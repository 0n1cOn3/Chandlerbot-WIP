<?php

// Helper function to count valid forwards
function realForwardCount() {
    global $toForward;
    $count = 0;
    foreach ($toForward as $forward) {
        $type = ($forward["album"] == -1) ? "video" : "album";
        if ($type === "video" && $forward["cover"] != -1) {
            $count++;
        }
        $count++;
    }
    return $count;
}

// Helper function for array search
function asearch($key, $array) {
    foreach ($array as $item) {
        if ($item == $key) {
            return true;
        }
    }
    return false;
}

// Main function for forwarding process
function fwdStepperJob() {
    global $floodwait, $MadelineProto, $channelInfoNew, $toForward, $syncPrintMsg, 
           $runtimeForwardCounter, $runtimeForwardCalls, $interval, $globalsettings, 
           $startTime, $floodchallange, $forwardstopmsg, $maxforwards, $floodtimer, 
           $alltimeforwards, $shm;

    $forward = [];

    // Reset flood wait state
    $floodwait = -1;

    if (count($toForward) !== 0) {
        $firstKey = array_key_first($toForward);
        $firstItem = $toForward[$firstKey];

        $fromTo = $firstItem["from"] . $firstItem["to"] . $firstItem["topic"];
        $from = $firstItem["from"];
        $to = $firstItem["to"];
        $toTopic = $firstItem["topic"];

        // Check if # exists in "to" field, exit if so
        $toTmp = explode("#", $to);
        if (isset($toTmp[1])) {
            $to = $toTmp[0];
            exit;
        }

        // Channel names
        $fromChannelName = $channelInfoNew[$from]["name"];
        $toChannelName = $firstItem["tochanname"];

        // Shorten channel names if too long
        $fromChannelName = mb_strlen($fromChannelName) >= 20 ? mb_substr($fromChannelName, 0, 20) . "..." : $fromChannelName;
        $toChannelName = mb_strlen($toChannelName) >= 20 ? mb_substr($toChannelName, 0, 20) . "..." : $toChannelName;

        logger("job: fwdStepper - all forwards in queue: " . realForwardCount());
        logger("job: fwdStepper - actually forwards from: [$fromChannelName] to: [$toChannelName]");

        $fwdmsg = ""; // Reset forward message content
        foreach ($toForward as $f) {
            if ($f["from"] . $f["to"] . $f["topic"] === $fromTo) {
                $type = ($f["album"] == -1) ? "video" : "album";
                switch ($type) {
                    case "video":
                        $fileTargetCheck = syncChecktargetChannel($f["width"], $f["height"], $f["runtime"], $f["filedate"], $f["filesize"], $to);
                        if ($fileTargetCheck == -1) {
                            if ($f["cover"] != -1) {
                                $fwdmsg .= $f["text"] . " [" . $f["cover"] . "] -cover\n";
                                logger(mb_sprintf("job: fwdStepper - %-54s %s - from [%s][%s] to [%s]", $f["text"], "cover", $fromChannelName, $f["cover"], $toChannelName));
                                $forward[] = $f["cover"]; // cover
                            }
                            $fwdmsg .= $f["width"] . "x" . $f["height"] . " - " . $f["text"] . " [" . $f["msgid"] . "] -video\n";
                            logger(mb_sprintf("job: fwdStepper - %-54s %s - from [%s][%s] to [%s]", $f["text"], $type, $fromChannelName, $f["msgid"], $toChannelName));
                            $forward[] = $f["msgid"]; // video

                            // Remove the forwarded item from the queue
                            $toForwardArrayKey = buildtoForwardKey($f["from"], $f["to"], $f["msgid"], $toTopic);
                            unset($toForward[$toForwardArrayKey]);
                        } else {
                            logger(mb_sprintf("job: fwdStepper - %-54s %s - from: [%s(%s)] already [%sx] @ [%s]", $f['text'], $type, $fromChannelName, $f["msgid"], $fileTargetCheck, $toChannelName));
                            $toForwardArrayKey = buildtoForwardKey($f["from"], $f["to"], $f["msgid"], $toTopic);
                            unset($toForward[$toForwardArrayKey]);
                            $sql = "DELETE FROM forwardqueue WHERE forwardkey='" . pg_escape_string($globalsettings["db"]["pg_conn"], $toForwardArrayKey) . "'";
                            pg_query($globalsettings["db"]["pg_conn"], $sql);
                        }
                        break;

                    case "album":
                        handleAlbumForward($f, $toForward, $fromChannelName, $toChannelName, $toTopic, $forward);
                        break;
                }
            }
            if (count($forward) >= 40) break; // Prevent exceeding max RPC workers
        }

        if (count($forward) != 0) {
            $runtimeForwardCounter += count($forward);
            $runtimeForwardCalls++;
            $alltimeforwards += count($forward);

            // Update forward counts in settings
            $sql = "UPDATE settings SET value = '" . pg_escape_string($globalsettings["db"]["pg_conn"], $alltimeforwards) . "' WHERE variable = '" . pg_escape_string($globalsettings["db"]["pg_conn"], "alltimeforwards") . "'";
            pg_query($globalsettings["db"]["pg_conn"], $sql);

            // Fetch updated alltimeforwards value
            $sql = "SELECT value FROM settings WHERE variable = '" . pg_escape_string($globalsettings["db"]["pg_conn"], "alltimeforwards") . "'";
            $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
            if ($numrows = pg_num_rows($result)) {
                $row = pg_fetch_array($result);
                $alltimeforwards = $row["value"];
            }

            // Handle flood challenge logic
            handleFloodChallenge($floodchallange, $floodwait, $maxforwards, $forward, $currentTime, $runtime, $floodtimer, $interval, $globalsettings);
            
            // Perform message forwarding
            forwardMessages($forward, $to, $from, $toTopic, $channelInfoNew);
        }

        logger("job: fwdStepper - forwards in queue: " . realForwardCount());
    }
}

