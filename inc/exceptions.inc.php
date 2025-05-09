<?php

// Define constants for repeated values
define("FLOODWAIT", "floodwait");
define("FORWARDSTOPMSG", "forwardstopmsg");
define("MAX_RETRIES", 100);

// Helper function to escape strings for SQL queries
function escapeString($input) {
    global $globalsettings;
    return pg_escape_string($globalsettings["db"]["pg_conn"], $input);
}

// Function to log error messages with context
function logError($function, $arguments, $message) {
    logger("Exception occurred in {$function} with arguments: " . json_encode($arguments) . " -> {$message}");
}

// Function to retry an action on failure
function retryAction($class, $function, $arguments, $trycount) {
    if ($trycount >= MAX_RETRIES) {
        recoverforwardqueue(1);
        logger("chandlerbot-exception: connection: {$function} -> exit after: {$trycount} tries");
        exit;
    }

    logger("chandlerbot-exception: connection: {$function} -> retrying in 30 seconds...");
    sleep(30);
    return made($class, $function, $arguments, $trycount + 1);
}

// Refactor worker busy count to reduce repetition
function workerBusyCount($arguments) {
    global $globalsettings;

    $to_topic = array_key_exists("top_msg_id", $arguments) ? $arguments["top_msg_id"] : -1;

    foreach ($arguments["id"] as $m) {
        $sourceid = substr($arguments["from_peer"], 4);
        $targetid = substr($arguments["to_peer"], 4);
        $msgid = $m;

        $sql = "SELECT * FROM forwardqueue WHERE 
                 sourceid LIKE $1 AND 
                 targetid = $2 AND 
                 msgid = $3 AND 
                 to_topic = $4";
        $result = pg_query_params($globalsettings["db"]["pg_conn"], $sql, [
            $sourceid . '%', $targetid, $msgid, $to_topic
        ]);

        $numrows = pg_num_rows($result);
        if ($numrows != 0) {
            while ($row = pg_fetch_array($result)) {
                $filetargetcheck = syncChecktargetChannel(
                    $row["width"], $row["heigth"], $row["runtime"], 
                    $row["filedate"], $row["filesize"], $targetid
                );
                echo "{$row['width']} - {$row['heigth']} - {$row['runtime']} - {$row['filedate']} - {$row['filesize']} - {$targetid} cover: {$row['cover']}\n";
                if ($filetargetcheck == -1) {
                    echo "{$row['message']} was not forwarded \n";
                } else { 
                    echo "{$row['message']} now in target channel \n";
                }
            }
        }
    }
}

// Refactor table clearing logic to remove redundancy
function clearForwardTable($arguments) {
    global $globalsettings;

    $to_topic = array_key_exists("top_msg_id", $arguments) ? $arguments["top_msg_id"] : -1;

    foreach ($arguments["id"] as $m) {
        $sourceid = substr($arguments["from_peer"], 4);
        $targetid = substr($arguments["to_peer"], 4);
        $msgid = $m;

        $sql = "DELETE FROM forwardqueue WHERE 
                 sourceid LIKE $1 AND 
                 targetid = $2 AND 
                 msgid = $3 AND 
                 to_topic = $4";
        pg_query_params($globalsettings["db"]["pg_conn"], $sql, [
            $sourceid . '%', $targetid, $msgid, $to_topic
        ]);
    }
}

// Centralized function for handling made operations
function made($class, $function, $arguments, $trycount = 1, $return = false) {
    global $MadelineProto, $globalsettings, $systemLoad, $interval;

    try { 
        switch ($function) {
            case "getMessages":
            case "deleteMessages":
                $return = $MadelineProto->$class->$function(channel: $arguments["channel"], id: $arguments["id"]);
                break;
            case "getForumTopics":
                $return = $MadelineProto->channels->getForumTopics(channel: $arguments["channel"], limit: $arguments["limit"]);
                break;
            case "getPeerDialogs":
                $return = $MadelineProto->$class->$function(peers: $arguments["peers"]);
                break;
            case "getHistory":
                $return = $MadelineProto->$class->$function(peer: $arguments["peer"], 
                    array_key_exists("offset_id", $arguments) ? 
                    $arguments["offset_id"] : null, limit: $arguments["limit"]);
                break;
            case "forwardMessages":
                $args = [
                    "background" => $arguments["background"], 
                    "drop_author" => $arguments["drop_author"],
                    "from_peer" => $arguments["from_peer"], 
                    "to_peer" => $arguments["to_peer"], 
                    "id" => $arguments["id"]
                ];
                if (array_key_exists("top_msg_id", $arguments)) {
                    $args["top_msg_id"] = $arguments["top_msg_id"];
                }
                $MadelineProto->messages->forwardMessages($args);

                // Clear the forward table after forwarding
                clearForwardTable($arguments);
                break;
            case "sendMessage":
                $c = 1;
                $opt = "";
                foreach (array_keys($arguments) as $k) {
                    if ($c >= 3) $opt .= $k . "-";
                    $c++;
                }
                $opt = rtrim($opt, "-");

                logger("made: {$class}->{$function}: {$opt}");

                $options = [
                    "parse_mode", "reply_to_msg_id", "top_msg_id", "parse_mode-reply_to_msg_id"
                ];
                foreach ($options as $optName) {
                    if (strpos($opt, $optName) !== false) {
                        $return = $MadelineProto->$class->$function(array_merge($arguments, [
                            "peer" => $arguments["peer"],
                            "message" => $arguments["message"]
                        ]));
                    }
                }
                break;
            case "getDialogIds":
                $return = $MadelineProto->getDialogIds();
                break;
            case "getSelf":
                $return = $MadelineProto->getSelf();
                break;
            case "downloadToFile":
                $return = $MadelineProto->downloadToFile($arguments["downloadmedia"], $arguments["fileout"]);
                break;
            case "getPwrChat":
                $return = $MadelineProto->getPwrChat($arguments["peer"]);
                break;
        }
    } catch (Exception $e) {
        logError($function, $arguments, $e->getMessage());

        // Handle known errors
        $errorMessage = $e->getMessage();
        $knownErrors = [
            "FLOOD_WAIT" => function($e) {
                handleFloodWait($e);
            },
            "CHANNEL_INVALID" => function($e) {
                exit;
            },
            "WORKER_BUSY_TOO_LONG_RETRY" => function($e) {
                clearForwardTable($arguments);
            },
            "MESSAGE_ID_INVALID" => function($e) {
                clearForwardTable($arguments);
            },
            "Request timeout" => function($e) use ($class, $function, $arguments, $trycount) {
                return retryAction($class, $function, $arguments, $trycount);
            },
            "RPC_SEND_FAIL" => function($e) use ($class, $function, $arguments, $trycount) {
                return retryAction($class, $function, $arguments, $trycount);
            }
        ];

        foreach ($knownErrors as $errorPattern => $handler) {
            if (str_contains($errorMessage, $errorPattern)) {
                $handler($e);
                break;
            }
        }
    }

    return $return;
}

// These included functions are expected to be defined elsewhere in the code
function logger($message) {
    // Log the message
}

function syncChecktargetChannel($width, $height, $runtime, $filedate, $filesize, $targetid) {
    // Check target channel logic
}

function recoverforwardqueue($status) {
    // Recover forward queue logic
}

function handleFloodWait($e) {
    // Handle flood wait error
}

?>
