#!/usr/bin/php
<?php

function usr_managment_useractivity($data) {
    foreach (array_keys($data) as $k) {
        if (!array_key_exists("status", $data)) return "userStatusNoStatusSet";
        if ($data[$k] == "status") {
            return empty($data["status"]["_"]) ? "userStatusArrayIsEmpty_wtf" : $data["status"]["_"];
        }
    }
    return "userStatusNoStatusSet";
}

function usr_managment_user($channelInfoKey, $useridsearch, $useroption, $option) {
    global $globalsettings, $channelInfoNew;
    switch ($option) {
        case "userid":
            if (!array_key_exists($useroption, $useridsearch)) {
                logger(mb_sprintf("usr_managment_getuser: userid %s not found", $useroption));
                logger(mb_sprintf("------------------------------------------"));
                return false;
            }
            return [$useroption => true];

        case "username":
            $useridsearch = [];
            foreach ($channelInfoNew[$channelInfoKey]["channeluser"] ?? [] as $userkey => $user) {
                if ($userkey === "lastupdate") continue;
                if (
                    str_contains(strtolower($user["full_name"]), strtolower($useroption)) ||
                    str_contains(strtolower($user["username"]), strtolower($useroption))
                ) {
                    $useridsearch[$user["id"]] = true;
                }
            }
            if (empty($useridsearch)) {
                logger(mb_sprintf("usr_managment_getuser: username like %s not found", $useroption));
                logger(mb_sprintf("------------------------------------------"));
                return false;
            }
            return $useridsearch;

        case "role":
            if (is_numeric($useroption)) {
                return usr_managment_user($channelInfoKey, $useridsearch, $useroption, "userid");
            } else {
                return usr_managment_user($channelInfoKey, $useridsearch, $useroption, "username");
            }
    }
    return [];
}

function usr_managment($channelInfoKey, $option = false, $useroption = false) {
    global $globalsettings, $channelInfoNew;

    $caching = ($option === "renewcache") ? "renew cache" : "caching";
    $channelInfoNew[$channelInfoKey]["channeluser"] ??= [];

    logger(mb_sprintf(
        "usr_managment: users on: %s (%s%s)",
        $channelInfoNew[$channelInfoKey]["name"],
        $option ? "option: $option" : "",
        $useroption ? ", useroption: $useroption" : ""
    ));
    logger(mb_sprintf("------------------------------------------"));

    if (
        isset($channelInfoNew[$channelInfoKey]["channeluser"]["lastupdate"]) &&
        $channelInfoNew[$channelInfoKey]["channeluser"]["lastupdate"] === 0
    ) {
        logger("no rights to read userdata");
        logger("------------------------------------------");
        return false;
    }

    if (!isset($channelInfoNew[$channelInfoKey]["channeluser"]["lastupdate"]) || $option === "renewcache") {
        logger(mb_sprintf("usr_managment: %s users on channel: %s", $caching, $channelInfoNew[$channelInfoKey]["name"]));

        $userdata = made("", "getPwrChat()", ["peer" => $channelInfoNew[$channelInfoKey]["peerid"]]);
        if (!$userdata || empty($userdata["participants"])) {
            $channelInfoNew[$channelInfoKey]["channeluser"]["lastupdate"] = 0;
            logger("------------------------------------------");
            logger("no rights to read userdata");
            logger("------------------------------------------");
            return false;
        }

        $channelInfoNew[$channelInfoKey]["channeluser"]["lastupdate"] = time();

        foreach ($userdata["participants"] as $user) {
            $user["role"] ??= "overloading";
            if (!isset($user["role"])) {
                logger("ERR: user has no role(overloading [role]), debug-data below");
                print_r($user);
            }

            $userkey = $user["user"]["id"];
            $info = [
                "id" => $user["user"]["id"],
                "role" => $user["role"],
                "username" => $user["user"]["username"] ?? -1,
                "first_name" => $user["user"]["first_name"] ?? -1,
                "last_name" => $user["user"]["last_name"] ?? -1,
            ];
            $info["full_name"] = trim(
                ($info["first_name"] !== -1 ? $info["first_name"] : "") .
                " " .
                ($info["last_name"] !== -1 ? $info["last_name"] : "")
            );
            if ($info["username"] === -1 && $info["full_name"] === "" && $info["role"] !== "banned") {
                $info["role"] = "deleted account";
            }
            $channelInfoNew[$channelInfoKey]["channeluser"][$userkey] = $info;
        }
    }

    if (in_array($option, ["role", "username", "userid"], true)) {
        $useridsearch = usr_managment_user($channelInfoKey, $channelInfoNew[$channelInfoKey]["channeluser"], $useroption, $option);

        if ($useridsearch === false) {
            logger("usr_managment: no users matched criteria.");
            logger("------------------------------------------");
            return false;
        }

        $users = [];
        foreach ($useridsearch as $userid => $_) {
            if (isset($channelInfoNew[$channelInfoKey]["channeluser'][$userid])) {
                $users[$userid] = $channelInfoNew[$channelInfoKey]["channeluser"][$userid];
            }
        }

        if (empty($users)) {
            logger("usr_managment: matched user IDs, but no corresponding data in cache.");
            logger("------------------------------------------");
            return false;
        }

        logger(mb_sprintf("usr_managment: returning %d matched users", count($users)));
        logger("------------------------------------------");

        return $users;
    }

    if ($option === false) {
        return $channelInfoNew[$channelInfoKey]["channeluser"];
    }

    $useridsearch = $channelInfoNew[$channelInfoKey]["channeluser"];
    $useridsearch = usr_managment_user($channelInfoKey, $useridsearch, $useroption, $option);
    if ($useridsearch === false) return false;

    $users = [];
    foreach ($useridsearch as $uid => $active) {
        if (!isset($channelInfoNew[$channelInfoKey]["channeluser"][$uid])) continue;
        $users[$uid] = $channelInfoNew[$channelInfoKey]["channeluser"][$uid];
    }
    return $users;
}