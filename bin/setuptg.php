#!/usr/bin/php
<?php
declare(strict_types=1);

// Initialization
$donotaskPID = true;
require_once("inc/include.inc.php");

// Set timezone and generate formatted timestamp
date_default_timezone_set('Europe/London');
$currentDateTime = gmdate("Y-m-d H:i:s", time() + date("Z"));

// Prepare message payload
$messageContent = "<b>madeline as client registered </b>: {$currentDateTime}<br>";
$recipientId = $globalsettings["bot"]["chandlerownerid"];

$response = made("messages", "sendMessage", [
    "peer" => $recipientId,
    "message" => $messageContent,
    "parse_mode" => "html"
]);

// Uncomment for debugging
// print_r($response);
