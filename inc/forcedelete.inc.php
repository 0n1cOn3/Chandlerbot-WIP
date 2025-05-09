<?php

function forcedelete($infoarray) {
    global $channelInfoNew;
    
    // Early return to stop function execution for all cases
    return;

    $channel = "1234567890"; // Target channel ID
    $userids = ["123412341", "2345234523"]; // Users: Hans and Peter

    // Check if the provided channel ID matches the target channel
    if ($infoarray["channelid"] === $channel) {
        // Ensure the message is valid for deletion
        if (cacheasks($infoarray) !== -1) {
            echo "--------------> @ channel ---> \n";
            echo "msgid: " . $infoarray["msgid"] . "\n";

            // Prepare the message for deletion notification
            $message = "delete: id: " . $infoarray["msgid"] . "<br>" . $infoarray["message"] . "<br>";

            // Send a message to the channel notifying about the deletion
            made("messages", "sendMessage", [
                "peer" => $channelInfoNew[$channel]["peerid"],
                "message" => $message,
                "parse_mode" => "html"
            ]);

            // Delete the message from the channel
            made("channels", "deleteMessages", [
                "channel" => $channelInfoNew[$channel]["peerid"],
                "id" => [$infoarray["msgid"]]
            ]);
        }
    }
} // End of function
?>
