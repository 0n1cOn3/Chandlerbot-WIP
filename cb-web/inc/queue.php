<?php

declare(strict_types=1);

// Enable error reporting for runtime diagnostics
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Displays current runtime statistics for the PandaBot forward queue.
 *
 * Retrieves shared memory data for active forwards, queue status,
 * and runtime since bot start.
 *
 * @return void
 */
function displayForwardQueueStatus(): void
{
    // Load configuration
    loadPandabotConfig(__DIR__ . '/../conf/chandlerbot.conf');

    // Generate a shared memory key based on the chandlerbot script file inode
    $shmKey = fileinode(__DIR__ . '/../bin/chandlerbot.php');

    if ($shmKey === false) {
        echo "<b style='color:red;'>Error: Unable to generate shared memory key.</b>";
        return;
    }

    // Attach to shared memory segment
    $shm = shm_attach($shmKey, 20480);
    if ($shm === false) {
        echo "<b style='color:red;'>Error: Unable to attach to shared memory.</b>";
        return;
    }

    // Retrieve shared memory variables
    $activeForwards = shm_get_var($shm, 1);
    $queueSize = shm_get_var($shm, 2);
    $botStartTime = shm_get_var($shm, 5);

    // Compute bot runtime
    $runtime = dhms(time() - $botStartTime);

    // Output formatted status information
    echo "<b>
            <p style='color:yellow; display:inline;' title='runtime: {$runtime}'>runt. forwards: </p>
            <p style='color:white; display:inline;'>{$activeForwards}&nbsp;&nbsp;</p>
          </b>";
    echo "<b>
            <p style='color:yellow; display:inline;'>queue: </p>
            <p style='color:white; display:inline;'>{$queueSize}</p>
          </b>";

    // Optional cleanup (commented)
    // shm_remove($shm);
    // sem_remove(sem_get($shmKey));
}

// Usage example
displayForwardQueueStatus();
