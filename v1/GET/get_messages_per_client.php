<?php

declare(strict_types=1);

/**
 * get_messages_per_client.php
 *
 * Returns the same (day, country_code) aggregation the daily cron writes
 * into `daily_conversations_stats` (see v1/cron/day.php), but computed live
 * for a caller-supplied collection of account numbers and broken down per
 * client, instead of stored for every account at once.
 *
 * The numbers are always sent in the JSON body. Optional filters:
 *   - date range      date_from / date_to   ('Y-m-d', inclusive)
 *   - direction       in | out | both       (default: out)
 *   - message status  all | delivered | undelivered
 *   - errors only     errors_only           (only sends that errored)
 *   - country         ISO 3166-1 alpha-2, matched against the country code
 *                     derived from the counterparty's phone number
 *   - message type    template (Marketing) | service | all
 *
 * Every figure is produced by the same helpers the daily cron uses, so an
 * endpoint row and a `daily_conversations_stats` row are comparable value
 * for value.
 *
 * @author ruvenss <ruvenss@gmail.com>
 */

/**
 * Validates one 'Y-m-d' date, or returns null when it was not supplied.
 * Rejects both malformed input and impossible calendar dates (2026-02-30).
 */
function get_messages_per_client_date(mixed $value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        http_response(400, ["error" => "Bad Request: '$field' must be a date string in Y-m-d format"]);
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        http_response(400, ["error" => "Bad Request: '$field' must be a valid calendar date in Y-m-d format"]);
    }

    return $date->format('Y-m-d');
}

/**
 * Normalises the requested account numbers into bare digit strings. They
 * land in a table identifier, which can neither be bound nor escaped, so
 * anything that is not purely numeric is refused rather than cleaned up.
 *
 * @return array{numbers: list<string>, rejected: list<string>}
 */
function get_messages_per_client_numbers(mixed $raw): array
{
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }

    if (!is_array($raw) || $raw === []) {
        http_response(400, ["error" => "Bad Request: 'numbers' must be a non-empty array of account phone numbers"]);
    }

    $numbers = [];
    $rejected = [];

    foreach ($raw as $entry) {
        // A bool would stringify to "1"/"" and quietly become a bogus
        // account number, so only real string/number entries are accepted.
        if (!is_string($entry) && !is_int($entry) && !is_float($entry)) {
            $rejected[] = '(unsupported value type: ' . get_debug_type($entry) . ')';
            continue;
        }

        $digits = preg_replace('/\D+/', '', (string) $entry);
        if ($digits === '' || preg_match('/^[0-9]{1,32}$/', $digits) !== 1) {
            $rejected[] = (string) $entry;
            continue;
        }

        $numbers[$digits] = $digits;
    }

    if ($numbers === []) {
        http_response(400, ["error" => "Bad Request: 'numbers' contained no usable account phone number"]);
    }

    return ['numbers' => array_values($numbers), 'rejected' => $rejected];
}

/**
 * @return list<string> the requested ISO 3166-1 alpha-2 codes, uppercased
 */
function get_messages_per_client_countries(mixed $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }

    if (!is_array($raw)) {
        http_response(400, ["error" => "Bad Request: 'country' must be a two-letter ISO code or a list of them"]);
    }

    $countries = [];
    foreach ($raw as $entry) {
        if (!is_scalar($entry)) {
            http_response(400, ["error" => "Bad Request: 'country' must be a two-letter ISO code or a list of them"]);
        }

        $code = strtoupper(trim((string) $entry));
        if ($code === '') {
            continue;
        }

        if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            http_response(400, ["error" => "Bad Request: '$code' is not a two-letter ISO 3166-1 alpha-2 country code"]);
        }

        $countries[$code] = $code;
    }

    return array_values($countries);
}

/**
 * Reads one of the small closed-vocabulary filters. Anything that is not a
 * plain scalar is refused outright rather than cast to a string, which
 * would silently turn an array into the word "Array".
 */
function get_messages_per_client_choice(mixed $value, string $field, string $default): string
{
    if ($value === null) {
        return $default;
    }

    if (!is_string($value) && !is_int($value)) {
        http_response(400, ["error" => "Bad Request: '$field' must be a string"]);
    }

    $choice = strtolower(trim((string) $value));

    return $choice === '' ? $default : $choice;
}

