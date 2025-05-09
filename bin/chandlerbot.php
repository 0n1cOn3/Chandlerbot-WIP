#!/usr/bin/php
<?php
require_once("inc/include.inc.php");

pg_opendb();

/**
 * Sends a message based on the target type and format.
 */
function sendMessage(array $sendMsg, string $channelKey, int $msgId, int $topicId, bool $isHtml = true): void {
    global $channelInfoNew;

    $peerId = $channelInfoNew[$channelKey]['peerid'];
    $messagePayload = [
        'peer' => $peerId,
        'message' => $sendMsg[1] . generatebotmarker(),
    ];

    if ($sendMsg[0] === 'TOUSER') {
        $messagePayload['reply_to_msg_id'] = $msgId;
    } elseif ($topicId !== -1) {
        $messagePayload['top_msg_id'] = $topicId;
    }

    if ($isHtml) {
        $messagePayload['parse_mode'] = 'html';
    }

    made("messages", "sendMessage", $messagePayload);
}

/**
 * Fetches recent messages and updates channel state.
 */
function getLastMessages(string $channelKey, string $singleTarget, bool $startup) {
    global $lastMessagePerChan, $channelInfoNew;

    $peerId = $channelInfoNew[$channelKey]['peerid'];

    if ($startup) {
        $dialogs = made("messages", "getPeerDialogs", ["peers" => [$peerId]]);
        $lastMessagePerChan[$peerId] = $dialogs['dialogs'][0]['top_message'];
        return made("messages", "getHistory", ["peer" => $peerId, "limit" => 100]);
    }

    $peers = ($singleTarget === "1") ? [$peerId] : array_keys($lastMessagePerChan);
    $dialogs = made("messages", "getPeerDialogs", ["peers" => $peers]);

    foreach ($dialogs['dialogs'] as $dialog) {
        $peer = $dialog['peer'];
        $topMsg = $dialog['top_message'];
        $dialogId = (substr($peer, 0, 4) === "-100") ? substr($peer, 4) : $peer;

        foreach ($channelInfoNew as $key => $info) {
            if ($info['chanid'] !== $dialogId) continue;

            $lastCount = $lastMessagePerChan[$info['peerid']];
            if ($lastCount === $topMsg || $info['status'] !== "ok") continue;

            $printModeMap = [
                'r' => 'readonly',
                'i' => 'interactiv',
                'ir' => 'interactiv+request',
                'a' => 'admin',
            ];

            logger("activity on channel: [{$info['name']}] - mode: {$printModeMap[$info['mode']]}");
            $limit = min(100, $topMsg - $lastCount + 5);
            $lastMessagePerChan[$info['peerid']] = $topMsg;
            $data = made("messages", "getHistory", ["peer" => $info['peerid'], "limit" => $limit]);

            switch ($info['mode']) {
                case "ir": if ($data) { getAskTasks($data, $key); request($data, $key); } break;
                case "i":  if ($data) getAskTasks($data, $key); break;
                case "r":  if ($data) insertData(parseData($data), $key); break;
                case "a":  if ($data) adminCommands($data, $startup); break;
            }
        }
    }
}

/**
 * Processes bot commands from user messages.
 */
function BotCommands(string $askMessage, string $channelKey, int $msgId, array $infoArray, ?string $rerun = null): array {
    global $globalsettings, $channelInfoNew;

    $botName = $channelInfoNew[$channelKey]['channelbotname'];
    $askCommandPrefix = "!" . $botName . " ";
    $askMessage = trim(preg_replace("/[()]+|(?<=\s)\s+/", "", $askMessage));
    $splitMsg = explode(" ", $askMessage);

    $cmd = null;
    foreach ($globalsettings["bot"]["botcommands"] as $botCmd) {
        if (strcasecmp($botCmd, $splitMsg[1]) === 0) {
            $cmd = strtolower($botCmd);
            break;
        }
    }

    $secArg = $splitMsg[2] ?? false;

    if (!$cmd) {
        return ["TOUSER", "{$splitMsg[1]}, no valid bot command!"];
    }

    $groupId = $channelInfoNew[$channelKey]['chanid'];
    $fromChannel = strlen($channelInfoNew[$channelKey]['name']) >= 20
        ? substr($channelInfoNew[$channelKey]['name'], 0, 20) . "..."
        : $channelInfoNew[$channelKey]['name'];

    logger("botcommand on [{$fromChannel}]: {$askMessage}");

    switch ($cmd) {
        case "help":
            $helpMsg = "<b> available commands</b>:<br>";
            foreach ($globalsettings["bot"]["botcommands"] as $adminCmd) {
                $helpMsg .= "-<code> {$askCommandPrefix}{$adminCmd}</code><br>";
            }
            if ($channelInfoNew[$channelKey]["mode"] === "ir") {
                $helpMsg .= "<br>-<code> " . $globalsettings["request"]["requestcommand"] . "</code><br>";
            }
            $helpMsg .= "<br>" . $globalsettings["bot"]["version"] . "<br>";
            return ["TOCHAN", $helpMsg];

        case "search":
        case "suche":
            return search($splitMsg, $askMessage, $groupId, $rerun);

        case "last5":
        case "last10":
        case "last20":
        case "last30":
        case "last50":
        case "last100":
            $limit = (int)substr($cmd, 4);
            return last($groupId, $limit, $channelKey, $infoArray, $secArg);

        case "say":
            return askPaul();

        default:
            return ["TOUSER", 'no valid botcommand'];
    }
}
