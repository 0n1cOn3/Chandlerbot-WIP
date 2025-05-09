<?php

declare(strict_types=1);

// Enable detailed error reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Displays the forward status and adjusts UI accordingly.
 * 
 * @return void
 */
function fwdStatus(): void
{
    // Load configuration
    loadPandabotConfig("../conf/chandlerbot.conf");

    // Attach shared memory
    $shm_key = fileinode("../bin/chandlerbot.php");
    $shm = shm_attach($shm_key);

    // Retrieve shared memory variable
    $out = shm_get_var($shm, 3);

    if (is_array($out)) {
        // Process status based on the value of $out[0]
        switch ($out[0]) {
            case "0":
                displayStatus($out[1], $out[2], 'lime', 'white', true);
                break;

            case "1":
                displayStatus($out[1], $out[2], 'red', 'white', false);
                break;
        }
    }
}

/**
 * Renders the forward status and applies UI changes based on the status.
 * 
 * @param string $statusLabel The status label.
 * @param string $statusMessage The status message.
 * @param string $labelColor The color of the status label.
 * @param string $messageColor The color of the status message.
 * @param bool $fadeIn Whether to fade in or fade out the view container.
 * 
 * @return void
 */
function displayStatus(string $statusLabel, string $statusMessage, string $labelColor, string $messageColor, bool $fadeIn): void
{
    // Display the status message
    echo "<b><p style='color:{$labelColor}; display: inline;'>{$statusLabel}: </p></b>";
    echo "<b><p style='color:{$messageColor}; display: inline;'>{$statusMessage}</p></b>";

    // JavaScript for fading in/out the view container
    $fadeOpacity = $fadeIn ? 1 : 0.1;
    echo "<script>
        if ($('.viewcontainer').css('opacity') == " . ($fadeIn ? '0.1' : '1') . ") {
            $('.viewcontainer').fadeTo(1000, {$fadeOpacity});
        }
    </script>";
}
?>
