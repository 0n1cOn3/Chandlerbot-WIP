<?php

declare(strict_types=1);

// Enable detailed error reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Outputs the full log contents.
 *
 * @return void
 */
function fullLog(): void
{
    // Set the maximum execution time
    set_time_limit(600);

    // Read and output the log contents
    echo getLogContents("../log.chandlerbot.log");
}

/**
 * Retrieves the contents of a log file.
 *
 * @param string $logFile The path to the log file.
 * @return string The contents of the log file.
 */
function getLogContents(string $logFile): string
{
    if (file_exists($logFile) && is_readable($logFile)) {
        $contents = file_get_contents($logFile);
        if ($contents === false) {
            return 'Error reading the log file.';
        }
        return $contents;
    }

    // Return a message if the log file is not available or readable
    return 'Log file is not accessible or does not exist.';
}
?>
