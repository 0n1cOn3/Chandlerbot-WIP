<?php

// Include necessary files
require_once("inc/bot.include.inc.php");
require_once("inc/botcmd.php");
require_once("inc/taillog.php");
require_once("inc/fulllog.php");
require_once("inc/title.php");
require_once("inc/cpuload.php");
require_once("inc/getlogsize.php");
require_once("inc/fwdstatus.php");
require_once("inc/queue.php");
require_once("inc/loadconf.php");
require_once("inc/getconf.php");
require_once("inc/sort.php");

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define bot configuration file path
$botconf = "../conf/chandlerbot.conf";

// Process actions based on the 'opt' request parameter
$action = $_REQUEST['opt'] ?? ''; // Get the action from the request, or default to an empty string.

if ($action === "sortchannels") {
    echo $action . "<br>";
    exit;
}

// Switch-case structure for handling various options
switch ($action) {
    case "botcmd":
        botcmd($_REQUEST['act']);
        break;

    case "taillog":
        taillog();
        break;

    case "fulllog":
        fulllog();
        break;

    case "title":
        title();
        break;

    case "cpuload":
        cpuload();
        break;

    case "getlogsize":
        getlogsize();
        break;

    case "fwdstatus":
        fwdstatus();
        break;

    case "queue":
        queue();
        break;

    case "loadconf":
        loadconf();
        break;

    case "getconf":
        getconf($_REQUEST['act']);
        break;

    case "sortchannels":
        echo "1234";
        print_r($_REQUEST);
        sortchannels($_REQUEST['act']);
        break;

    default:
        // Optionally, handle unsupported actions here
        echo "Invalid action specified!";
        break;
}
