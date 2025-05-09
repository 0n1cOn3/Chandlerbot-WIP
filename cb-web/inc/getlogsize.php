<?php

declare(strict_types=1);

// Enable detailed error reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Retrieves and formats the size of the log file.
 * 
 * @return void
 */
function getLogSize(): void
{
    $logfile = "../log.chandlerbot.log";

    // Check if file exists
    if (!file_exists($logfile)) {
        echo "<b style='color:red;'>Error: Log file does not exist.</b>";
        return;
    }

    // Get formatted file size
    $formattedSize = filesize_formatted($logfile);

    // Output the log size in a styled format
    echo "<b><p style='color:yellow; display: inline;'>logsize:</p>
              <p style='color:white; display: inline;'>  {$formattedSize}</p></b>";
}

/**
 * Formats the size of the file in a human-readable format (e.g., KB, MB).
 * 
 * @param string $file The path to the file.
 * @return string The formatted file size.
 */
function filesize_formatted(string $file): string
{
    $bytes = filesize($file);

    if ($bytes === false) {
        return "Error: Unable to determine file size.";
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $power = floor(($bytes ? log($bytes) : 0) / log(1024));
    $bytes /= pow(1024, $power);

    return sprintf("%.2f %s", $bytes, $units[$power]);
}

?>
