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
if (isset(ms_secrets['db']) && file_exists($v1_dir . '/general/db.php')) {
    include_once $v1_dir . '/general/db.php';
}
if (file_exists($v1_dir . '/general/custom_functions.php')) {
    include_once $v1_dir . '/general/custom_functions.php';
}
function collect_conversations_per_day(){
    /* 
    Collect conversations for the day
    insert them into the database table `daily_conversations_stats`
    if the table does not exist, create it first
    */
    echo "\ncollect_conversations_per_day\n";
}
collect_conversations_per_day();