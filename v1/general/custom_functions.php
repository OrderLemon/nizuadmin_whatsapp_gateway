<?php
/**
 * User-owned helpers, safe from framework auto-updates.
 * Auto-included by index.php / cron scripts when present.
 */

/**
 * Maps a WhatsApp phone number to its ISO 3166-1 alpha-2 country code and
 * the market/region bucket used by Meta's WhatsApp Business Platform
 * pricing, per https://developers.facebook.com/docs/whatsapp/pricing#country-calling-codes
 * Both are derived from the same calling-code table so they can never drift
 * out of sync with each other.
 *
 * @return array{country: string, market: string}
 */
function wa_recipient_geo(string $phone): array
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') {
        return ['country' => 'XX', 'market' => 'Other'];
    }

    // NANP (+1) is shared by the US/Canada and a handful of Caribbean
    // markets that price under "Rest of Latin America" instead of "North
    // America". The area code alone can't otherwise tell US from Canada, so
    // default to US - the common case for this business's traffic.
    static $nanpOverrides = [
        '809' => ['DO', 'Rest of Latin America'], '829' => ['DO', 'Rest of Latin America'], '849' => ['DO', 'Rest of Latin America'],
        '658' => ['JM', 'Rest of Latin America'], '876' => ['JM', 'Rest of Latin America'],
        '787' => ['PR', 'Rest of Latin America'], '939' => ['PR', 'Rest of Latin America'],
    ];
    if ($digits[0] === '1') {
        $areaCode = substr($digits, 1, 3);
        if (isset($nanpOverrides[$areaCode])) {
            return ['country' => $nanpOverrides[$areaCode][0], 'market' => $nanpOverrides[$areaCode][1]];
        }
        return ['country' => 'US', 'market' => 'North America'];
    }

    static $callingCodeGeo = [
        // Standalone markets
        '54' => ['AR', 'Argentina'], '55' => ['BR', 'Brazil'], '56' => ['CL', 'Chile'], '57' => ['CO', 'Colombia'],
        '20' => ['EG', 'Egypt'], '33' => ['FR', 'France'], '49' => ['DE', 'Germany'], '852' => ['HK', 'Hong Kong'],
        '36' => ['HU', 'Hungary'], '91' => ['IN', 'India'], '62' => ['ID', 'Indonesia'], '972' => ['IL', 'Israel'],
        '39' => ['IT', 'Italy'], '60' => ['MY', 'Malaysia'], '52' => ['MX', 'Mexico'], '31' => ['NL', 'Netherlands'],
        '234' => ['NG', 'Nigeria'], '92' => ['PK', 'Pakistan'], '51' => ['PE', 'Peru'], '48' => ['PL', 'Poland'],
        '974' => ['QA', 'Qatar'], '40' => ['RO', 'Romania'], '7' => ['RU', 'Russia'], '966' => ['SA', 'Saudi Arabia'],
        '65' => ['SG', 'Singapore'], '27' => ['ZA', 'South Africa'], '34' => ['ES', 'Spain'], '90' => ['TR', 'Turkey'],
        '971' => ['AE', 'United Arab Emirates'], '44' => ['GB', 'United Kingdom'],

        // Rest of Africa
        '213' => ['DZ', 'Rest of Africa'], '244' => ['AO', 'Rest of Africa'], '229' => ['BJ', 'Rest of Africa'],
        '267' => ['BW', 'Rest of Africa'], '226' => ['BF', 'Rest of Africa'], '257' => ['BI', 'Rest of Africa'],
        '237' => ['CM', 'Rest of Africa'], '235' => ['TD', 'Rest of Africa'], '242' => ['CG', 'Rest of Africa'],
        '291' => ['ER', 'Rest of Africa'], '251' => ['ET', 'Rest of Africa'], '241' => ['GA', 'Rest of Africa'],
        '220' => ['GM', 'Rest of Africa'], '233' => ['GH', 'Rest of Africa'], '245' => ['GW', 'Rest of Africa'],
        '225' => ['CI', 'Rest of Africa'], '254' => ['KE', 'Rest of Africa'], '266' => ['LS', 'Rest of Africa'],
        '231' => ['LR', 'Rest of Africa'], '218' => ['LY', 'Rest of Africa'], '261' => ['MG', 'Rest of Africa'],
        '265' => ['MW', 'Rest of Africa'], '223' => ['ML', 'Rest of Africa'], '222' => ['MR', 'Rest of Africa'],
        '212' => ['MA', 'Rest of Africa'], '258' => ['MZ', 'Rest of Africa'], '264' => ['NA', 'Rest of Africa'],
        '227' => ['NE', 'Rest of Africa'], '250' => ['RW', 'Rest of Africa'], '221' => ['SN', 'Rest of Africa'],
        '232' => ['SL', 'Rest of Africa'], '252' => ['SO', 'Rest of Africa'], '211' => ['SS', 'Rest of Africa'],
        '249' => ['SD', 'Rest of Africa'], '268' => ['SZ', 'Rest of Africa'], '255' => ['TZ', 'Rest of Africa'],
        '228' => ['TG', 'Rest of Africa'], '216' => ['TN', 'Rest of Africa'], '256' => ['UG', 'Rest of Africa'],
        '260' => ['ZM', 'Rest of Africa'], '263' => ['ZW', 'Rest of Africa'],

        // Rest of Asia Pacific
        '93' => ['AF', 'Rest of Asia Pacific'], '61' => ['AU', 'Rest of Asia Pacific'], '880' => ['BD', 'Rest of Asia Pacific'],
        '855' => ['KH', 'Rest of Asia Pacific'], '86' => ['CN', 'Rest of Asia Pacific'], '81' => ['JP', 'Rest of Asia Pacific'],
        '856' => ['LA', 'Rest of Asia Pacific'], '976' => ['MN', 'Rest of Asia Pacific'], '977' => ['NP', 'Rest of Asia Pacific'],
        '64' => ['NZ', 'Rest of Asia Pacific'], '675' => ['PG', 'Rest of Asia Pacific'], '63' => ['PH', 'Rest of Asia Pacific'],
        '94' => ['LK', 'Rest of Asia Pacific'], '886' => ['TW', 'Rest of Asia Pacific'], '992' => ['TJ', 'Rest of Asia Pacific'],
        '66' => ['TH', 'Rest of Asia Pacific'], '993' => ['TM', 'Rest of Asia Pacific'], '998' => ['UZ', 'Rest of Asia Pacific'],
        '84' => ['VN', 'Rest of Asia Pacific'],

        // Rest of Central & Eastern Europe
        '355' => ['AL', 'Rest of Central & Eastern Europe'], '374' => ['AM', 'Rest of Central & Eastern Europe'],
        '994' => ['AZ', 'Rest of Central & Eastern Europe'], '375' => ['BY', 'Rest of Central & Eastern Europe'],
        '359' => ['BG', 'Rest of Central & Eastern Europe'], '385' => ['HR', 'Rest of Central & Eastern Europe'],
        '420' => ['CZ', 'Rest of Central & Eastern Europe'], '995' => ['GE', 'Rest of Central & Eastern Europe'],
        '30' => ['GR', 'Rest of Central & Eastern Europe'], '371' => ['LV', 'Rest of Central & Eastern Europe'],
        '370' => ['LT', 'Rest of Central & Eastern Europe'], '373' => ['MD', 'Rest of Central & Eastern Europe'],
        '389' => ['MK', 'Rest of Central & Eastern Europe'], '381' => ['RS', 'Rest of Central & Eastern Europe'],
        '421' => ['SK', 'Rest of Central & Eastern Europe'], '386' => ['SI', 'Rest of Central & Eastern Europe'],
        '380' => ['UA', 'Rest of Central & Eastern Europe'],

        // Rest of Western Europe
        '43' => ['AT', 'Rest of Western Europe'], '32' => ['BE', 'Rest of Western Europe'], '45' => ['DK', 'Rest of Western Europe'],
        '358' => ['FI', 'Rest of Western Europe'], '353' => ['IE', 'Rest of Western Europe'], '47' => ['NO', 'Rest of Western Europe'],
        '351' => ['PT', 'Rest of Western Europe'], '46' => ['SE', 'Rest of Western Europe'], '41' => ['CH', 'Rest of Western Europe'],

        // Rest of Latin America
        '591' => ['BO', 'Rest of Latin America'], '506' => ['CR', 'Rest of Latin America'], '593' => ['EC', 'Rest of Latin America'],
        '503' => ['SV', 'Rest of Latin America'], '502' => ['GT', 'Rest of Latin America'], '509' => ['HT', 'Rest of Latin America'],
        '504' => ['HN', 'Rest of Latin America'], '505' => ['NI', 'Rest of Latin America'], '507' => ['PA', 'Rest of Latin America'],
        '595' => ['PY', 'Rest of Latin America'], '598' => ['UY', 'Rest of Latin America'], '58' => ['VE', 'Rest of Latin America'],

        // Rest of Middle East
        '973' => ['BH', 'Rest of Middle East'], '964' => ['IQ', 'Rest of Middle East'], '962' => ['JO', 'Rest of Middle East'],
        '965' => ['KW', 'Rest of Middle East'], '961' => ['LB', 'Rest of Middle East'], '968' => ['OM', 'Rest of Middle East'],
        '967' => ['YE', 'Rest of Middle East'],
    ];

    foreach ([3, 2, 1] as $len) {
        $prefix = substr($digits, 0, $len);
        if (strlen($prefix) === $len && isset($callingCodeGeo[$prefix])) {
            return ['country' => $callingCodeGeo[$prefix][0], 'market' => $callingCodeGeo[$prefix][1]];
        }
    }

    return ['country' => 'XX', 'market' => 'Other'];
}

