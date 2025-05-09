<?php

// Initialize Pandabot by checking its PID and loading the configuration
initializePandabot();

// Include MadelineProto dependencies
require_once 'vendor/autoload.php';

use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Settings\RPC;

// Function to initialize Pandabot
function initializePandabot() {
    checkPandabotPID();
    loadPandabotConfig();
}

// Function to configure the MadelineProto client and set bot settings
function configureBotSettings($settings) {
    global $globalsettings;

    // Set bot version with MadelineProto version
    $settings->setAppInfo(
        (new AppInfo)
            ->setApiId($globalsettings["tgapp"]["APIID"])
            ->setApiHash($globalsettings["tgapp"]["APIHASH"])
            ->setAppVersion($globalsettings["bot"]["version"] . " (MP: " . getMadelineProtoVersion($settings) . ")")
    );

    // Set connection settings
    $settings->setConnection(
        (new Connection)
            ->setTimeout($globalsettings["tgapp"]["setTimeout"])
    );

    // Set RPC settings
    $settings->setRPC(
        (new RPC)
            ->setRpcResendTimeout($globalsettings["tgapp"]["setRpcResendTimeout"])
    );
}

// Function to get MadelineProto version
function getMadelineProtoVersion($settings) {
    return explode(" ", $settings->getAppInfo(new AppInfo)->getAppVersion())[0];
}

// Initialize MadelineProto API with configuration
function initializeMadelineProto($settings) {
    $MadelineProto = new \danog\MadelineProto\API('session.madeline', $settings);
    $MadelineProto->start();

    return $MadelineProto;
}

// Set bot owner information
function setBotOwner($MadelineProto) {
    $getSelf = $MadelineProto->getSelf();
    global $globalsettings;

    // Set global bot owner information
    $globalsettings["bot"]["chandlerownerid"] = $getSelf['id'];
    $globalsettings["bot"]["chandlerownername"] = "@" . $getSelf['username'];
}

// Main execution
$settings = new Settings;
configureBotSettings($settings);

$MadelineProto = initializeMadelineProto($settings);
setBotOwner($MadelineProto);

$chandlerOwnerName = "@" . $globalsettings["bot"]["chandlerownername"];

?>
