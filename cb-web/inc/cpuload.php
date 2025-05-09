<?php

declare(strict_types=1);

// Enable detailed error reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Retrieves and displays the current CPU load.
 *
 * @return void
 */
function getCpuLoad(): void
{
    // Get CPU load from /proc/loadavg
    $load = fetchCpuLoad();

    if ($load !== null) {
        echo "<b><p style='color:yellow; display: inline;'>load:</p><p style='color:white; display: inline;'>  $load</p></b>";
    } else {
        echo "<b><p style='color:red;'>Error retrieving CPU load</p></b>";
    }
}

/**
 * Fetches the current CPU load from the system.
 *
 * @return string|null The CPU load, or null if an error occurs.
 */
function fetchCpuLoad(): ?string
{
    // Execute the command and capture the output
    exec("cat /proc/loadavg | awk '{print $1}'", $output, $status);

    // Check for successful execution and return the CPU load
    if ($status === 0 && isset($output[0])) {
        return $output[0];
    }

    // Return null if the command failed
    return null;
}
?>
