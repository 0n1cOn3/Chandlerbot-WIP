<?php

function last($grpid, $limit, $channelInfoKey, $infoarray, $topic = false) {
    global $globalsettings, $channelInfoNew;

    // Check for topic filtering
    if ($topic && $channelInfoNew[$channelInfoKey]["topicchannel"] == 1 && $topic != strtolower("channel:current")) {
        $topicclausel = " AND (LOWER(cleantopicname) LIKE '%" . pg_escape_string($globalsettings["db"]["pg_conn"], strtolower($topic)) . "%' OR LOWER(topicname) LIKE '%" . pg_escape_string($globalsettings["db"]["pg_conn"], strtolower($topic)) . "%')";
    } else {
        $topicclausel = "";
    }

    // Current check
    $current = ($topic == strtolower("channel:current")) ? 1 : -1;

    // Handling redirection logic
    $o_channelInfoKey = $grpid;
    if ($channelInfoNew[$grpid]["redirectout"] != -1 && $current == -1) {
        $o_channelInfoKey = $grpid;
        $grpid = $channelInfoNew[$grpid]["redirectout"];
    } else {
        if (isset($channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"]) && $channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"] != -1) {
            $grpid = $channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"];
        }
    }

    // SQL query to fetch media messages
    $sql = "SELECT msgid, 
                   SUBSTRING(message, 0, " . $globalsettings["message"]["cuttext"] . ") AS message, 
                   SUBSTRING(cleanmessage, 0, " . $globalsettings["message"]["cuttext"] . ") AS cleanmessage, 
                   msgdate, 
                   link, 
                   width, 
                   height, 
                   size, 
                   filename, 
                   mime_type, 
                   grouped_id 
            FROM chan_" . $grpid . " 
            WHERE type = 'messageMediaDocument' " . $topicclausel . " 
            AND (mime_type LIKE 'audio%' 
                 OR (mime_type LIKE 'video%' AND duration > 15) 
                 OR (mime_type LIKE 'application%' AND mime_type NOT LIKE '%sticker%'))
            ORDER BY msgid DESC 
            LIMIT " . $limit;

    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
    $numrows = pg_num_rows($result);

    // Check if there are results
    if ($numrows == 0) {
        $returnMsg[0] = "TOUSER";
        $returnMsg[1] = "LAST" . $limit . ": no matches found";
        return $returnMsg;
    }

    // Process each row and prepare the response
    $returnMsg[1] = "";
    while ($row = pg_fetch_array($result)) {
        $albumout = ($row['grouped_id'] == -1) ? "| Ⓢ" : "| Ⓐ";
        $dim = ($row['width'] == -1) ? "" : "<code>" . $row['width'] . "x" . $row['height'] . "</code> " . $albumout . " | ";

        $text = htmlspecialchars($row['cleanmessage']);
        $link = $row['link'];
        $msgid = $row['msgid'];

        // Handle audio and file size
        $performer = (str_contains($row['mime_type'], 'audio')) ? " | " . htmlspecialchars($row['message']) : "";
        $fsize = ($row['size'] == -1) ? $row['filename'] : round($row['size'] / 1024 / 1024) . " MB" . $performer;

        // Prepare redirection or normal output based on settings
        if (isset($channelInfoNew[$o_channelInfoKey]["redirectout_tochannel"]) && $channelInfoNew[$o_channelInfoKey]["redirectout"] != -1 && $current == -1) {
            switch ($channelInfoNew[$o_channelInfoKey]["redirectout_mode"]) {
                case "link":
                    $returnMsgRevert[] = $dim . "<a href=\"" . $link . "\">" . $text . "</a><br><code>" . $fsize . "</code><br>";
                    break;
                case "id":
                    if ($topic == strtolower(substr($globalsettings["request"]["requestcommand"], 0, -1) . ":#getfile")) {
                        $rightuserout = usr_managment($infoarray["channelid"], "userid", $infoarray["userid"])[$infoarray["userid"]]["role"];
                        if ($rightuserout == "creator" || $rightuserout == "admin") {
                            redirect_getfile($o_channelInfoKey, $msgid, $infoarray["topicid"], []);
                            $returnMsgRevert[] = $dim . $text . "<br>" . $fsize . "<br>";
                        } else {
                            $returnMsgRevert[] = $dim . $text . "<br><code>" . $globalsettings["request"]["requestcommand"] . "#getfile " . $msgid . "</code><br>" . $fsize . "<br>";
                        }
                    } else {
                        $returnMsgRevert[] = $dim . $text . "<br><code>" . $globalsettings["request"]["requestcommand"] . "#getfile " . $msgid . "</code><br>" . $fsize . "<br>";
                    }
                    break;
            }
        } else {
            $returnMsgRevert[] = $dim . "<a href=\"" . $link . "\">" . $text . "</a><br><code>" . $fsize . "</code><br>";
        }
    }

    // Finalize response message
    $returnMsg[0] = "TOCHAN";
    for ($i = count($returnMsgRevert) - 1; $i >= 0; $i--) {
        $returnMsg[1] .= $returnMsgRevert[$i];
    }

    return $returnMsg;
} // function end