function wa_recipient_market(string $phone): string
{
    return wa_recipient_geo($phone)['market'];
}

function wa_recipient_country_code(string $phone): string
{
    return wa_recipient_geo($phone)['country'];
}

/**
 * WhatsApp Business Platform per-message rates in EUR, effective 2026-07-01.
 * 'n/a' markets (authentication_international / service) are stored as NULL.
 */
function wa_pricing_rate_seed(): array
{
    return [
        ['Argentina', 0.0512, 0.0216, 0.0216, null, null],
        ['Brazil', 0.0518, 0.0056, 0.0056, null, null],
        ['Chile', 0.0736, 0.0166, 0.0166, null, null],
        ['Colombia', 0.0104, 0.0008, 0.0008, null, null],
        ['Egypt', 0.0533, 0.0030, 0.0030, 0.0538, null],
        ['France', 0.0712, 0.0248, 0.0248, null, null],
        ['Germany', 0.1131, 0.0456, 0.0456, null, null],
        ['Hong Kong', 0.0606, 0.0216, 0.0216, null, null],
        ['Hungary', 0.0712, 0.0289, 0.0289, null, null],
        ['India', 0.0099, 0.0012, 0.0012, 0.0252, null],
        ['Indonesia', 0.0341, 0.0208, 0.0208, 0.1129, null],
        ['Israel', 0.0292, 0.0044, 0.0044, null, null],
        ['Italy', 0.0658, 0.0248, 0.0248, null, null],
        ['Malaysia', 0.0712, 0.0116, 0.0116, 0.0346, null],
        ['Mexico', 0.0253, 0.0071, 0.0071, null, null],
        ['Netherlands', 0.1323, 0.0414, 0.0414, null, null],
        ['Nigeria', 0.0428, 0.0056, 0.0056, 0.0622, null],
        ['Pakistan', 0.0392, 0.0083, 0.0083, 0.0622, null],
        ['Peru', 0.0582, 0.0166, 0.0166, null, null],
        ['Poland', 0.0303, 0.0101, 0.0101, null, null],
        ['Qatar', 0.0282, 0.0099, 0.0099, null, null],
        ['Romania', 0.0712, 0.0239, 0.0239, null, null],
        ['Russia', 0.0664, 0.0331, 0.0331, null, null],
        ['Saudi Arabia', 0.0414, 0.0088, 0.0088, 0.0495, null],
        ['Singapore', 0.0606, 0.0133, 0.0133, null, null],
        ['South Africa', 0.0314, 0.0063, 0.0063, 0.0166, null],
        ['Spain', 0.0585, 0.0166, 0.0166, null, null],
        ['Turkey', 0.0090, 0.0007, 0.0007, null, null],
        ['United Arab Emirates', 0.0415, 0.0130, 0.0130, 0.0421, null],
        ['United Kingdom', 0.0526, 0.0182, 0.0182, null, null],
        ['North America', 0.0207, 0.0028, 0.0028, null, null],
        ['Rest of Africa', 0.0186, 0.0033, 0.0033, null, null],
        ['Rest of Asia Pacific', 0.0606, 0.0094, 0.0094, null, null],
        ['Rest of Central & Eastern Europe', 0.0712, 0.0175, 0.0175, null, null],
        ['Rest of Latin America', 0.0612, 0.0094, 0.0094, null, null],
        ['Rest of Middle East', 0.0282, 0.0075, 0.0075, null, null],
        ['Rest of Western Europe', 0.0490, 0.0142, 0.0142, null, null],
        ['Other', 0.0500, 0.0064, 0.0064, null, null],
    ];
}