function get_messages_per_client(): void
{
    // A request this endpoint cannot answer at all - no database - is a
    // server-side problem, not a bad request.
    if (!defined('dbconn') || !isset(ms_secrets['db']['name'])) {
        http_response(500, ["error" => "Internal Server Error: database is not configured"]);
    }

    $maxNumbers = 200;
    $maxSpanDays = 366;

    $params = request_data['parameters'] ?? [];
    $payload = request_data['payload'] ?? [];

    if (!is_array($params)) {
        http_response(400, ["error" => "Bad Request: 'parameters' must be an object"]);
    }

    $parsed = get_messages_per_client_numbers(
        $params['numbers'] ?? (is_array($payload) ? ($payload['numbers'] ?? null) : null) ?? request_data['numbers'] ?? null
    );
    $numbers = $parsed['numbers'];

    if (count($numbers) > $maxNumbers) {
        http_response(400, ["error" => "Bad Request: at most $maxNumbers numbers can be requested at once, " . count($numbers) . " given"]);
    }

    // Defaults mirror the daily cron, which refreshes yesterday and today.
    $dateFrom = get_messages_per_client_date($params['date_from'] ?? $params['from_date'] ?? null, 'date_from')
        ?? (new DateTimeImmutable('yesterday'))->format('Y-m-d');
    $dateTo = get_messages_per_client_date($params['date_to'] ?? $params['until_date'] ?? null, 'date_to')
        ?? (new DateTimeImmutable('today'))->format('Y-m-d');

    if ($dateFrom > $dateTo) {
        http_response(400, ["error" => "Bad Request: 'date_from' must be on or before 'date_to'"]);
    }

    $spanDays = (int) (new DateTimeImmutable($dateFrom))->diff(new DateTimeImmutable($dateTo))->format('%a') + 1;
    if ($spanDays > $maxSpanDays) {
        http_response(400, ["error" => "Bad Request: date range spans $spanDays days, the maximum is $maxSpanDays"]);
    }

    $direction = get_messages_per_client_choice($params['direction'] ?? null, 'direction', 'out');
    if (!in_array($direction, ['in', 'out', 'both'], true)) {
        http_response(400, ["error" => "Bad Request: 'direction' must be one of in, out, both"]);
    }

    $status = match (get_messages_per_client_choice($params['status'] ?? null, 'status', 'all')) {
        'all' => 'all',
        'delivered', 'sent', 'success' => 'delivered',
        'undelivered', 'not_delivered', 'failed' => 'undelivered',
        default => null,
    };
    if ($status === null) {
        http_response(400, ["error" => "Bad Request: 'status' must be one of all, delivered, undelivered"]);
    }

    $messageType = match (get_messages_per_client_choice($params['message_type'] ?? null, 'message_type', 'all')) {
        'all' => 'all',
        'template', 'templates', 'marketing' => 'marketing',
        'service', 'free_form', 'freeform' => 'service',
        default => null,
    };
    if ($messageType === null) {
        http_response(400, ["error" => "Bad Request: 'message_type' must be one of all, template, service"]);
    }

    $filters = [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'direction' => $direction,
        'status' => $status,
        'errors_only' => filter_var($params['errors_only'] ?? $params['only_errors'] ?? false, FILTER_VALIDATE_BOOL),
        'countries' => get_messages_per_client_countries($params['country'] ?? $params['countries'] ?? null),
        'message_type' => $messageType,
    ];

    $result = wa_collect_client_message_stats($numbers, $filters);

    http_response(200, [
        "values" => [
            "clients" => $result['clients'],
            "totals" => $result['totals'],
        ],
        "filters" => $filters,
        "unknown_numbers" => $result['unknown_numbers'],
        "rejected_numbers" => $parsed['rejected'],
        "meta" => [
            "grain" => "day + country_code",
            "days_in_range" => $spanDays,
            "currency" => "EUR",
            // Days with no traffic at all are simply absent from "days";
            // the totals still cover the whole requested range.
            "empty_days_omitted" => true,
            // A message_type filter is meaningless for incoming traffic, so
            // asking for one drops the incoming side rather than counting it
            // unfiltered.
            "incoming_included" => in_array($direction, ['in', 'both'], true) && $messageType === 'all',
            // Which column each out_ table's error count was derived from.
            "error_basis" => $result['error_basis'],
            // False means whatsapp_pricing_rates does not exist yet (the
            // daily cron creates and seeds it), so every cost reads 0.
            "pricing_rates_available" => $result['pricing_rates_available'],
        ],
    ]);
}
get_messages_per_client();
