<?php

declare(strict_types=1);

namespace Plugins\Messages_stats\Controllers;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Pmsrapi\V2\Database\Connection;
use Pmsrapi\V2\Database\Schema;
use Pmsrapi\V2\Exception\ApiException;
use Pmsrapi\V2\Http\Request;
use Pmsrapi\V2\Http\Response;

final class StatsController
{
    private const array ALLOWED_INTERVALS = ['hour', 'day', 'week', 'month', 'year'];
    private const array ALLOWED_DIRECTIONS = ['in', 'out'];
    private const int MAX_BUCKETS = 10_000;

    public function __construct(
        private readonly Connection $db,
        private readonly Schema $schema,
    ) {}

    /**
     * @throws \DateMalformedStringException
     * @throws \DateMalformedPeriodStringException
     */
    public function stats(Request $request): Response
    {
        $fromDate = $request->query('from_date');
        $untilDate = $request->query('until_date');
        $direction = $request->query('direction');
        $interval = $request->query('interval');
        $account = $request->query('account');

        if ($fromDate === null || $untilDate === null || $direction === null || $interval === null) {
            throw new ApiException(
                'Missing required parameters: from_date, until_date, direction, interval',
                400,
                'validation_error',
            );
        }

        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new ApiException(
                'Invalid direction. Allowed: in, out',
                400,
                'validation_error',
            );
        }

        if (!in_array($interval, self::ALLOWED_INTERVALS, true)) {
            throw new ApiException(
                'Invalid interval. Allowed: hour, day, week, month, year',
                400,
                'validation_error',
            );
        }

        $from = $this->parseDate($fromDate, 'from_date');
        $until = $this->parseDate($untilDate, 'until_date');

        $from = $from->setTime(0, 0);
        $untilExclusive = $until->modify('+1 day')->setTime(0, 0);

        if ($from >= $untilExclusive) {
            throw new ApiException(
                'from_date must be before or equal to until_date',
                400,
                'validation_error',
            );
        }

        $bucketCount = $this->estimateBuckets($from, $untilExclusive, $interval);
        if ($bucketCount > self::MAX_BUCKETS) {
            throw new ApiException(
                sprintf('Date range too large: ~%d buckets exceed the %d limit. Narrow the range or use a coarser interval.', $bucketCount, self::MAX_BUCKETS),
                400,
                'range_too_large',
            );
        }

        $dateFormat = $this->mysqlDateFormat($interval);

        $start = $from->format('Y-m-d H:i:s');
        $end = $untilExclusive->format('Y-m-d H:i:s');

        $account = trim((string) $account);

        $tables = $account === ''
            ? $this->accountTables($direction)
            : [$this->accountTable($direction, $account)];

        $countsByPeriod = [];
        $totalMessages = 0;
        foreach ($tables as $table) {
            foreach ($this->countPerPeriod($table, $dateFormat, $start, $end) as $row) {
                $period = (string) $row['period'];
                $count = (int) $row['count'];
                $countsByPeriod[$period] = ($countsByPeriod[$period] ?? 0) + $count;
                $totalMessages += $count;
            }
        }

        $allPeriods = $this->generatePeriods($from, $untilExclusive, $interval);

        $labels = [];
        $data = [];
        foreach ($allPeriods as $period) {
            $labels[] = $period;
            $data[] = $countsByPeriod[$period] ?? 0;
        }