/**
 * Bootstraps whatsapp_pricing_rates with the 2026-07-01 baseline rates.
 * Only ever called once, right after the table is created - this table is
 * managed by hand from then on (e.g. adding the 2026-10-01 Service rates),
 * so the cron must never overwrite rows a person has edited.
 */
function wa_seed_pricing_rates(): void
{
    foreach (wa_pricing_rate_seed() as [$market, $marketing, $utility, $authentication, $authIntl, $service]) {
        sqlInsert(
            'whatsapp_pricing_rates',
            ['market', 'effective_date', 'currency', 'marketing', 'utility', 'authentication', 'authentication_international', 'service'],
            [$market, '2026-07-01', 'EUR', $marketing, $utility, $authentication, $authIntl ?? 'NULL', $service ?? 'NULL']
        );
    }
}

function wa_ensure_stats_tables(string $dbName): void
{
    if (sqlTableExist('daily_conversations_stats', $dbName)) {
        // Migration: the table used to be keyed by day alone. Since this
        // table is a fully-derived aggregation cache (never source data),
        // it's safe to drop and let the backfill below rebuild it fresh
        // under the new (day, country_code) grain.
        if (!in_array('country_code', sqlTableInformationOf('daily_conversations_stats'), true)) {
            dbconn->query('DROP TABLE `daily_conversations_stats`');
        }
    }

    if (!sqlTableExist('daily_conversations_stats', $dbName)) {
        dbconn->query(
            "CREATE TABLE IF NOT EXISTS `daily_conversations_stats` (" .
            "`day` DATE NOT NULL," .
            "`country_code` CHAR(2) NOT NULL," .
            "`messages_in` INT UNSIGNED NOT NULL DEFAULT 0," .
            "`messages_out` INT UNSIGNED NOT NULL DEFAULT 0," .
            "`messages_in_success` INT UNSIGNED NOT NULL DEFAULT 0," .
            "`messages_out_success` INT UNSIGNED NOT NULL DEFAULT 0," .
            "`total_marketing_messages` INT UNSIGNED NOT NULL DEFAULT 0," .
            "`total_service_messages` INT UNSIGNED NOT NULL DEFAULT 0," .
            "`cost_in` DECIMAL(12,4) NOT NULL DEFAULT 0.0000," .
            "`cost_out` DECIMAL(12,4) NOT NULL DEFAULT 0.0000," .
            "`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP," .
            "PRIMARY KEY (`day`, `country_code`)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!sqlTableExist('whatsapp_pricing_rates', $dbName)) {
        dbconn->query(
            "CREATE TABLE IF NOT EXISTS `whatsapp_pricing_rates` (" .
            "`market` VARCHAR(64) NOT NULL," .
            "`effective_date` DATE NOT NULL," .
            "`currency` VARCHAR(3) NOT NULL DEFAULT 'EUR'," .
            "`marketing` DECIMAL(10,4) NULL," .
            "`utility` DECIMAL(10,4) NULL," .
            "`authentication` DECIMAL(10,4) NULL," .
            "`authentication_international` DECIMAL(10,4) NULL," .
            "`service` DECIMAL(10,4) NULL," .
            "PRIMARY KEY (`market`, `effective_date`)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Only seed on first creation - this table is hand-maintained from
        // here on (new effective_date rows, e.g. 2026-10-01's Service rates,
        // are added by a person directly, never by this cron job).
        wa_seed_pricing_rates();
    }
}

/**
 * Loads every rate version for every market, sorted oldest-first, so the
 * caller can pick whichever version was in effect on a given message's day.
 *
 * @return array<string, list<array{effective_date: string, marketing: ?float, utility: ?float, service: ?float}>>
 */
function wa_load_pricing_rates(): array
{
    $rates = [];
    $rows = sqlSelectRows('whatsapp_pricing_rates', 'market, effective_date, marketing, utility, service', '1=1', 'effective_date ASC');
    foreach ($rows as $row) {
        $rates[$row['market']][] = [
            'effective_date' => $row['effective_date'],
            'marketing' => $row['marketing'] !== null ? (float) $row['marketing'] : null,
            'utility' => $row['utility'] !== null ? (float) $row['utility'] : null,
            'service' => $row['service'] !== null ? (float) $row['service'] : null,
        ];
    }
    return $rates;
}

/**
 * The rate for $market/$category in effect on $day: the most recent
 * version whose effective_date is on or before $day, or - for a day older
 * than every known version (all of our message history predates the first
 * pricing snapshot we have) - the earliest known version, as the closest
 * available approximation. Returns null if the market is unknown, or the
 * category has no rate in the applicable version (e.g. Service before it's
 * ever been priced).
 */
function wa_pricing_rate_for(array $rates, string $market, string $category, string $day): ?float
{
    $versions = $rates[$market] ?? [];
    if ($versions === []) {
        return null;
    }

    $applicable = $versions[0];
    foreach ($versions as $version) {
        if ($version['effective_date'] > $day) {
            break;
        }
        $applicable = $version;
    }
    return $applicable[$category] ?? null;
}

/**
 * send_template/hsm are business-initiated template sends, billed as
 * Marketing. Everything else is a free-form reply, billed as Service
 * (free until a Service rate is added to whatsapp_pricing_rates).
 */
function wa_out_message_category(string $messageType): string
{
    return in_array($messageType, ['send_template', 'hsm'], true) ? 'marketing' : 'service';
}

function wa_empty_country_row(): array
{
    return [
        'messages_in' => 0,
        'messages_out' => 0,
        'messages_in_success' => 0,
        'messages_out_success' => 0,
        'total_marketing_messages' => 0,
        'total_service_messages' => 0,
        'cost_out' => 0.0,
    ];
}

function wa_daily_country_row(array &$daily, string $day, string $country): void
{
    if (!isset($daily[$day][$country])) {
        $daily[$day][$country] = wa_empty_country_row();
    }
}

/**
 * Adds per-(day, country) totals for one account's in_$phone table, using
 * the sender's phone number to attribute each message to a country.
 */
function wa_accumulate_in_stats(array &$daily, string $phone, string $dbName, string $rangeStart, string $rangeEndExclusive): void
{
    $table = 'in_' . $phone;
    if (!sqlTableExist($table, $dbName)) {
        return;
    }

    $where = "incoming_date IS NOT NULL AND incoming_date >= '" . mysqli_real_escape_string(dbconn, $rangeStart) . "'"
        . " AND incoming_date < '" . mysqli_real_escape_string(dbconn, $rangeEndExclusive) . "'";

    $rows = sqlSelectRows($table, "DATE(incoming_date) AS day, sender_phone, delivered", $where);

    foreach ($rows as $row) {
        $day = $row['day'];
        if (!isset($daily[$day])) {
            continue;
        }

        $country = empty($row['sender_phone']) ? 'XX' : wa_recipient_country_code($row['sender_phone']);
        wa_daily_country_row($daily, $day, $country);

        $daily[$day][$country]['messages_in']++;
        if ((int) $row['delivered'] === 1) {
            $daily[$day][$country]['messages_in_success']++;
        }
    }
}

/**
 * Adds per-(day, country) totals, the Marketing/Service split, and cost_out
 * for one account's out_$phone table, using the recipient's phone number to
 * attribute each message to a country and pricing market. Cost is priced
 * per successfully delivered message at its category's rate for that
 * market, as of the message's own day - so this automatically starts
 * charging Service messages once a Service rate is added to
 * whatsapp_pricing_rates, with no code change.
 */
function wa_accumulate_out_stats(array &$daily, string $phone, string $dbName, string $rangeStart, string $rangeEndExclusive, array $rates): void
{
    $table = 'out_' . $phone;
    if (!sqlTableExist($table, $dbName)) {
        return;
    }

    $where = "incoming_date IS NOT NULL AND incoming_date >= '" . mysqli_real_escape_string(dbconn, $rangeStart) . "'"
        . " AND incoming_date < '" . mysqli_real_escape_string(dbconn, $rangeEndExclusive) . "'";

    $rows = sqlSelectRows(
        $table,
        "DATE(incoming_date) AS day, message_type, delivered, COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '\$.recipient_phonenumber')), JSON_UNQUOTE(JSON_EXTRACT(payload, '\$.to'))) AS recipient",
        $where
    );

    foreach ($rows as $row) {
        $day = $row['day'];
        if (!isset($daily[$day])) {
            continue;
        }

        $geo = empty($row['recipient']) ? ['country' => 'XX', 'market' => 'Other'] : wa_recipient_geo($row['recipient']);
        $country = $geo['country'];
        wa_daily_country_row($daily, $day, $country);

        $daily[$day][$country]['messages_out']++;

        $category = wa_out_message_category($row['message_type']);
        if ($category === 'marketing') {
            $daily[$day][$country]['total_marketing_messages']++;
        } else {
            $daily[$day][$country]['total_service_messages']++;
        }

        if ((int) $row['delivered'] === 1) {
            $daily[$day][$country]['messages_out_success']++;
            $rate = wa_pricing_rate_for($rates, $geo['market'], $category, $day);
            if ($rate !== null) {
                $daily[$day][$country]['cost_out'] += $rate;
            }
        }
    }
}

/**
 * Backfills/refreshes daily_conversations_stats for every day in
 * [$startDate, $endDate] (inclusive, 'Y-m-d'), across all accounts, one row
 * per (day, country_code) that had activity. A day with no messages at all
 * still gets exactly one row, with the sentinel country_code 'XX' and
 * everything at 0. cost_in is always 0 - WhatsApp doesn't charge businesses
 * to receive messages.
 */
function wa_collect_daily_conversation_stats(string $startDate, string $endDate): void
{
    if (!defined('dbconn')) {
        echo "wa_collect_daily_conversation_stats: no database connection available\n";
        return;
    }

    $dbName = ms_secrets['db']['name'];
    wa_ensure_stats_tables($dbName);
    $rates = wa_load_pricing_rates();

    $daily = [];
    $cursor = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);
    while ($cursor <= $end) {
        $daily[$cursor->format('Y-m-d')] = [];
        $cursor = $cursor->modify('+1 day');
    }

    if (!sqlTableExist('accounts', $dbName)) {
        echo "wa_collect_daily_conversation_stats: accounts table not found\n";
    } else {
        $rangeStart = $startDate . ' 00:00:00';
        $rangeEndExclusive = $end->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

        foreach (sqlSelectRows('accounts', 'phonenumber', '1=1') as $account) {
            $phone = (string) $account['phonenumber'];
            if (preg_match('/^[0-9]{1,32}$/', $phone) !== 1) {
                continue;
            }

            wa_accumulate_in_stats($daily, $phone, $dbName, $rangeStart, $rangeEndExclusive);
            wa_accumulate_out_stats($daily, $phone, $dbName, $rangeStart, $rangeEndExclusive, $rates);
        }
    }

    $rowCount = 0;
    foreach ($daily as $day => $countries) {
        if ($countries === []) {
            $countries = ['XX' => wa_empty_country_row()];
        }

        foreach ($countries as $country => $stats) {
            sqlInsert(
                'daily_conversations_stats',
                ['day', 'country_code', 'messages_in', 'messages_out', 'messages_in_success', 'messages_out_success', 'total_marketing_messages', 'total_service_messages', 'cost_in', 'cost_out'],
                [
                    $day,
                    $country,
                    $stats['messages_in'],
                    $stats['messages_out'],
                    $stats['messages_in_success'],
                    $stats['messages_out_success'],
                    $stats['total_marketing_messages'],
                    $stats['total_service_messages'],
                    '0.0000',
                    number_format($stats['cost_out'], 4, '.', ''),
                ],
                'messages_in=VALUES(messages_in), messages_out=VALUES(messages_out), messages_in_success=VALUES(messages_in_success), messages_out_success=VALUES(messages_out_success), total_marketing_messages=VALUES(total_marketing_messages), total_service_messages=VALUES(total_service_messages), cost_in=VALUES(cost_in), cost_out=VALUES(cost_out)'
            );
            $rowCount++;
        }
    }

    echo "wa_collect_daily_conversation_stats: processed " . count($daily) . " days, wrote $rowCount rows\n";
}

/* ---------------------------------------------------------------------
 * Per-client (per-account) message statistics
 * ---------------------------------------------------------------------
 * Powers GET/get_messages_per_client.php: the exact same
 * (day, country_code) aggregation the daily cron stores in
 * `daily_conversations_stats`, but computed live for a caller-supplied
 * set of account numbers and returned per client instead of stored.
 *
 * Every decision that produces a number - country attribution, the
 * Marketing/Service split, which rate version applies - is delegated to
 * the same helpers the cron calls, so the endpoint can never drift away
 * from the daily job. Only the scope (chosen accounts), the filters and
 * the output shape differ.
 */

/**
 * Column name => lowercased column type for $table, cached per request.
 * The per-account tables are created by the gateway rather than by this
 * repo, so anything beyond the columns the cron already relies on is
 * discovered instead of assumed.
 *
 * @return array<string, string>
 */
function wa_table_columns(string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $columns = [];
    if (defined('dbconn')) {
        try {
            $result = dbconn->query('DESC `' . $table . '`');
            if ($result !== false) {
                while ($field = $result->fetch_assoc()) {
                    $columns[(string) $field['Field']] = strtolower((string) $field['Type']);
                }
            }
        } catch (mysqli_sql_exception $e) {
            // Unreadable table - treated as "no optional columns", which
            // makes every caller fall back to its documented default.
            $columns = [];
        }
    }

    $cache[$table] = $columns;
    return $columns;
}

/**
 * A SQL expression evaluating to 1/0 for "this send errored", plus a note
 * of what it was derived from so the response can tell the caller which
 * basis was actually used.
 *
 * A dedicated error column is preferred when the table has one; otherwise
 * a send that never reached the recipient is treated as the error case.
 *
 * @return array{expression: string, basis: string}
 */
function wa_error_expression(string $table): array
{
    $columns = wa_table_columns($table);

    foreach (['error', 'error_message', 'error_code', 'errors', 'failure_reason'] as $candidate) {
        if (!isset($columns[$candidate])) {
            continue;
        }

        $isNumeric = preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|bit)/', $columns[$candidate]) === 1;

        return [
            'expression' => $isNumeric
                ? "(`$candidate` IS NOT NULL AND `$candidate` <> 0)"
                : "(`$candidate` IS NOT NULL AND TRIM(`$candidate`) <> '')",
            'basis' => $candidate,
        ];
    }

    if (isset($columns['delivered'])) {
        return ['expression' => '(`delivered` = 0)', 'basis' => 'delivered = 0'];
    }

    return ['expression' => '(1 = 0)', 'basis' => 'unavailable'];
}

