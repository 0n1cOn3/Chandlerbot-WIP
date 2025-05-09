<?php

declare(strict_types=1);

// Enable full error reporting for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Outputs the last N lines of a log file, similar to the Unix `tail` command.
 */
function taillog(string $logFilePath = '../log.chandlerbot.log', int $lastLines = 50): void
{
    if (!file_exists($logFilePath)) {
        echo '<b>Log file does not exist. Is the bot running?</b>';
        exit;
    }

    if (filesize($logFilePath) <= 5048) {
        echo '<b>Log file loading...</b>';
        exit;
    }

    $file = fopen($logFilePath, 'r');
    if (!$file) {
        echo '<b>Failed to open log file.</b>';
        exit;
    }

    $position = -1;
    $lineBuffer = '';
    $lines = [];
    $lineCount = 0;

    while ($lineCount < $lastLines && fseek($file, $position, SEEK_END) === 0) {
        $char = fgetc($file);
        if ($char === false) {
            break;
        }

        $lineBuffer = $char . $lineBuffer;
        $position--;

        if ($char === "\n") {
            $lines[] = $lineBuffer;
            $lineBuffer = '';
            $lineCount++;
        }
    }

    if (fseek($file, $position, SEEK_END) !== 0) {
        echo '<b>Failed to seek in log file.</b>';
        fclose($file);
        exit;
    }

    fclose($file);

    foreach (array_reverse($lines) as $line) {
        echo htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Execute function
taillog();
