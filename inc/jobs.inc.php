<?php

// Utility function to convert memory size
function convertMemory($size) {
    $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
    $i = floor(log($size, 1024));
    return round($size / pow(1024, $i), 2) . ' ' . $unit[$i];
}

// Start the job if the conditions are met
function startJob($jobName) {
    global $interval;

    webStats();

    usleep(100000); // Control the load

    $currentTime = time();
    $lastRuntime = $interval[$jobName]['lastruntime'];
    $jobInterval = $interval[$jobName]['interval'];
    $timeMode = $interval[$jobName]['mode'];

    // Calculate next runtime based on time mode
    $lastRuntime = getNextRuntime($lastRuntime, $jobInterval, $timeMode);

    if ($currentTime > $lastRuntime) {
        if (function_exists($jobName . "Job")) {
            eval($jobName . "Job();"); // Call job function dynamically
        }

        // Update last runtime
        $interval[$jobName]['lastruntime'] = $currentTime;
    }
}

// Calculate next runtime based on time mode
function getNextRuntime($lastRuntime, $jobInterval, $timeMode) {
    switch ($timeMode) {
        case "seconds":
            return $lastRuntime + $jobInterval;
        case "minutes":
            return $lastRuntime + ($jobInterval * 60);
        case "hours":
            return $lastRuntime + ($jobInterval * 3600);
        case "days":
            return $lastRuntime + ($jobInterval * 86400);
        default:
            return $lastRuntime; // No change if mode is unknown
    }
}

// Collect system stats for monitoring
function collectSystemStats() {
    global $globalsettings, $usedMemory, $usedPeakMemory;

    $systemLoad = sys_getloadavg();
    $globalsettings["bot"]["systemload"] = array_map(fn($load) => round($load, 2), $systemLoad);
    $usedPeakMemory = convertMemory(memory_get_peak_usage(true));
    $usedMemory = convertMemory(memory_get_usage(true));
}

// Web stats update function
function webStats() {
    global $runtimeForwardCounter, $shm;
    shm_put_var($shm, 1, $runtimeForwardCounter);
    shm_put_var($shm, 2, realForwardCount());
}

// Job to track bot's activity
function aliveJob() {
    global $floodwait, $globalsettings, $usedMemory, $usedPeakMemory, $forwardstopmsg, $floodchallange, $shm;

    if ($floodwait != -1) {
        $currentTime = time();
        $waitTime = dhms($floodwait - $currentTime);

        // Handle flood wait logic
        handleFloodWait($waitTime);

        if (empty($globalsettings["bot"]["systemload"])) {
            $globalsettings["bot"]["systemload"] = [];
        }

        logBotStatus($waitTime);
        shm_put_var($shm, 3, [1, $forwardstopmsg, $waitTime]);
    } else {
        if (empty($globalsettings["bot"]["systemload"])) {
            $globalsettings["bot"]["systemload"] = [];
        }

        if (!array_key_exists(0, $globalsettings["bot"]["systemload"])) {
            collectSystemStats();
        }

        logBotStatus();
        updateFloodChallenge();
    }
}

// Handle flood wait logic
function handleFloodWait($waitTime) {
    global $floodchallange, $forwardstopmsg;

    if ($forwardstopmsg == "floodwait") {
        $floodchallange["floodtimer"] = time();
        $floodchallange["floodtimerreset"] = 1;
    }
}

// Log bot status to track system health
function logBotStatus($waitTime = null) {
    global $globalsettings, $usedMemory, $usedPeakMemory, $forwardstopmsg;

    $statusMessage = sprintf(
        "job: bot is still alive - load avg: %s, %s, %s - memory(peak/used): %s/%s - queue: %s",
        $globalsettings["bot"]["systemload"][0] ?? 'N/A',
        $globalsettings["bot"]["systemload"][1] ?? 'N/A',
        $globalsettings["bot"]["systemload"][2] ?? 'N/A',
        $usedPeakMemory,
        $usedMemory,
        realForwardCount()
    );

    if ($waitTime) {
        $statusMessage .= " - " . $forwardstopmsg . ": " . $waitTime;
    }

    logger($statusMessage);
}

// Update flood challenge for tracking forwards
function updateFloodChallenge() {
    global $floodchallange, $shm;

    $runtime = dhms(time() - $floodchallange["floodtimer"], 2);
    $actHour = $runtime["day"] * 24 + $runtime["hour"];
    $actMin = $runtime["min"];
    $key = "acthour-" . $actHour;

    if (!isset($floodchallange[$key])) {
        $floodchallange[$key]["forwardcount"] = 0;
    }

    shm_put_var($shm, 3, [0, "forwards act. tg-session hour", $floodchallange[$key]["forwardcount"] . " (" . $actMin . " min.)"]);
}

// Channel info update job
function channelInfoJob() {
    global $channelInfo, $channels;

    logger("job: renew channelInfo");
    $channelsInfoNew = loadChannelConfig(false);
    $channelInfo = rebuildChannelInfo($channelsInfoNew);
    $channels = rebuildChannels($channelsInfoNew);
}

// Read channels job
function readChannelsJob() {
    logger("job: read channels");
    getLastMessages("-1", "-1", $startup);
}

// Execute all scheduled jobs
function runJobs() {
    global $interval;

    if (is_array($interval) && count($interval) > 0) {
        foreach (array_keys($interval) as $job) {
            startJob($job);
        }
    }
}

?>
