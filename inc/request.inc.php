<?php

// redirectout: channelid:link|id - default link $redirectout_mode

function generatebotmarker() {
    global $globalsettings;

    $globalsettings["message"]["deletemarker"] = preg_replace("/[0-4]/u", ",", $globalsettings["bot"]["starttime"]);
    $globalsettings["message"]["deletemarker"] = preg_replace("/[5-9]/u", ".", $globalsettings["message"]["deletemarker"]);
    return $globalsettings["message"]["deletemarker"];
} // function end

function cacheasks(array $infoarray): int {
    global $globalsettings;

    $sql = "SELECT msgid FROM cacheAsks WHERE grpid = $1 AND msgid = $2";
    $result = pg_query_params($globalsettings["db"]["pg_conn"], $sql, [$infoarray["channelid"], $infoarray["msgid"]]);

    if (pg_num_rows($result) === 0) {
        getlastbotcommands($infoarray);

        $sql = "INSERT INTO cacheAsks (channelid, channelname, msgid, userid, username, msgdate, message) 
                VALUES ($1, $2, $3, $4, $5, $6, $7)";
        pg_query_params($globalsettings["db"]["pg_conn"], $sql, [
            $infoarray["channelid"],
            $infoarray["channelname"],
            $infoarray["msgid"],
            $infoarray["userid"],
            $infoarray["username"],
            $infoarray["msgdate"],
            $infoarray["message"]
        ]);

        $sql = "INSERT INTO deletemessages (channelid, msgid, timestamp) 
                VALUES ($1, $2, $3) ON CONFLICT DO NOTHING";
        pg_query_params($globalsettings["db"]["pg_conn"], $sql, [
            $infoarray['channelid'],
            $infoarray['msgid'],
            $infoarray['msgdate']
        ]);

        return 1; // New command
    }

    return -1; // Old command
} // function end

function sendmessageintochannel(string $channelInfoKey, string $message, int $topicid, bool $html = true): void {
    global $channelInfoNew;

    if ($topicid != -1) {
        made("messages", "sendMessage", [
            "peer" => $channelInfoNew[$channelInfoKey]["peerid"],
            "message" => $message,
            "top_msg_id" => $topicid,
            "parse_mode" => "html"
        ]);
    } else {
        made("messages", "sendMessage", [
            "peer" => $channelInfoNew[$channelInfoKey]["peerid"],
            "message" => $message,
            "parse_mode" => "html"
        ]);
    }
} // function end

function requestusage(string $channelInfoKey, string $command, string $mode = "default"): string {
    global $channelInfoNew, $globalsettings;

    $usage = "";
    switch ($mode) {
        case "error":
            $usage .= "<b>No valid ($command) <code>{$globalsettings["request"]["requestcommand"]}</code> command!</b><br><br>please <b><u>delete</u></b> ur {$globalsettings["request"]["requestcommand"]} and try another stunt.<br><br>";
            break;
        case "help":
            $usage .= "- <code>{$globalsettings["request"]["requestcommand"]}help</code><br>";
            $usage .= "- <code>{$globalsettings["request"]["requestcommand"]}list</code><br>";
            $usage .= "- <code>{$globalsettings["request"]["requestcommand"]}#resolve</code> ".htmlspecialchars("<title> <year>")."<br><br>";

            if ($channelInfoNew[$channelInfoKey]["redirectout"] != -1) {
                switch ($channelInfoNew[$channelInfoKey]["redirectout_mode"]) {
                    case "id":
                        $usage .= "<b>redirect mode</b>:<br>";
                        $usage .= "- <code>{$globalsettings["request"]["requestcommand"]}#getfile</code> ".htmlspecialchars("<msgid>")."<br><br>";
                        break;
                }
            }
            break;
        case "invalidrequest":
            $usage .= "<b>No valid request: $command</b><br><br>please <b><u>delete</u></b> ur {$globalsettings["request"]["requestcommand"]} and try another stunt.<br><br>";
            break;
        case "alreadyondb":
        case "replyonrequest":
        case "fulfillednotcorrect":
        case "resolve":
            $usage .= $command."<br>";
            break;
    }

    $default = "- <code>{$globalsettings["request"]["requestcommand"]}</code> &lt;title&gt; &lt;year&gt;<br>eg. <code>{$globalsettings["request"]["requestcommand"]}</code> Kung Fu Panda 4 2024<br>";
    $usage .= $default;
    $usage .= generatebotmarker();
    return $usage;
} // function end