/**
 * One empty (day, country_code) bucket. Mirrors the columns of
 * `daily_conversations_stats`, plus messages_out_error which the cron has
 * no column for but the endpoint reports.
 */
function wa_empty_client_stats(): array
{
    return [
        'messages_in' => 0,
        'messages_out' => 0,
        'messages_in_success' => 0,
        'messages_out_success' => 0,
        'messages_out_error' => 0,
        'total_marketing_messages' => 0,
        'total_service_messages' => 0,
        'cost_in' => 0.0,
        'cost_out' => 0.0,
    ];
}

function wa_client_bucket(array &$buckets, string $day, string $country): void
{
    if (!isset($buckets[$day][$country])) {
        $buckets[$day][$country] = wa_empty_client_stats();
    }
}

/**
 * Costs are rendered with the same 4-decimal precision the cron stores, so
 * an endpoint row and a `daily_conversations_stats` row are comparable
 * value for value.
 */
function wa_format_client_stats(array $stats): array
{
    $stats['cost_in'] = number_format((float) $stats['cost_in'], 4, '.', '');
    $stats['cost_out'] = number_format((float) $stats['cost_out'], 4, '.', '');

    return $stats;
}

/**
 * The WHERE fragment implementing the status / errors_only filters. Both
 * directions share it - in_ and out_ tables both carry `delivered`.
 */
