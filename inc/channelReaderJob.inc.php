<?php

function getUpdatedDbChannel($channelInfoNew) {
    foreach (array_keys($channelInfoNew) as $channelInfoKey) {
        if ($channelInfoNew[$channelInfoKey]["status"] == "ok" && $channelInfoNew[$channelInfoKey]["disabled"] == 2) {
            return $channelInfoKey;
        }
    }
    return false;
}

function insertChannelToBckWorker($channelInfoNew, $channelInfoKey, $globalsettings) {
    $insertlastrun = time() - (97 * 60 * 60);  // Adjust last run time
    $sql = "INSERT INTO bckworker VALUES (
        " . $channelInfoNew[$channelInfoKey]["chanid"] . ",
        '" . pg_escape_string($globalsettings["db"]["pg_conn"], $channelInfoNew[$channelInfoKey]["name"]) . "',
        " . $insertlastrun . ",
        96
    ) ON CONFLICT (grpid) DO NOTHING;";
    
    return pg_query($globalsettings["db"]["pg_conn"], $sql);
}

function fetchBckWorkerTimes($channelInfoNew, $channelInfoKey, $globalsettings) {
    $sql = "SELECT lastrun, runafter FROM bckworker WHERE grpid=" . $channelInfoNew[$channelInfoKey]["chanid"];
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);

    if (!$result) {
        logger("Error fetching bckworker times.");
        return null;
    }

    $row = pg_fetch_array($result);
    return [
        'lastrun' => $row['lastrun'],
        'runafter' => $row['runafter']
    ];
}

function checkIfLockTableExists($channelInfoNew, $channelInfoKey, $globalsettings) {
    $sql = "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = 'lock_" . $channelInfoNew[$channelInfoKey]["chanid"] . "';";
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
    
    return pg_num_rows($result) != 0;
}

function getMaxIdFromLockTable($channelInfoNew, $channelInfoKey, $globalsettings) {
    $sql = "SELECT max(msgid) AS maxid FROM lock_" . $channelInfoNew[$channelInfoKey]["chanid"] . ";";
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);

    if (!$result) {
        logger("Error fetching max msgid from lock table.");
        return null;
    }

    $row = pg_fetch_array($result);
    return $row["maxid"];
}

function setupStepperForChannel($channelInfoNew, $channelInfoKey, $maxid) {
    $top_message = made("messages", "getPeerDialogs", ["peers" => [$channelInfoNew[$channelInfoKey]["peerid"]]])["dialogs"][0]["top_message"];
    logger("job: chnStepper - setup stepper for [" . $channelInfoNew[$channelInfoKey]["name"] . "] from " . $maxid . " to " . $top_message);

    $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["limit"] = 100;
    $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["from"] = $maxid;
    $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["to"] = $top_message;
    $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["maxid"] = $top_message;  // Obsolet during top_message
}

function createChannelTables($channelInfoNew, $channelInfoKey, $globalsettings) {
    pg_query($globalsettings["db"]["pg_conn"], pg_channelTableTemplate("chan_" . $channelInfoNew[$channelInfoKey]["chanid"]));  // Create chan table
    pg_query($globalsettings["db"]["pg_conn"], pg_channelTableTemplate("lock_" . $channelInfoNew[$channelInfoKey]["chanid"])); // Create lock table
}

function startJobForChannel($channelInfoNew, $channelInfoKey) {
    global $isJobRunning;
    $isJobRunning["backgroundWorker"]["isrunning"] = 1;
    $isJobRunning["backgroundWorker"]["chanid"] = $channelInfoNew[$channelInfoKey]["chanid"];
    $isJobRunning["backgroundWorker"]["name"] = $channelInfoNew[$channelInfoKey]["name"];
    $isJobRunning["backgroundWorker"]["starttime"] = time();
    $isJobRunning["backgroundWorker"]["channelInfoKey"] = $channelInfoKey;

    logger("job: channelReader - start on [" . $channelInfoNew[$channelInfoKey]["name"] . "]");
}

function channelReaderJob() {
    global $globalsettings, $channelInfoNew, $isJobRunning, $bckStepArray;

    if (!array_key_exists("backgroundWorker", $isJobRunning)) {
        $isJobRunning["backgroundWorker"]["isrunning"] = -1;
    }

    if ($isJobRunning["backgroundWorker"]["isrunning"] == -1) {
        foreach (array_keys($channelInfoNew) as $channelInfoKey) {
            if ($channelInfoNew[$channelInfoKey]["status"] == "ok" && $channelInfoNew[$channelInfoKey]["mode"] != "a") {

                $updatedb = getUpdatedDbChannel($channelInfoNew);
                if ($updatedb !== false) $channelInfoKey = $updatedb; // Overwrite channelInfoKey if found disabled: updatedb
                
                if (!insertChannelToBckWorker($channelInfoNew, $channelInfoKey, $globalsettings)) {
                    logger("Error inserting channel to bckworker.");
                    continue;
                }

                $workerTimes = fetchBckWorkerTimes($channelInfoNew, $channelInfoKey, $globalsettings);
                if ($workerTimes === null) {
                    continue;
                }

                $lastrun = $workerTimes['lastrun'];
                $runafter = $workerTimes['runafter'];
                $runafterInSeconds = $runafter * 60 * 60;
                $runNow = $lastrun + $runafterInSeconds;

                if ($updatedb !== false) {
                    $runNow = -1; // Force updatedb
                }

                if (time() > $runNow) {
                    if (checkIfLockTableExists($channelInfoNew, $channelInfoKey, $globalsettings)) {
                        $maxid = getMaxIdFromLockTable($channelInfoNew, $channelInfoKey, $globalsettings);
                        if ($maxid !== null) {
                            setupStepperForChannel($channelInfoNew, $channelInfoKey, $maxid);
                        }

                        createChannelTables($channelInfoNew, $channelInfoKey, $globalsettings);
                        startJobForChannel($channelInfoNew, $channelInfoKey);

                        break;
                    }
                }
            }
        }
    } else {
        $channelInfoKey = $isJobRunning["backgroundWorker"]["channelInfoKey"];
        $stepsleft = round(($bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["to"] - $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["from"]) / $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]['limit']);

        logger("job: channelReader - work on [" . $channelInfoNew[$channelInfoKey]["name"] . "], current msg: " . $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["from"] . " to goal: " . $bckStepArray[$channelInfoNew[$channelInfoKey]["peerid"]]["to"]);
    }
    return;
}
