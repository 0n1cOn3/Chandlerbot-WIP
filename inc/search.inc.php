<?php

function search($splitMsg, $askMessage, $grpid, $rerun = null) {
    global $askcommand, $globalsettings, $channelInfoNew;

    $countSplit = 0;
    $like = "%";
    $current = -1;
    $c = 1;
    
    $likearray = [];
    $notlikearray = [];
    $topicclausel = "";
    $orderclausel = "";

    // Process split message
    foreach ($splitMsg as $searchTerm) { 
        if ($c > 2) {
            // Process specific search terms
            if (substr($searchTerm, 0, 8) === "channel:" && explode(":", $searchTerm)[1] === "current") {
                $current = 1;
            }
            if (substr($searchTerm, 0, 6) === "topic:") {
                $topicclausel = " AND topicname LIKE '%" . pg_escape_string($globalsettings["db"]["pg_conn"], explode(":", $searchTerm)[1]) . "%'";
            }
            if (substr($searchTerm, 0, 6) === "order:") {
                $orderclausel = $searchTerm;
            }
            if (substr($searchTerm, 0, 1) === "-") {
                $notlikearray[] = substr($searchTerm, 1);
            }
            if (substr($searchTerm, 0, 6) !== "order:" && substr($searchTerm, 0, 1) !== "-" && substr($searchTerm, 0, 6) !== "topic:" && substr($searchTerm, 0, 8) !== "channel:") {
                $likearray[] = pg_escape_string($globalsettings["db"]["pg_conn"], $searchTerm);
            }
        }
        $c++;
    }

    // Construct LIKE condition
    if (!empty($likearray)) {
        $like .= implode("%", $likearray) . "%";
    }

    // Construct NOT LIKE condition
    $notlike = "";
    if (!empty($notlikearray)) {
        foreach ($notlikearray as $notlikes) {
            $notlike .= " AND LOWER(cleanmessage) NOT LIKE LOWER('%" . pg_escape_string($globalsettings["db"]["pg_conn"], $notlikes) . "%')";
        }
    }

    // Default empty topic clause
    $topicclausel = $topicclausel ?? "";

    // Construct order clause
    $sqlorder = " ORDER BY width DESC, height DESC, size DESC";
    if (isset($orderclausel)) {
        $order = explode(":", $orderclausel);
        switch ($order[1]) {
            case "res":
                $sqlorder = " ORDER BY width DESC";
                break;
            case "msg":
                $sqlorder = " ORDER BY msgid DESC";
                break;
            case "date":
                $sqlorder = " ORDER BY msgdate DESC";
                break;
            case "size":
                $sqlorder = " ORDER BY size DESC";
                break;
            case "dur":
                $sqlorder = " ORDER BY duration DESC";
                break;
            default:
                $sqlorder = " ORDER BY width DESC, height DESC, size DESC";
        }
    }

    // Redirect logic
    $o_channelInfoKey = $grpid;
    if (isset($channelInfoNew[$grpid]["redirectout_tochannel"]) && $channelInfoNew[$grpid]["redirectout"] != -1 && $current == -1) {
        $grpid = $channelInfoNew[$grpid]["redirectout"];
    } elseif (isset($channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"]) && $channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"] != -1) {
        $grpid = $channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"];
    }

    // Construct the SQL query
    $sql = "SELECT msgid, 
                   SUBSTR(message, 0, {$globalsettings['message']['cuttext']}) AS message, 
                   SUBSTR(cleanmessage, 0, {$globalsettings['message']['cuttext']}) AS cleanmessage, 
                   msgdate, link, width, height, size, filename, mime_type, grouped_id, duration 
            FROM chan_{$grpid} 
            WHERE type = 'messageMediaDocument' 
            AND (mime_type LIKE 'audio%' 
                OR (mime_type LIKE 'video%' AND duration > 15) 
                OR (mime_type LIKE 'application%' AND mime_type NOT LIKE '%sticker%')) 
            AND (LOWER(cleanmessage) LIKE LOWER('{$like}') 
                OR LOWER(message) LIKE LOWER('{$like}')) 
            {$notlike} {$topicclausel} {$sqlorder}";

    // Limit for rerun searches
    if ($rerun) {
        $sql .= " LIMIT {$globalsettings['search']['maxresults']}";
    }

    // Execute query
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
    $numrows = pg_num_rows($result);

    if ($numrows == 0) {
        return ["TOUSER", "SEARCH: no matches found"];
    }

    // Process results
    $returnMsg = ["", ""];
    while ($row = pg_fetch_array($result)) {
        $durationout = isset($row["duration"]) ? " - " . round($row["duration"] / 60) . " min." : "";
        $albumout = $row['grouped_id'] == -1 ? "| Ⓢ" : "| Ⓐ";
        $dim = $row['width'] == -1 ? "" : "<code>{$row['width']}x{$row['height']}</code> {$albumout} | ";
        $text = htmlspecialchars($row['cleanmessage']);
        $performer = str_contains($row['mime_type'], 'audio') ? " | " . htmlspecialchars($row['message']) : "";
        $fsize = $row['size'] == -1 ? $row['filename'] : round($row['size'] / 1024 / 1024) . " MB" . $performer;

        $returnMsg[0] = "TOCHAN";

        // Redirect handling
        if (isset($channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"]) && $channelInfoNew[$o_channelInfoKey]["redirectout"] != -1 && $current == -1) {
            switch ($channelInfoNew[$o_channelInfoKey]["redirectout_mode"]) {
                case "link":
                    $returnMsg[1] .= $dim . "<a href=\"{$row['link']}\">{$text}</a><br><code>{$fsize}</code>{$durationout}<br>";
                    break;
                case "id":
                    $returnMsg[1] .= $dim . "{$text} <br><code>{$globalsettings['request']['requestcommand']}#getfile {$row['msgid']}</code><br>{$fsize}{$durationout}<br>";
                    break;
            }
        } else {
            $returnMsg[1] .= $dim . "<a href=\"{$row['link']}\">{$text}</a><br><code>{$fsize}</code>{$durationout}<br>";
        }
    }

    // Handle result order
    if (isset($orderclausel) && !array_key_exists(2, $returnMsg) && explode(":", $orderclausel)[1] === "res") {
        $returnMsg[1] = implode("", array_reverse($returnMsg[1]));
    }

    return $returnMsg;
}
?>
