<?php

$logDir = __DIR__ . '/../logs';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/php-error.log';

ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', $logFile);

error_reporting(E_ALL);

function writeLog($message)
{
    global $logFile;

    file_put_contents(
        $logFile,
        "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

set_error_handler(function ($severity, $message, $file, $line) {
    writeLog("ERROR [$severity] $message in $file on line $line");
    return false;
});

set_exception_handler(function ($exception) {
    writeLog(
        "EXCEPTION: " .
        $exception->getMessage() .
        " in " .
        $exception->getFile() .
        " on line " .
        $exception->getLine()
    );
});

register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null) {
        writeLog(
            "FATAL: {$error['message']} in {$error['file']} on line {$error['line']}"
        );
    }
});