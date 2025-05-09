<?php

/**
 * Download media file from a given link and send messages accordingly.
 * 
 * @param string $link The URL of the media to be downloaded.
 * @param string|null $option Optional parameter to define reupload behavior.
 * @return void
 */
function download(string $link, ?string $option = null): void {
    global $channelInfoNew, $globalsettings;

    // Extract channel ID and message ID from the URL.
    $channel = explode("/", $link)[4];
    $peerChannel = "-100" . $channel;

    // Ensure the URL contains a valid message ID.
    if (count(explode("/", $link)) < 6) {
        echo "Invalid link format.\n";
        return;
    }

    $msgId = explode("/", $link)[5];
    $fileOut = $globalsettings["bot"]["downloaddirectory"] . "/";

    // Check if the bot is part of the specified channel.
    if (asearch($peerChannel, $globalsettings["channels"]["channeldb"])) {
        // Retrieve the message containing the media.
        $message = made("channels", "getMessages", [
            "channel" => $peerChannel,
            "id" => [$msgId]
        ]);
        
        $downloadMedia = $message['messages'][0]['media'];
        $fileName = getMessageMediaAttributes($message['messages'][0]["media"]["document"]["attributes"])["filename"];
        $fileOut .= $fileName;

        // Notify the bot owner about the download attempt.
        made("messages", "sendMessage", [
            "peer" => $globalsettings["bot"]["chandlerownerid"],
            "message" => "- Trying to download: " . htmlspecialchars($fileName) . "<br>",
            "parse_mode" => "html"
        ]);

        // If file already exists, notify the bot owner and exit.
        if (file_exists($fileOut)) {
            made("messages", "sendMessage", [
                "peer" => $globalsettings["bot"]["chandlerownerid"],
                "message" => "❌ File: " . htmlspecialchars($fileName) . " already exists<br>",
                "parse_mode" => "html"
            ]);
            return;
        }

        // Proceed with downloading the media file.
        try {
            $downloadReturn = made("", "downloadToFile", [
                "downloadmedia" => $downloadMedia,
                "fileout" => $fileOut
            ]);

            // Notify the bot owner that the download is complete.
            made("messages", "sendMessage", [
                "peer" => $globalsettings["bot"]["chandlerownerid"],
                "message" => "- Download completed: " . htmlspecialchars($fileName) . "<br>",
                "parse_mode" => "html"
            ]);
        } catch (\Exception $e) {
            // Handle any exceptions that occur during the download process.
            echo "Error downloading file: " . $e->getMessage() . "\n";
            made("messages", "sendMessage", [
                "peer" => $globalsettings["bot"]["chandlerownerid"],
                "message" => "❌ Error downloading file: " . htmlspecialchars($fileName) . "<br>",
                "parse_mode" => "html"
            ]);
        }
    } else {
        // Notify if the bot is not in the specified channel.
        $sendMessage = "You are not a member of the channel.";

        echo $sendMessage . "\n";
        made("messages", "sendMessage", [
            "peer" => $globalsettings["bot"]["chandlerownerid"],
            "message" => $sendMessage,
            "parse_mode" => "html"
        ]);
    }
}
