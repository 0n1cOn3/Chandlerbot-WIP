<?php

function repostMsgJob() {
    global $globalsettings, $channelInfoNew;

    foreach (array_keys($channelInfoNew) as $channelInfoKey) {
        $channel = $channelInfoNew[$channelInfoKey];
        
        // Check if repost message ID is not set
        if ($channel["repostinfomsg_msgid"] != -1) {

            // Fetch the last runtime from the database
            $sql = "SELECT channelid, lastruntime FROM repostmsg WHERE channelid = '{$channelInfoKey}'";
            $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
            $numRows = pg_num_rows($result);

            // If no rows found, initialize new entry in repostmsg table
            if ($numRows == 0) {
                logger("job: repostMsg - init {$channel['name']}");
                $sql = "INSERT INTO repostmsg (channelid, lastruntime, channelname, lastrun) 
                        VALUES ('{$channelInfoKey}', -1, '" . pg_escape_string($globalsettings["db"]["pg_conn"], $channel['name']) . "', '" . date($globalsettings["bot"]["defaultdateformat"], time()) . "') 
                        ON CONFLICT (channelid) DO NOTHING";
                pg_query($globalsettings["db"]["pg_conn"], $sql);
                return;
            }

            // Retrieve the channel's last runtime from the result set
            while ($row = pg_fetch_array($result)) {
                $channelId = $row['channelid'];
                $lastRuntime = $row['lastruntime'];
            }

            // Calculate the next runtime based on the time mode
            switch ($channel["repostinfomsg_timemode"]) {
                case "seconds":
                    $lastRuntime += $channel["repostinfomsg_time"];
                    $nextRuntime = time() + $channel["repostinfomsg_time"];
                    break;
                case "minutes":
                    $lastRuntime += ($channel["repostinfomsg_time"] * 60);
                    $nextRuntime = time() + ($channel["repostinfomsg_time"] * 60);
                    break;
                case "hours":
                    $lastRuntime += ($channel["repostinfomsg_time"] * 60 * 60);
                    $nextRuntime = time() + ($channel["repostinfomsg_time"] * 60 * 60);
                    break;
                case "days":
                    $lastRuntime += ($channel["repostinfomsg_time"] * 86400);
                    $nextRuntime = time() + ($channel["repostinfomsg_time"] * 86400);
                    break;
            }

            // Check if it's time to repost the message
            if (time() > $lastRuntime) {
                logger("job: repostMsg - repost message: {$channel['repostinfomsg_msgid']} on {$channel['name']}:{$channel['repostinfomsg_topicid']}");
                
                // Update the repostmsg table with new last and next runtimes
                $sql = "UPDATE repostmsg 
                        SET lastruntime = " . time() . ", 
                            channelname = '" . pg_escape_string($globalsettings["db"]["pg_conn"], $channel['name']) . "', 
                            lastrun = '" . date($globalsettings["bot"]["defaultdateformat"], time()) . "', 
                            nextrun = '" . date($globalsettings["bot"]["defaultdateformat"], $nextRuntime) . "' 
                        WHERE channelid = '{$channelInfoKey}'";
                pg_query($globalsettings["db"]["pg_conn"], $sql);

                // Forward the message to the appropriate peer
                $messageParams = [
                    "background" => true,
                    "drop_author" => true,
                    "from_peer" => $channel["peerid"],
                    "to_peer" => $channel["peerid"],
                    "id" => [$channel["repostinfomsg_msgid"]],
                ];

                if ($channel["repostinfomsg_topicid"] == -1) {
                    made("messages", "forwardMessages", $messageParams);
                } else {
                    $messageParams["top_msg_id"] = $channel["repostinfomsg_topicid"];
                    made("messages", "forwardMessages", $messageParams);
                }
            }
        }
    }
}
?>
