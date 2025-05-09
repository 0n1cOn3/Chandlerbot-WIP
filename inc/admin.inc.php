<?php

// Utility function to safely handle message and prevent duplication
function updateGlobalSettings($key, $infoarray) {
    global $globalsettings;
    $commandskey = $infoarray['channelid'] . "-" . $infoarray['msgid'];
    $commandData = [
        "msgid" => $infoarray['msgid'],
        "message" => htmlspecialchars($infoarray['message']),
        "channelname" => htmlspecialchars($infoarray['channelname']),
        "channelid" => $infoarray['channelid'],
        "username" => htmlspecialchars($infoarray['username']),
        "userid" => $infoarray['userid'],
        "msgdate" => $infoarray['msgdate']
    ];

    $globalsettings["bot"]["lastbotcommands"]["commands"][$commandskey] = $commandData;

    // Move the new command to the front of the list
    $globalsettings["bot"]["lastbotcommands"]["commands"] = [$commandskey => $commandData] + $globalsettings["bot"]["lastbotcommands"]["commands"];

    // Restrict array length
    $globalsettings["bot"]["lastbotcommands"]["commands"] = array_slice($globalsettings["bot"]["lastbotcommands"]["commands"], 0, $globalsettings["bot"]["lastbotcommands"]["count"]);
}

// Function to show last bot commands
function showLastBotCommands($arguments) {
    global $globalsettings;
    $output = "list last <b>" . $globalsettings["bot"]["lastbotcommands"]["count"] . "</b> bot commands:<br>";

    $help = false;
    if (array_key_exists(1, $arguments)) {
        $setchannel = handleCommandArguments($arguments, $output, $help);
    }

    if (isset($globalsettings["bot"]["lastbotcommands"]["commands"])) {
        $output .= listCommands($globalsettings["bot"]["lastbotcommands"]["commands"], $setchannel, $output);
    } else {
        $output .= $help ? '' : "no bot commands used<br>";
    }

    sendmessageintochannel("chandlerbot owner", $output, -1);
}

// Function to handle command arguments
function handleCommandArguments($arguments, &$output, &$help) {
    global $globalsettings;

    switch ($arguments[1]) {
        case "help":
            $output .= "<code>lastbotcommands</code>(list over all channels)<br><code>lastbotcommands</code> " . htmlspecialchars("<channelid>") . " (show only specific channel)<br>";
            $help = true;
            break;
        default:
            if (!array_key_exists($arguments[1], $globalsettings["bot"]["channelinfo"])) {
                $output .= "channelid: <b>" . $arguments[1] . "</b> does not exist!<br>try: <code>channelstatus</code><br><br><code>lastbotcommands</code><br>";
                $help = true;
            } else {
                return $arguments[1];
            }
    }
    return null;
}

// Function to list commands for the specified channel
function listCommands($commands, $setchannel, $output) {
    $outputcounter = 0;
    foreach ($commands as $command) {
        if (!$setchannel || $setchannel == $command["channelid"]) {
            $outputcounter++;
            $output .= "channel: <b><a href='https://t.me/c/" . $command["channelid"] . "'>" . $command["channelname"] . "</a></b>, user: <b>@" . $command["username"] . "</b><br>";
            $output .= "<b>command</b> (" . date('m/d/Y H:i:s', $command["msgdate"]) . "): <b><code>" . $command["message"] . "</code></b><br>--<br>";
        }
    }
    if ($outputcounter == 0) {
        $output .= "no bot commands used<br>";
    }
    return $output;
}

// Function to handle channel details
function channelDetail($channel) {
    global $globalsettings, $channelInfoNew;
    
    $channelInfoKey = $channel[1];
    if (!array_key_exists($channelInfoKey, $channelInfoNew)) {
        sendMessageToOwner("channelid: <b>" . $channelInfoKey . "</b> does not exist!");
        return;
    }
    
    $output = generateChannelDetails($channelInfoKey, $channelInfoNew[$channelInfoKey]);
    sendMessageToOwner($output);
}

