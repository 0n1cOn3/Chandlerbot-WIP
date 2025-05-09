<?php

function getMappingInfo($channelInfoKey, $channelInfoNew) {
    $chanid = explode("-", $channelInfoKey)[0];
    $mappingInfo = [];

    foreach ($channelInfoNew as $channel) {
        if ($channel["chanid"] == $chanid) {
            $mappingInfo[] = $channel["typemapping"];
        }
    }

    return !empty($mappingInfo) ? $mappingInfo : false;
}

function getForwardInfo($channelInfoKey, $channelInfoNew) {
    $chanid = explode("-", $channelInfoKey)[0];
    $forwardInfo = [];

    foreach ($channelInfoNew as $channel) {
        if ($channel["chanid"] == $chanid) {
            if (!array_key_exists("-1", $channel["to"])) {
                foreach ($channel["to"] as $t) {
                    $targetChanId = $t["chanid"];
                    if (array_key_exists($targetChanId, $channelInfoNew)) {
                        $targetChannel = $channelInfoNew[$targetChanId];
                        if ($targetChannel["disabled"] == -1) {
                            $forwardInfo[$chanid]["from_topic_id"][$channel["from_topic_id"]][$targetChanId] = $t["to_topic"];
                        }
                    } else {
                        $forwardInfo[$chanid]["from_topic_id"][$channel["from_topic_id"]][$targetChanId] = $t["to_topic"];
                    }
                }
            }
        }
    }

    return !empty($forwardInfo) ? $forwardInfo : false;
}

function getChannelKeyFromChanid($chanid, $channelInfoNew) {
    foreach ($channelInfoNew as $key => $channel) {
        if ($channel["chanid"] == $chanid) {
            return $key;
        }
    }
    return false;
}

function loadChandlerbotConfig($conf = false) {
    global $askcommand, $recoverqueue, $globalsettings;

    $channelsConf = $conf ?: "conf/chandlerbot.conf";

    if (!$conf) {
        logger("loadChandlerbotConfig: using: ".$channelsConf);
    }

    checkChannelsConf($channelsConf);
    $data = yaml_parse_file($channelsConf);

    $globalsettings["bot"]["botname"] = $data["botcommand"];
    $askcommand = "!" . $globalsettings["bot"]["botname"] . " ";

    $globalsettings["request"]["requestcommand"] = "#" . $data["requestcommand"] . " ";
    $globalsettings["bot"]["downloaddirectory"] = $data["downloaddirectory"];
    $globalsettings["db"] = [
        "dbhost" => $data["dbhost"],
        "dbport" => $data["dbport"],
        "dbuser" => $data["dbuser"],
        "dbpass" => $data["dbpass"],
        "dbname" => $data["dbname"]
    ];

    $recoverqueue = $data["recover forward queue"];
    $globalsettings["forward"]["recoverqueue"] = $data["recover forward queue"];
}

function checkMyChannels($myChans, $chanid) {
    foreach ($myChans as $mychan) {
        if (substr($mychan, 0, 4) == "-100" && substr($mychan, 4) == $chanid) {
            return true;
        }
    }
    return false;
}

function checkDialog($dialog, $chanid, $dialogArray, $dialogKey) {
    foreach ($dialog[$dialogArray] as $d) {
        if ($d['id'] == $chanid && array_key_exists($dialogKey, $d)) {
            return $d[$dialogKey];
        }
    }
    return false;
}

function getConfTopicName($channelInfoNew, $chanid, $topic_id) {
    foreach ($channelInfoNew as $channel) {
        if (array_key_exists("to", $channel) && !array_key_exists("-1", $channel["to"])) {
            foreach ($channel["to"] as $t) {
                if ($t["chanid"] == $chanid && $t["to_topic"] == $topic_id && $t["topic_name"] != "-1") {
                    return $t["topic_name"];
                }
            }
        }
    }
    return false;
}

function checkChannelsConf($file) {
    if (!file_exists($file)) {
        echo "ERR: config file: ".$file." missing!\n";
        exit;
    }
}

function checkPandabotPID() {
    global $donotaskPID;

    if (!isset($donotaskPID)) {
        $donotaskPID = false;
    }

    $tmpdir = "log/tmp/";
    $chandlerbot = "chandlerbot.pid";
    $mypid = getmypid();
    $lockfile = $tmpdir . $chandlerbot;

    if (file_exists($lockfile)) {
        $lockingPID = trim(file_get_contents($lockfile));
        $pidsshellout = shell_exec("ps -ef | awk -v pid=".$lockingPID." '{if ($2 == pid) {print $2}}'");
        $pids = explode("\n", $pidsshellout);

        if (in_array($lockingPID, $pids) && $donotaskPID == false) {
            logger("checkPandabotPID: ERR: chandlerbot is running @ pid: ".$lockingPID);
            exit;
        }
    }

    if (file_exists($lockfile)) unlink($lockfile);
    file_put_contents($lockfile, $mypid . "\n");
}

function copyChannelConfig($channelInfoNew) {
    $tmpdir = "log/tmp/";
    $confdir = "conf/";

    $channelsConfFile = "channels.conf";
    $channelsConfOrig = $confdir . $channelsConfFile;
    $channelsConfTmp = $tmpdir . $channelsConfFile;

    if (file_exists($channelsConfTmp)) {
        unlink($channelsConfTmp);
    }

    writeChannelConfig($channelInfoNew);
    copy($channelsConfOrig, $channelsConfTmp);
}

function loadChannelConfig($startup = false, $cfgfile = false) {
    global $shm, $globalsettings;

    $channelsConf = $startup ? "conf/channels.conf" : "log/tmp/channels.conf";
    if ($cfgfile) {
        $channelsConf = $cfgfile;
    }

    $channelInfoNew = [];
    $globalsettings["channels"]["channeldb"] = made("", "getDialogIds()", -1);

    // inject admin channel - predefine $channelInfoNew array
    $getSelf = made("", "getSelf()", -1);
    $channelInfoNew["chandlerbot owner"] = [
        "chanid" => $getSelf['id'],
        "peerid" => $getSelf['id'],
        "typemapping" => "video",
        "status" => "ok",
        "info" => "chandlerbot admin channel - saved messages",
        "from_topic_id" => -1,
        "from_topic_name" => -1,
        "autosync" => -1,
        "disabled" => -1,
        "mode" => "a",
        "to" => ["-1" => ["chanid" => -1, "peerid" => -1, "status" => "nook", "info" => "no to settings @ admin channel", "to_topic" => -1, "topic_name" => -1, "name" => -1]],
        "name" => "@" . $getSelf["username"],
        "forward" => "nook",
        "topicchannel" => -1,
        "redirectout" => -1,
        "redirectout_name" => -1,
        "redirectout_mode" => "link",
        "redirectout_tochannel" => -1,
        "redirectout_tochannel_name" => -1,
        "channelbotname" => $globalsettings["bot"]["botname"],
        "repostinfomsg_msgid" => -1,
        "repostinfomsg_time" => -1,
        "repostinfomsg_timemode" => -1,
        "repostinfomsg_topicid" => -1,
        "channeluser" => -1
    ];

    logger("loadChannelConfig: using: ".$channelsConf);
    checkChannelsConf($channelsConf);

    $myChans = made("", "getDialogIds()", -1);
    $data = yaml_parse_file($channelsConf);

    foreach ($data as $k => $channelData) {
        // Process each channel configuration
        processChannelConfig($channelInfoNew, $myChans, $channelData, $k);
    }
}