// Helper function to handle album forwarding
function handleAlbumForward($f, $toForward, $fromChannelName, $toChannelName, $toTopic, &$forward) {
    $fileCounter = 0;
    foreach ($toForward as $ap) {
        if ($f["from"] . $f["to"] == $ap["from"] . $ap["to"] && $f["album"] == $ap["album"]) {
            $albumType = ($ap["cover"] == "cover") ? "album-cover" : "album-video";
            if ($ap["cover"] != "cover") {
                $fileTargetCheck = syncChecktargetChannel($ap["width"], $ap["height"], $ap["runtime"], $ap["filedate"], $ap["filesize"], $ap["to"]);
                if ($fileTargetCheck == -1) {
                    $fileCounter++;
                } else {
                    logger(mb_sprintf("job: fwdStepper - %-54s %s - from [%s][%s] already [%sx] @ [%s]", $ap["text"], $albumType, $fromChannelName, $ap["msgid"], $fileTargetCheck, $toChannelName));
                }
            }
        }
    }

    if ($fileCounter == 0) {
        // All files already forwarded
        foreach ($toForward as $ap) {
            if ($f["from"] . $f["to"] . $f["topic"] == $ap["from"] . $ap["to"] . $toTopic && $f["album"] == $ap["album"]) {
                $apArrayKey = buildtoForwardKey($ap["from"], $ap["to"], $ap["msgid"], $toTopic);
                unset($toForward[$apArrayKey]);
                $sql = "DELETE FROM forwardqueue WHERE forwardkey='" . pg_escape_string($globalsettings["db"]["pg_conn"], $apArrayKey) . "'";
                pg_query($globalsettings["db"]["pg_conn"], $sql);
            }
        }
    } else {
        // Some files not yet forwarded
        foreach ($toForward as $ap) {
            if ($f["from"] . $f["to"] . $f["topic"] == $ap["from"] . $ap["to"] . $toTopic && $f["album"] == $ap["album"]) {
                $albumType = ($ap["cover"] == "cover") ? "album-cover" : "album-video";
                if (!asearch($ap["msgid"], $forward)) {
                    $fwdMsg = ($ap["cover"] == "cover") ? $ap["text"] . " [" . $ap["msgid"] . "] -" . $albumType . "\n" : $ap["text"] . " [" . $ap["msgid"] . "] -" . $albumType . "\n";
                    logger(mb_sprintf("job: fwdStepper - %-54s %s - from [%s][%s] to [%s]", $ap["text"], $albumType, $fromChannelName, $ap["msgid"], $toChannelName));
                    $forward[] = $ap["msgid"];
                }
            }
        }
    }
}

// Helper function to handle flood challenge
function handleFloodChallenge($floodchallange, &$floodwait, $maxforwards, &$forward, $currentTime, $runtime, $floodtimer, $interval, $globalsettings) {
    if ($floodchallange != -1 && $runtimeForwardCounter >= $maxforwards) {
        if ($currentTime < $floodtimer) {
            logger(mb_sprintf("job: fwdStepper - Flood waiting..."));
            $floodwait = 1;
            $forwardstopmsg = 1;
            exit;
        } else {
            logger(mb_sprintf("job: fwdStepper - Floodbreak complete."));
            $floodchallange = -1;
            $runtimeForwardCounter = 0;
        }
    }
}

// Forward the messages
function forwardMessages($forward, $to, $from, $toTopic, $channelInfoNew) {
    global $MadelineProto;
    
    foreach ($forward as $forwardItem) {
        // Forward the message
        $MadelineProto->messages->forwardMessages([
            'peer' => $to,
            'from_peer' => $from,
            'message_ids' => [$forwardItem]
        ]);
        logger(mb_sprintf("job: fwdStepper - forwarded message from [%s] to [%s] msgid [%s]", $from, $to, $forwardItem));
    }
}

// Logging helper function
function logger($message) {
    // Log to console or a file
    echo $message . "\n";
}

?>