// Function to generate channel details output
function generateChannelDetails($channelInfoKey, $channelData) {
    $output = "<b>name:</b> " . htmlspecialchars($channelData["name"]) . "<br><br>";
    $output .= "<b>channelbotname:</b> " . htmlspecialchars($channelData["channelbotname"]) . "<br>";
    $output .= "<b>mode:</b> " . getModeText($channelData["mode"]) . "<br>";
    $output .= "<b>typemapping:</b> " . $channelData["typemapping"] . "<br>";
    $output .= "<b>autosync:</b> " . getAutoSyncText($channelData["autosync"]) . "<br>";
    $output .= "<b>disabled:</b> " . getDisabledText($channelData["disabled"]) . "<br>";
    $output .= "<b>to:</b><br>" . generateToChannelsList($channelData["to"]);
    return $output;
}

// Helper functions for channel details
function getModeText($mode) {
    $modes = ["a" => "admin", "i" => "interactive", "ir" => "interactive+request", "r" => "read only"];
    return $modes[$mode] ?? "unknown";
}

function getAutoSyncText($autosync) {
    $syncModes = [-1 => "false", 1 => "true", 2 => "force"];
    return $syncModes[$autosync] ?? "unknown";
}

function getDisabledText($disabled) {
    $disabledModes = [-1 => "false", 1 => "true", 2 => "updatedb"];
    return $disabledModes[$disabled] ?? "unknown";
}

function generateToChannelsList($toChannels) {
    if (array_key_exists("-1", $toChannels)) {
        return "no target channels configured<br>";
    }
    $output = "";
    foreach ($toChannels as $t) {
        $output .= " - " . $t["name"] . "<br>";
    }
    return $output;
}

// Utility function to send message to the owner
function sendMessageToOwner($message) {
    sendmessageintochannel("chandlerbot owner", $message, -1);
}

// Function to check if config key value is valid
function ifConfigKeyValueValid($key, $value) {
    global $globalsettings;
    $validValues = getValidValuesForKey($key);
    return in_array($value, $validValues) ? $value : false;
}

// Helper function to get valid values for config keys
function getValidValuesForKey($key) {
    global $globalsettings;
    $validValues = [];
    switch ($key) {
        case "mode":
            $validValues = $globalsettings["bot"]["channelmode"];
            break;
        case "autosync":
            $validValues = $globalsettings["bot"]["autosyncmode"];
            break;
        case "disabled":
            $validValues = $globalsettings["bot"]["disabledmode"];
            break;
        case "channelbotname":
            $validValues = ["valid_name"];  // Specify valid bot names if any
            break;
    }
    return $validValues;
}

// Function to set channel configurations
function setChannelConfig($arguments) {
    global $globalsettings, $channelInfoNew;
    
    if (count($arguments) == 1) {
        $output = generateSetChannelUsage($globalsettings["admin"]["setchannel"]);
        sendMessageToOwner($output);
        return;
    }

    if (isset($arguments[1], $channelInfoNew[$arguments[1]])) {
        $setKey = $arguments[2] ?? null;
        if (isValidConfigKey($setKey, $globalsettings["admin"]["setchannel"])) {
            // Process config value setting...
        } else {
            sendMessageToOwner("Invalid option key.");
        }
    } else {
        sendMessageToOwner("Invalid channel ID.");
    }
}

// Helper function to validate the config key
function isValidConfigKey($setKey, $validKeys) {
    return in_array($setKey, $validKeys);
}

// Function to clear the queue
function clearQueue($arguments) {
    global $globalsettings;
    
    $sql = "SELECT id FROM forwardqueue";
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
    $numRows = pg_num_rows($result);
    
    if (isset($arguments[1]) && $arguments[1] == "yes") {
        pg_query($globalsettings["db"]["pg_conn"], "DELETE FROM forwardqueue");
        sendMessageToOwner("DB queue (" . $numRows . ") is cleared.");
    } else {
        sendMessageToOwner("Please confirm with 'yes' to clear the queue.");
    }
}

?>