function wa_client_status_where(array $filters, string $errorExpression): string
{
    $clauses = [];

    if ($filters['status'] === 'delivered') {
        $clauses[] = '`delivered` = 1';
    } elseif ($filters['status'] === 'undelivered') {
        $clauses[] = '`delivered` = 0';
    }

    if ($filters['errors_only']) {
        $clauses[] = $errorExpression;
    }

    return $clauses === [] ? '' : ' AND ' . implode(' AND ', $clauses);
}

/**
 * The shared date-range WHERE fragment. $rangeStart/$rangeEndExclusive are
 * already validated 'Y-m-d H:i:s' strings; they are escaped anyway because
 * the v1 sql* helpers interpolate rather than bind.
 */
function wa_client_range_where(string $rangeStart, string $rangeEndExclusive): string
{
    return '`incoming_date` IS NOT NULL'
        . " AND `incoming_date` >= '" . mysqli_real_escape_string(dbconn, $rangeStart) . "'"
        . " AND `incoming_date` < '" . mysqli_real_escape_string(dbconn, $rangeEndExclusive) . "'";
}

/**
 * Adds one account's incoming totals, attributing each message to a country
 * via the sender's phone number - the same rule wa_accumulate_in_stats()
 * uses for the daily job.
 */
function wa_accumulate_client_in_stats(array &$buckets, string $table, array $filters, string $rangeStart, string $rangeEndExclusive): void
{
    $where = wa_client_range_where($rangeStart, $rangeEndExclusive)
        . wa_client_status_where($filters, wa_error_expression($table)['expression']);

    $rows = sqlSelectRows(
        $table,
        'DATE(`incoming_date`) AS `day`, `sender_phone` AS `sender_phone`, `delivered` AS `delivered`, COUNT(*) AS `total`',
        $where,
        '',
        '',
        '`day`, `sender_phone`, `delivered`'
    );

    foreach ($rows as $row) {
        $sender = (string) ($row['sender_phone'] ?? '');
        $country = $sender === '' ? 'XX' : wa_recipient_country_code($sender);

        if ($filters['countries'] !== [] && !in_array($country, $filters['countries'], true)) {
            continue;
        }

        $day = (string) $row['day'];
        $total = (int) $row['total'];
        wa_client_bucket($buckets, $day, $country);

        $buckets[$day][$country]['messages_in'] += $total;
        if ((int) $row['delivered'] === 1) {
            $buckets[$day][$country]['messages_in_success'] += $total;
        }
    }
}

