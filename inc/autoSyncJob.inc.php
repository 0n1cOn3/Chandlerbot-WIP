<?php

function autoSyncJob() {
    global $channelInfoNew, $globalsettings;

    if (realForwardCount() != 0) { 
        logger("job: autoSync - forwardqueue is not empty, skip turn");
        return;
    }

    logger("job: autoSync - start");
    unset($breakout);

    // Loop through each channel info
    foreach ($channelInfoNew as $channelInfoKey => $channelInfo) {
        if ($channelInfo["status"] != "ok" || $channelInfo["autosync"] == -1) {
            continue;
        }

        // Check if there are channels to sync
        if (!array_key_exists("-1", $channelInfo["to"])) {
            foreach ($channelInfo["to"] as $toKey => $toChannel) {
                if (!isset($breakout)) {
                    // Check if the target channel is valid and not disabled
                    $targetChannel = $channelInfoNew[$toChannel["chanid"]] ?? null;
                    if ($targetChannel && ($targetChannel["disabled"] == -1 || $targetChannel["disabled"] == 2)) {
                        break;
                    }

                    // Build SQL query to check for files that need sync
                    $topicClause = $channelInfo["from_topic_id"] != -1 ? " and topic_id = ".$channelInfo["from_topic_id"] : "";
                    $sql = buildFileCheckQuery($channelInfo, $toChannel, $topicClause);
                    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
                    $filesLeft = pg_fetch_result($result, 0, 'count') ?? 0;

                    // Skip if no files left to sync
                    if ($filesLeft > 0) {
                        $sqllimit = getSqlLimit($channelInfo);
                        $sql = buildSyncQuery($channelInfo, $toChannel, $topicClause, $sqllimit);

                        $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
                        $numrows = pg_num_rows($result);
                        logger("job: autoSync - from: [".$channelInfo["name"]."] to: [".$toChannel["name"]."] files left: ".$filesLeft);

                        // Break out and proceed to sync
                        if ($numrows > 0) {
                            $target = $toChannel["chanid"];
                            $breakout = true;
                            break;
                        }
                    } else {
                        // Disable autosync for this channel if already in sync
                        $channelInfo["autosync"] = -1;
                        logger("job: autoSync - from: [".$channelInfo["name"]."] to: [".$toChannel["name"]."] in sync, autosync disabled");
                    }
                }
            }
        }
    }

    if (isset($breakout)) {
        syncFiles(-1, $source, $result);
        return;
    }

    logger("job: autoSync - all configured channels in sync");
    return;
}

// Helper functions to build SQL queries
function buildFileCheckQuery($channelInfo, $toChannel, $topicClause) {
    return "
        set local enable_seqscan = off;
        select count(*) as count
        from chan_{$channelInfo['chanid']} t1
        where 
            " . typeclausel($channelInfo["typemapping"]) . "
            {$topicClause}
            and not exists (
                select * from chan_{$toChannel['chanid']} t2
                where
                    t2.width = t1.width
                    and t2.height = t1.height
                    and t2.duration = t1.duration
                    and t2.filedate between t1.filedate - 5 and t1.filedate + 5
                    and t2.size = t1.size
            );
    ";
}

function buildSyncQuery($channelInfo, $toChannel, $topicClause, $sqllimit) {
    return "
        set local enable_seqscan = off;
        select msgid, message, substr(cleanmessage, 0, {$GLOBALS['globalsettings']['message']['cuttext']}) as cleanmessage, 
               duration, grouped_id, filedate, size, width, height, topic_id
        from chan_{$channelInfo['chanid']} t1
        where 
            " . typeclausel($channelInfo["typemapping"]) . "
            {$topicClause}
        order by msgid asc
        {$sqllimit}
    ";
}

function getSqlLimit($channelInfo) {
    if ($channelInfo["autosync"] == 1 || $channelInfo["autosync"] == -1) {
        return " limit 20;";
    }
    return "";
}
?>