function listopenrequests(string $channelInfoKey, int $topicid): void {
    global $globalsettings, $channelInfoNew;

    $sql = "SELECT title, year, link FROM requests WHERE channelid = $1 AND fulfills = -1 AND deleted = -1";
    $result = pg_query_params($globalsettings["db"]["pg_conn"], $sql, [$channelInfoNew[$channelInfoKey]["chanid"]]);

    $numrows = pg_num_rows($result);
    $output = "<b>open requests:</b><br>";

    if ($numrows > 0) {
        while ($row = pg_fetch_assoc($result)) {
            $output .= "<code>{$row['title']} {$row['year']}</code>: <a href='{$row['link']}'>➡️</a><br>";
        }
    } else {
        $output .= "no open requests<br>";
    }

    $output .= generatebotmarker();
    sendmessageintochannel($channelInfoKey, $output, $topicid);
} // function end

function workonrequest(string $channelInfoKey, string $message, int $topicid, string $username, int $userid, string $channelname, int $channelid, int $msgid, array $infoarray): void {
    global $channelInfoNew, $globalsettings;

    preg_match_all("/(.+?)\W?(\d{4})?$/mu", $message, $res);
    $reqmsg = "";

    $year = $res[count($res)-1][0];
    $title = preg_replace("/^".strtolower($globalsettings["request"]["requestcommand"])."/u", "", strtolower($res[count($res)-2][0]));
    $title = preg_replace("/^\s+|\s+$|\s+(?=\s)/u", "", $title);
    $title = htmlspecialchars($title);

    $reqmsg .= "channelname: $channelname<br>";
    $reqmsg .= "messageid: $msgid<br>";
    $reqmsg .= "username: $username<br>";
    $link = "https://t.me/c/$channelid/$msgid";
    $reqmsg .= "request: $message<br>";
    $reqmsg .= "title: $title<br>";
    $reqmsg .= "year: $year<br>";

    if (explode(" ", $title)[0] == "#getfile") {
        $getfile_msgid = explode(" ", $message)[2];

        if (!ctype_digit($getfile_msgid)) {
            sendmessageintochannel($channelInfoKey, "❌<b>{$globalsettings["request"]["requestcommand"]} #getfile</b>: invalid message id: $getfile_msgid<br>" . generatebotmarker(), $topicid);
            return;
        }

        redirect_getfile($channelInfoKey, $getfile_msgid, $topicid, $infoarray);
        return;
    }

    if (!preg_match("/^\d{4}$/u", $year)) {
        sendmessageintochannel($channelInfoKey, requestusage($channelInfoKey, htmlspecialchars($message), "invalidrequest"), $topicid);
        return;
    }

    if (explode(" ", $title)[0] == "#resolve") {
        resolve($channelInfoKey, $topicid, $title, $year, $infoarray);
        return;
    }

    if (substr(explode(" ", $title)[0], 0, 1) == "#") {
        sendmessageintochannel($channelInfoKey, requestusage($channelInfoKey, htmlspecialchars($message), "invalidrequest"), $topicid);
        return;
    }

    $sql = "SELECT channelid, msgid, link, fulfills, request, fulfillsmessage, fulfillslink FROM requests WHERE channelid = $1 AND title = $2 AND year = $3";
    $result = pg_query_params($globalsettings["db"]["pg_conn"], $sql, [$channelid, $title, $year]);

    $numrows = pg_num_rows($result);

    if ($numrows > 0) {
        $reqrow = pg_fetch_assoc($result);
    
        if ($reqrow["fulfills"] != -1) {
            $reqmsg .= "❌ This request has already been fulfilled. Unable to process.<br>";
        } else {
            $reqmsg .= "✅ Request is being processed.<br>";
        }
    } else {
        $reqmsg .= "❌ No such request found.<br>";
    }
    
    sendmessageintochannel($channelInfoKey, $reqmsg, $topicid);
} // function end

function resolve(string $channelInfoKey, int $topicid, string $title, string $year, array $infoarray): void {
    global $globalsettings;

    $sql = "UPDATE requests SET fulfills = 1 WHERE title = $1 AND year = $2 AND channelid = $3";
    pg_query_params($globalsettings["db"]["pg_conn"], $sql, [$title, $year, $infoarray["channelid"]]);

    sendmessageintochannel($channelInfoKey, "<b>Request resolved:</b> $title $year", $topicid);
} // function end
?>
