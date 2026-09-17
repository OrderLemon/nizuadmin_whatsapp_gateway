<?php
/**
 * Console command to run daily cron jobs
 */
$start_time = microtime(true);
require_once getcwd() . '/../config.php';
if (file_exists(config_path)) {
    $configContent = file_get_contents(config_path);
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
    echo "Configuration file not found at " . getcwd() . '/../config.php';
    exit(1);
}
if (file_exists(getcwd() . '/../vendor/autoload.php')) {
    include_once getcwd() . '/../vendor/autoload.php';
}
if (isset(ms_secrets['db']) && file_exists(getcwd() . '/../general/db.php')) {
    include_once getcwd() . '/../general/db.php';
}
if (file_exists(getcwd() . '/../general/custom_functions.php')) {
    include_once getcwd() . '/../general/custom_functions.php';
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