/**
 * Adds one account's outgoing totals, the Marketing/Service split and
 * cost_out, attributing each message to a country and pricing market via
 * the recipient's phone number - the same rules wa_accumulate_out_stats()
 * uses for the daily job. Cost is charged per successfully delivered
 * message at its category's rate as of that message's own day.
 */
function wa_accumulate_client_out_stats(array &$buckets, string $table, array $filters, string $rangeStart, string $rangeEndExclusive, array $rates): void
{
    $errorExpression = wa_error_expression($table)['expression'];

    $where = wa_client_range_where($rangeStart, $rangeEndExclusive)
        . wa_client_status_where($filters, $errorExpression);

    $recipient = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`payload`, '\$.recipient_phonenumber')), JSON_UNQUOTE(JSON_EXTRACT(`payload`, '\$.to')))";

    $rows = sqlSelectRows(
        $table,
        'DATE(`incoming_date`) AS `day`, `message_type` AS `message_type`, `delivered` AS `delivered`, '
            . $errorExpression . ' AS `is_error`, ' . $recipient . ' AS `recipient`, COUNT(*) AS `total`',
        $where,
        '',
        '',
        '`day`, `message_type`, `delivered`, `is_error`, `recipient`'
    );

    foreach ($rows as $row) {
        $category = wa_out_message_category((string) ($row['message_type'] ?? ''));
        if ($filters['message_type'] !== 'all' && $filters['message_type'] !== $category) {
            continue;
        }

        $recipientPhone = (string) ($row['recipient'] ?? '');
        $geo = $recipientPhone === '' ? ['country' => 'XX', 'market' => 'Other'] : wa_recipient_geo($recipientPhone);

        if ($filters['countries'] !== [] && !in_array($geo['country'], $filters['countries'], true)) {
            continue;
        }

        $day = (string) $row['day'];
        $country = $geo['country'];
        $total = (int) $row['total'];
        wa_client_bucket($buckets, $day, $country);

        $buckets[$day][$country]['messages_out'] += $total;

        if ($category === 'marketing') {
            $buckets[$day][$country]['total_marketing_messages'] += $total;
        } else {
            $buckets[$day][$country]['total_service_messages'] += $total;
        }

        if ((int) $row['is_error'] === 1) {
            $buckets[$day][$country]['messages_out_error'] += $total;
        }

        if ((int) $row['delivered'] === 1) {
            $buckets[$day][$country]['messages_out_success'] += $total;
            $rate = wa_pricing_rate_for($rates, $geo['market'], $category, $day);
            if ($rate !== null) {
                $buckets[$day][$country]['cost_out'] += $rate * $total;
            }
        }
    }
}

