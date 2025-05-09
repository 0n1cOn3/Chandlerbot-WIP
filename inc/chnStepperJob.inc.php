<?php

function chnStepperJob() {
    global $globalsettings, $channelInfoNew, $isJobRunning, $bckStepArray;

    // Check if backgroundWorker job is running
    if (!array_key_exists("backgroundWorker", $isJobRunning) || $isJobRunning["backgroundWorker"]["isrunning"] == -1) {
        return; // No job running
    }

    // Get the channelInfoKey for the current backgroundWorker job
    $channelInfoKey = $isJobRunning["backgroundWorker"]["channelInfoKey"];

    // Check if this channel is already being processed
    if (!array_key_exists($channelInfoNew[$channelInfoKey]["peerid"], $bckStepArray)) {
        // Initialize and setup for the first time processing of the channel
        setupChannelStepper($channelInfoKey, $bckStepArray);
    }

    // Begin message processing loop
    $from = $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["from"];
    while (true) {
        // Fetch message history from the channel
        $out = made("messages", "getHistory", [
            "peer" => $channelInfoNew[$channelInfoKey]["peerid"], 
            "offset_id" => $from, 
            "limit" => $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["limit"]
        ]);

        // Check if messages were found
        if (count($out['chats']) != 0) {
            // Update the 'from' value and exit the loop
            $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["from"] = $from;
            break;
        }

        // Increase the offset if no messages found
        $from = 100 + $from; 
        logger("job: chnStepper - no messages found " . $from . " - [" . $channelInfoNew[$channelInfoKey]["name"] . "]");
        sleep(1);
    }

    // Process messages if any exist
    if ($from != $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["to"]) {
        processMessages($channelInfoKey, $bckStepArray, $out, $from);
    } else {
        // No further processing, finalize the channel
        finalizeChannelProcessing($channelInfoKey);
    }

    return;
}

// Setup the channel stepper for the first time
function setupChannelStepper($channelInfoKey, &$bckStepArray) {
    global $globalsettings, $channelInfoNew;

    $channelId = $channelInfoNew[$channelInfoKey]["chanid"];
    $peerId = $channelInfoNew[$channelInfoKey]["peerid"];
    
    // Fetch max message ID from the database
    $sql = "SELECT MAX(msgid) AS maxid FROM chan_" . $channelId;
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
    $maxid = 0;
    if ($row = pg_fetch_array($result)) {
        $maxid = $row['maxid'];
    }

    // Get the top message ID from the channel
    $top_message = made("messages", "getPeerDialogs", ["peers" => [$peerId]])["dialogs"][0]["top_message"];
    logger("job: chnStepper - setup stepper for [" . $channelInfoNew[$channelInfoKey]["name"] . "] from 100 to " . $top_message);

    // Initialize stepper settings
    $bckStepArray[$peerId] = [
        "limit" => 100,
        "from" => 100,
        "to" => $top_message,
        "maxid" => $maxid
    ];
}

// Process the fetched messages
function processMessages($channelInfoKey, &$bckStepArray, $out, $from) {
    global $channelInfoNew;

    $peerId = $channelInfoNew[$channelInfoKey]["peerid"];
    // Update 'from' and calculate steps left
    $bckStepArray[$peerId]["from"] = $from;
    $stepsleft = round(($bckStepArray[$peerId]["to"] - $from) / $bckStepArray[$peerId]['limit']);

    // Parse and insert the fetched data
    insertData(parseData($out), $channelInfoKey, 1, 1); // $donotsync=1; $locktable=1;

    // Update the stepper state
    if ($from >= $bckStepArray[$peerId]["to"]) {
        $bckStepArray[$peerId]["from"] = $bckStepArray[$peerId]["to"];
    } else {
        $bckStepArray[$peerId]["from"] += $bckStepArray[$peerId]["limit"];
    }
}

// Finalize the channel after processing
function finalizeChannelProcessing($channelInfoKey) {
    global $globalsettings, $channelInfoNew, $isJobRunning, $bckStepArray;

    $channelId = $channelInfoNew[$channelInfoKey]["chanid"];
    $peerId = $channelInfoNew[$channelInfoKey]["peerid"];

    // Log completion of channel reading
    logger("job: chnStepper - channelReader done @ [" . $channelInfoNew[$channelInfoKey]["name"] . "]");

    // Clean up old data from the channel
    $sql = "DELETE FROM chan_" . $channelId . " t1 WHERE t1.msgid < " . $bckStepArray[$peerId]["maxid"] . " 
            AND NOT EXISTS (SELECT msgid FROM lock_" . $channelId . " t2 WHERE t1.msgid = t2.msgid AND t1.type = t2.type AND t1.size = t2.size)";
    pg_query($globalsettings["db"]["pg_conn"], $sql);

    // Insert new data into the channel
    $sql = "INSERT INTO chan_" . $channelId . " SELECT * FROM lock_" . $channelId . " ON CONFLICT (msgid) DO NOTHING;";
    pg_query($globalsettings["db"]["pg_conn"], $sql);

    // Drop the lock table
    dropTable("lock_" . $channelId);

    // Update the background worker's last run time
    $sql = "UPDATE bckworker SET lastrun=" . $isJobRunning["backgroundWorker"]["starttime"] . " WHERE grpid=" . $channelId;
    pg_query($globalsettings["db"]["pg_conn"], $sql);

    // Mark the background worker as not running
    $isJobRunning["backgroundWorker"]["isrunning"] = -1;

    // Check if the channel is disabled, and if so, reset it
    if ($channelInfoNew[$channelInfoKey]["disabled"] == 2) {
        $channelInfoNew[$channelInfoKey]["disabled"] = -1;
        $channelInfoNew[$channelInfoKey]["status"] = "ok";
        $channelInfoNew[$channelInfoKey]["info"] = "ready";
        writeChannelConfig($channelInfoNew);
        loadChannelConfig(true);
        writeChannelConfig($channelInfoNew);
    }
}

?>
