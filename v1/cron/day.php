<?php
/**
 * Console command to run daily cron jobs
 */
$start_time = microtime(true);
$v1_dir = dirname(__DIR__);
require_once $v1_dir . '/config.php';
$secrets_path = str_starts_with(config_path, '/') ? config_path : $v1_dir . '/' . config_path;
if (file_exists($secrets_path)) {
    $configContent = file_get_contents($secrets_path);
    $configData = json_decode($configContent, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        define("ms_secrets", $configData);
        define("ms_logserver_token", $configData['ms_logserver_token']);
        define("ms_server_token", $configData['ms_server_token']);
        define("ms_environment", $configData['env']);
    } else {
        echo "Invalid JSON in configuration file";
        exit(1);
    }
} else {
    define("ms_secrets", []);
    echo "Configuration file not found at " . $secrets_path;
    exit(1);
}
if (file_exists($v1_dir . '/vendor/autoload.php')) {
    include_once $v1_dir . '/vendor/autoload.php';
}
if (isset(ms_secrets['db']['host']) && file_exists($v1_dir . '/general/db.php')) {
    if (!function_exists('http_response')) {
        // db.php calls http_response() on connection failure; that function
        // only exists in the HTTP entry point (index.php), not in this cron
        // context, so provide a minimal fallback to avoid a fatal error.
        function http_response($code, $data)
        {
            echo json_encode($data) . "\n";
            exit($code >= 400 ? 1 : 0);
        }
    }
    include_once $v1_dir . '/general/db.php';
}
if (file_exists($v1_dir . '/general/custom_functions.php')) {
    include_once $v1_dir . '/general/custom_functions.php';
}
function collect_conversations_per_day(){
    /*
    Collect conversations for every day since Feb 2023, insert/update them
    into `daily_conversations_stats` (creating it and `whatsapp_pricing_rates`
    first if needed). Days with no messages are still upserted with 0s.
    */
    if (function_exists('wa_collect_daily_conversation_stats')) {
        wa_collect_daily_conversation_stats('2023-02-01', date('Y-m-d'));
    } else {
        echo "collect_conversations_per_day: database not configured, skipping\n";
    }
}
collect_conversations_per_day();