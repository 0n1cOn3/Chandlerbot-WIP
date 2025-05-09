<?php

// Utility function for matching file extensions
function getMatchingFileExtension($text) {
    $extensions = [
        '/.mp4$/u', '/.m4a$/u', '/.ac3$/u', '/.jpg$/u', '/.mp3$/u', '/.m4v$/u',
        '/.avi$/u', '/.divx$/u', '/.mov$/u', '/.ts$/u', '/.mpg$/u', '/.mpeg$/u',
        '/.mkv$/u', '/.nofile$/u'
    ];

    foreach ($extensions as $end) {
        if (preg_match($end, $text)) {
            return true;
        }
    }
    return false;
}

// Function to clean text
function removeChannelAds($text, $type = null) {
    $ext = explode(".", $text);
    $extLength = count($ext) - 1;
    $fileExt = getMatchingFileExtension($text);

    // Clean up special characters, retain valid ones
    $text = preg_replace("/[^a-zA-Z0-9@#ßÄÜÖöüäéíîáâê()'.,\!?]+/u", " ", $text);

    if ($fileExt) {
        $extensions = [
            '/.mp4$/u', '/.m4a$/u', '/.ac3$/u', '/.jpg$/u', '/.mp3$/u', '/.m4v$/u',
            '/.avi$/u', '/.divx$/u', '/.mov$/u', '/.ts$/u', '/.mpg$/u', '/.mpeg$/u',
            '/.mkv$/u', '/.nofile$/u'
        ];
        $text = preg_replace($extensions, "", $text);
    }

    // Filter out unwanted hashtags and mentions
    $text = preg_replace('/[@|#][^\s\/.,|]+/u', '', $text);

    // Clean up the resulting text
    $text = trim($text);

    if ($type == "filename" && $fileExt) {
        $text .= "." . $ext[$extLength];
    }

    return $text;
}

// Function to remove emojis and special characters
function cleanupString($input) {
    $input = mb_convert_encoding($input, "UTF-8", mb_detect_encoding($input)); // force utf8 encoding
    $input = preg_replace('/([0-9|#][\x{20E3}])|[\x{00ae}|\x{00a9}|\x{203C}|\x{2047}|\x{2048}|\x{2049}|\x{3030}|\x{303D}|\x{2139}|\x{2122}|\x{3297}|\x{3299}][\x{FE00}-\x{FEFF}]?|[\x{2190}-\x{21FF}][\x{FE00}-\x{FEFF}]?|[\x{2300}-\x{23FF}][\x{FE00}-\x{FEFF}]?|[\x{2460}-\x{24FF}][\x{FE00}-\x{FEFF}]?|[\x{25A0}-\x{25FF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{FE00}-\x{FEFF}]?|[\x{2600}-\x{27BF}][\x{1F000}-\x{1FEFF}]?|[\x{2900}-\x{297F}][\x{FE00}-\x{FEFF}]?|[\x{2B00}-\x{2BF0}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F9FF}][\x{FE00}-\x{FEFF}]?|[\x{1F000}-\x{1F9FF}][\x{1F000}-\x{1FEFF}]?/u', '', $input);
    $input = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $input); // remove binary - eq. newlines
    $input = mb_convert_encoding(iconv("UTF-8", "ISO-8859-1//IGNORE", $input), "UTF-8", "ISO-8859-1"); // could still be useful
    return trim($input);
}

// Function to extract media attributes
function getMessageMediaAttributes($data) {
    $attributes = ["duration" => -1, "width" => -1, "height" => -1, "filename" => -1];
    
    foreach ($data as $d) {
        foreach ($d as $key => $value) {
            if (!is_array($value)) {
                switch ($key) {
                    case "duration": $attributes["duration"] = $value; break;
                    case "w": $attributes["width"] = $value; break;
                    case "h": $attributes["height"] = $value; break;
                    case "file_name": $attributes["filename"] = $value; break;
                }
            }
        }
    }
    return $attributes;
}

// Function to extract photo media attributes
function getMessageMediaPhotoAttributes($data) {
    $attributes = ["mime_type" => -1, "width" => -1, "height" => -1, "size" => -1];
    $readAttr = false;

    foreach ($data as $d) {
        if ($d["_"] == "photoSize") {
            $readAttr = true;
        }
        foreach ($d as $key => $value) {
            if (!is_array($value) && $readAttr) {
                switch ($key) {
                    case "type": $attributes["mime_type"] = $value; break;
                    case "w": $attributes["width"] = $value; break;
                    case "h": $attributes["height"] = $value; break;
                    case "size": $attributes["size"] = $value; break;
                }
            }
        }
    }

    return $attributes;
}

// Function to get message data
function getMessageData($messagedata, $messagetype) {
    $messagereturn = [
        "msgid" => -1, "link" => -1, "grouped_id" => -1, "type" => -1, "message" => -1, 
        "cleanmessage" => -1, "reply_to" => -1, "msgdate" => -1, "mime_type" => -1, 
        "duration" => -1, "width" => -1, "height" => -1, "size" => -1, "filedate" => -1, 
        "filename" => -1
    ];

    $channel_id = substr($messagedata["peer_id"], 4);
    $messagereturn["link"] = "https://t.me/c/" . $channel_id . "/" . $messagedata["id"];
    $messagereturn["msgid"] = $messagedata["id"];
    $messagereturn["type"] = $messagetype;
    $messagereturn["msgdate"] = $messagedata["date"];

    if ($messagetype == "messageMediaDocument") {
        $attributesdata = getMessageMediaAttributes($messagedata["media"]["document"]["attributes"]);
    }
    if ($messagetype == "messageMediaPhoto") {
        $attributesdata = getMessageMediaPhotoAttributes($messagedata["media"]["photo"]["sizes"]);
    }

    // Set grouped_id and reply_to
    $messagereturn["grouped_id"] = array_key_exists("grouped_id", $messagedata) ? $messagedata["grouped_id"] : -1;
    $messagereturn["reply_to"] = array_key_exists("reply_to", $messagedata) && array_key_exists("reply_to_msg_id", $messagedata["reply_to"]) ? $messagedata["reply_to"]["reply_to_msg_id"] : -1;

    // Set message and clean message
    $messagereturn["message"] = $messagedata["message"] ?: -1;
    $messagereturn["cleanmessage"] = $messagereturn["message"] != -1 ? removeChannelAds(cleanupString($messagedata["message"])) : -1;

    // Handle media-specific attributes
    switch ($messagetype) {
        case "messageMediaPhoto":
            $messagereturn["mime_type"] = $attributesdata["mime_type"];
            $messagereturn["width"] = $attributesdata["width"];
            $messagereturn["height"] = $attributesdata["height"];
            $messagereturn["size"] = $attributesdata["size"];
            $messagereturn["filedate"] = $messagedata["media"]["photo"]["date"];
            $messagereturn["filename"] = $messagedata["media"]["photo"]["id"] . "#" . $messagedata["media"]["photo"]["access_hash"];
            break;
        case "messageMediaDocument":
            $messagereturn["size"] = $messagedata["media"]["document"]["size"];
            $messagereturn["filedate"] = $messagedata["media"]["document"]["date"];
            $messagereturn["mime_type"] = $messagedata["media"]["document"]["mime_type"];
            $messagereturn["filename"] = $attributesdata["filename"];
            break;
    }

    // Return final data
    return $messagereturn;
}