        return Response::ok([
            'total_messages' => $totalMessages,
            'chartjs_data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => "Messages ({$direction})",
                        'data' => $data,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Resolves the per-account table name, whitelisting it against the live schema.
     * Account ids are WhatsApp phone numbers, so anything non-numeric is rejected
     * before it can reach an identifier position (identifiers cannot be bound).
     */
    private function accountTable(string $direction, string $account): string
    {
        if (preg_match('/^[0-9]{1,32}$/', $account) !== 1) {
            throw new ApiException(
                'Invalid account. Expected a numeric account id.',
                400,
                'validation_error',
            );
        }

        $table = $direction . '_' . $account;

        if ($this->schema->columns($table) === []) {
            throw new ApiException(
                "Unknown account: {$account}",
                404,
                'not_found',
            );
        }

        return $table;
    }

    /**
     * @return list<string> every per-account message table for this direction
     */
    private function accountTables(string $direction): array
    {
        $rows = $this->db->select(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME REGEXP ?
             ORDER BY TABLE_NAME ASC",
            [$this->db->databaseName(), '^' . $direction . '_[0-9]+$'],
        );

        return array_map(static fn(array $row): string => (string) $row['TABLE_NAME'], $rows);
    }

    /**
     * @return list<array{period: string, count: int|string}>
     */
    private function countPerPeriod(string $table, string $dateFormat, string $start, string $end): array
    {
        $sql = sprintf(
            'SELECT DATE_FORMAT(`delivered_date`, ?) AS `period`, COUNT(*) AS `count`
             FROM %s
             WHERE `delivered_date` >= ? AND `delivered_date` < ?
             GROUP BY `period`',
            $this->schema->quote($table),
        );

        return $this->db->select($sql, [$dateFormat, $start, $end]);
    }

    private function parseDate(string $value, string $field): DateTimeImmutable
    {
        if (str_contains($value, "\0")) {
            throw new ApiException(
                "Invalid date format for {$field}. Expected: Y-m-d",
                400,
                'validation_error',
            );
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            throw new ApiException(
                "Invalid date format for {$field}. Expected: Y-m-d",
                400,
                'validation_error',
            );
        }

        if ($date->format('Y-m-d') !== $value) {
            throw new ApiException(
                "Invalid calendar date for {$field}: {$value}",
                400,
                'validation_error',
            );
        }

        return $date;
    }

    private function mysqlDateFormat(string $interval): string
    {
        return match ($interval) {
            'hour' => '%Y-%m-%d %H:00',
            'day' => '%Y-%m-%d',
            'week' => '%x-W%v',
            'month' => '%Y-%m',
            'year' => '%Y',
        };
    }

    private function estimateBuckets(DateTimeImmutable $from, DateTimeImmutable $untilExclusive, string $interval): int
    {
        $diff = $from->diff($untilExclusive);
        $totalDays = (int) $diff->format('%a');

        return match ($interval) {
            'hour' => $totalDays * 24 + 1,
            'day' => $totalDays + 1,
            'week' => (int) ceil($totalDays / 7) + 1,
            'month' => $diff->y * 12 + $diff->m + 1,
            'year' => $diff->y + 1,
        };
    }

    /**
     * @return list<string>
     * @throws \DateMalformedPeriodStringException
     */
    private function generatePeriods(DateTimeImmutable $from, DateTimeImmutable $untilExclusive, string $interval): array
    {
        $start = $this->alignToStart($from, $interval);

        $phpFormat = match ($interval) {
            'hour' => 'Y-m-d H:00',
            'day' => 'Y-m-d',
            'week' => 'o-\WW',
            'month' => 'Y-m',
            'year' => 'Y',
        };

        $dateInterval = match ($interval) {
            'hour' => new DateInterval('PT1H'),
            'day' => new DateInterval('P1D'),
            'week' => new DateInterval('P1W'),
            'month' => new DateInterval('P1M'),
            'year' => new DateInterval('P1Y'),
        };

        $periods = [];
        $period = new DatePeriod($start, $dateInterval, $untilExclusive);
        foreach ($period as $date) {
            $periods[] = $date->format($phpFormat);
        }

        return $periods;
    }

    /**
     * @throws \DateMalformedStringException
     */
    private function alignToStart(DateTimeImmutable $date, string $interval): DateTimeImmutable
    {
        return match ($interval) {
            'hour' => $date->setTime((int) $date->format('H'), 0),
            'day' => $date->setTime(0, 0),
            'week' => $date->modify('Monday this week')->setTime(0, 0),
            'month' => $date->modify('first day of this month')->setTime(0, 0),
            'year' => $date->modify('first day of January this year')->setTime(0, 0),
        };
    }
}
