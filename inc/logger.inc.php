<?php
function logger($msg) {
    global $startTime;

    date_default_timezone_set('Europe/London');
    $date = gmdate("Y-m-d H:i:s", time() + date("Z"));
    $logfiledate = gmdate("YmdHi", $startTime + date("Z"));
    $logfile = "log/" . $logfiledate . "_chandlerbot.app.log";

    echo $date . " - " . $msg . "\n";

    // Un-comment the following line if you want to log to a file as well.
    // file_put_contents($logfile, $date . " - " . $msg . "\n", FILE_APPEND | LOCK_EX); // ensures logging without overwrite
}