/**
 * Loads the pricing rates when the table exists. A deployment where the
 * daily cron has never run yet has no `whatsapp_pricing_rates` table; that
 * must degrade to "costs are 0" rather than blow up a read-only endpoint,
 * so this never creates or seeds anything.
 */
function wa_client_pricing_rates(string $dbName): array
{
    return sqlTableExist('whatsapp_pricing_rates', $dbName) ? wa_load_pricing_rates() : [];
}

/**
 * Computes per-(day, country_code) stats for each of $numbers, restricted
 * to that account's own in_/out_ tables.
 *
 * $numbers must already be validated as bare digit strings - they land in
 * a table identifier, which cannot be escaped or bound.
 *
 * $filters: date_from, date_to ('Y-m-d'), direction (in|out|both), status
 * (all|delivered|undelivered), errors_only (bool), countries (list of
 * ISO 3166-1 alpha-2), message_type (all|marketing|service).
 *
 * @param list<string> $numbers
 * @return array{clients: list<array>, totals: array, unknown_numbers: list<string>, error_basis: array<string, string>, pricing_rates_available: bool}
 */
function wa_collect_client_message_stats(array $numbers, array $filters): array
{
    $dbName = ms_secrets['db']['name'];
    $rates = wa_client_pricing_rates($dbName);

    $rangeStart = $filters['date_from'] . ' 00:00:00';
    $rangeEndExclusive = (new DateTimeImmutable($filters['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    // A message_type filter only means something for outgoing traffic -
    // incoming messages are never templates - so asking for one drops the
    // incoming side rather than silently counting it unfiltered.
    $wantsIn = in_array($filters['direction'], ['in', 'both'], true) && $filters['message_type'] === 'all';
    $wantsOut = in_array($filters['direction'], ['out', 'both'], true);

    $clients = [];
    $unknownNumbers = [];
    $errorBasis = [];
    $overall = wa_empty_client_stats();

    foreach ($numbers as $number) {
        $inTable = 'in_' . $number;
        $outTable = 'out_' . $number;

        $hasIn = $wantsIn && sqlTableExist($inTable, $dbName);
        $hasOut = $wantsOut && sqlTableExist($outTable, $dbName);

        if (!$hasIn && !$hasOut) {
            $unknownNumbers[] = $number;
            continue;
        }

        $buckets = [];

        if ($hasIn) {
            wa_accumulate_client_in_stats($buckets, $inTable, $filters, $rangeStart, $rangeEndExclusive);
        }

        if ($hasOut) {
            $errorBasis[$outTable] = wa_error_expression($outTable)['basis'];
            wa_accumulate_client_out_stats($buckets, $outTable, $filters, $rangeStart, $rangeEndExclusive, $rates);
        }

        ksort($buckets);

        $days = [];
        $clientTotals = wa_empty_client_stats();

        foreach ($buckets as $day => $countries) {
            ksort($countries);

            foreach ($countries as $country => $stats) {
                $days[] = array_merge(
                    ['day' => (string) $day, 'country_code' => (string) $country],
                    wa_format_client_stats($stats)
                );

                foreach ($stats as $key => $value) {
                    $clientTotals[$key] += $value;
                    $overall[$key] += $value;
                }
            }
        }

        $clients[] = [
            'number' => $number,
            'tables' => [
                'in' => $hasIn ? $inTable : null,
                'out' => $hasOut ? $outTable : null,
            ],
            'totals' => wa_format_client_stats($clientTotals),
            'days' => $days,
        ];
    }

    return [
        'clients' => $clients,
        'totals' => wa_format_client_stats($overall),
        'unknown_numbers' => $unknownNumbers,
        'error_basis' => $errorBasis,
        'pricing_rates_available' => $rates !== [],
    ];
}
