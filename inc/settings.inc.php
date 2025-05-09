<?php

// Define constants for configurations
define('FLOOD_WAIT', -1);
define('MEMORY_LIMIT', '64M');
define('TIMEZONE', 'Europe/Berlin');
define('MAX_FORWARDS', 1400);
define('REPOST_MSG_INTERVAL', 30); // in seconds

// Set memory limit
ini_set('memory_limit', MEMORY_LIMIT);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Function to safely parse YAML configuration
function parseYaml($yaml) {
    $parsed = @yaml_parse($yaml);
    if ($parsed === false) {
        throw new Exception("Failed to parse YAML configuration.");
    }
    return $parsed;
}

// Load interval configuration
$intervalyaml = file_get_contents('interval_config.yaml'); // Assume this YAML is in a separate file
$interval = parseYaml($intervalyaml);

// Load type mapping configuration
$typemappingyaml = file_get_contents('typemapping_config.yaml'); // Assume this YAML is in a separate file
$typemapping = parseYaml($typemappingyaml);

// Load global settings configuration
$globalsettingsyaml = file_get_contents('globalsettings_config.yaml'); // Assume this YAML is in a separate file
$globalsettings = parseYaml($globalsettingsyaml);

// Initialize necessary variables
$lastMessagePerChan = [];
$runtimeForwardCounter = 0;
$alltimeforwards = 0;
$runtimeForwardCalls = 0;
$recoverqueue = true;
$toForward = [];
$forwardstopmsg = "";
$floodchallange = ['floodtimer' => time()];
$forwardMsg = [];
$bckStepArray = [];
$isJobRunning = [];
$usedPeakMemory = "just wait";
$usedMemory = "just wait";
$requestSilentMode = 1; // -1 || 1
$floodwait = FLOOD_WAIT;

// Track memory usage
function trackMemoryUsage() {
  global $usedMemory, $usedPeakMemory;
  $currentMemory = memory_get_usage();
  $peakMemory = memory_get_peak_usage();

  // Only log if memory usage has increased significantly
  if ($currentMemory > $usedMemory * 1.1 || $peakMemory > $usedPeakMemory * 1.1) {
      $usedMemory = $currentMemory;
      $usedPeakMemory = $peakMemory;
  }
}

// Flood control logic
function checkFlood() {
    global $floodchallange;
    $currentTime = time();
    if (($currentTime - $floodchallange['floodtimer']) < FLOOD_WAIT) {
        return false; // Flooding detected
    }
    // Reset the flood timer
    $floodchallange['floodtimer'] = $currentTime;
    return true;
}

// Task scheduling logic (example: message forwarding)
function scheduleTask($task) {
    global $interval, $lastMessagePerChan;
    $currentTime = time();
    $taskConfig = $interval[$task];

    if ($taskConfig) {
        $lastRun = $taskConfig['lastruntime'];
        $intervalInSeconds = getIntervalInSeconds($taskConfig);

        if (($currentTime - $lastRun) >= $intervalInSeconds) {
            $lastMessagePerChan[$task] = $currentTime;
            return true;
        }
    }
    return false;
}

// Convert interval to seconds based on the mode
function getIntervalInSeconds($taskConfig) {
    $interval = $taskConfig['interval'];
    $mode = $taskConfig['mode'];
    
    switch ($mode) {
        case 'seconds':
            return $interval;
        case 'minutes':
            return $interval * 60;
        case 'hours':
            return $interval * 3600;
        default:
            return $interval; // Default to seconds
    }
}

// Example: Checking and forwarding message
if (scheduleTask('repostMsg') && checkFlood()) {
    // Forward message logic goes here
    echo "Forwarding message...\n";
}

// Example: Track memory usage periodically
// Periodic memory tracking
function trackMemoryUsagePeriodically() {
  global $lastMemoryTrackTime;
  $currentTime = time();

  // Track memory every MEMORY_TRACK_INTERVAL seconds
  if (!$lastMemoryTrackTime || ($currentTime - $lastMemoryTrackTime) >= MEMORY_TRACK_INTERVAL) {
      trackMemoryUsage();
      $lastMemoryTrackTime = $currentTime;
  }
}

// Initialize last memory tracking time
$lastMemoryTrackTime = 0;

// Periodically track memory usage
trackMemoryUsagePeriodically();

// Constants for task synchronization
define('TASK_SYNC_INTERVAL', 60); // Sync interval in seconds
define('CHANNEL_SYNC_INTERVAL', 300); // Channel sync interval in seconds (5 minutes)

// Initialize necessary variables
$channelUpdates = [];
$isSyncingInProgress = false;
$lastSyncTime = 0; // Last sync time to track periodic sync

// Channel sync function
function processChannelUpdates() {
    global $channelUpdates, $isSyncingInProgress, $lastSyncTime;

    $currentTime = time();
    
    // Check if the sync interval has passed (to avoid multiple syncs at the same time)
    if (($currentTime - $lastSyncTime) < CHANNEL_SYNC_INTERVAL) {
        return; // Exit if sync is not needed yet
    }

    // Start the sync process
    $isSyncingInProgress = true;
    
    // Process channel updates (this could be loading new channels, syncing, etc.)
    echo "Starting channel updates...\n";

    foreach ($channelUpdates as $channelId => $channelData) {
        echo "Processing update for channel: $channelId\n";
        
        // Example operations:
        // - Sync the channel with an external API
        // - Check the status of the channel
        // - Update channel settings or other relevant info

        // Here, for demonstration, we'll simulate a delay in processing each channel
        sleep(2); // Simulate a task (you should replace this with actual logic)

        // Example: Update channel status to 'synced'
        $channelUpdates[$channelId]['status'] = 'synced';
    }

    // Finish syncing
    $lastSyncTime = $currentTime;  // Update the last sync time
    $isSyncingInProgress = false;  // Mark syncing as finished
    echo "Channel updates completed.\n";
}

// Sync tasks periodically (task syncing logic)
function syncTasks() {
    global $isSyncingInProgress;

    if ($isSyncingInProgress) {
        echo "Syncing is already in progress. Skipping...\n";
        return;
    }

    echo "Starting task synchronization...\n";
    // Example task syncing operations, such as checking if tasks need to be synced or processed:
    // You can extend this with specific syncing logic as per your requirements.

    // Simulate a task sync process (replace with real logic)
    sleep(1); // Simulate processing time

    echo "Task synchronization completed.\n";
}

// Function to add a channel update task (for simulation)
function addChannelUpdate($channelId, $channelData) {
    global $channelUpdates;

    echo "Adding update task for channel: $channelId\n";
    $channelUpdates[$channelId] = $channelData;
}

// Track periodic sync and updates
function trackSyncAndUpdates() {
    global $lastSyncTime, $isSyncingInProgress;
    $currentTime = time();
    
    // Sync tasks periodically (every TASK_SYNC_INTERVAL)
    if (($currentTime - $lastSyncTime) >= TASK_SYNC_INTERVAL) {
        // Call syncTasks to sync the tasks
        syncTasks();
        $lastSyncTime = $currentTime;
    }
    
    // Process channel updates periodically (check and process every 5 minutes for example)
    processChannelUpdates();
}

// Example channel updates (You can replace these with real data)
addChannelUpdate(101, ['name' => 'Channel ', 'status' => 'pending']);
addChannelUpdate(102, ['name' => 'Channel 102', 'status' => 'pending']);
addChannelUpdate(103, ['name' => 'Channel 103', 'status' => 'pending']);

// Periodically track sync and channel updates
trackSyncAndUpdates();

?>
