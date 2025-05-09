<?php

/**
 * Retrieves the topic IDs for a given channel key.
 *
 * @param string $channelInfoKey The key for the channel in the channelInfoNew array.
 * @return string Returns a formatted message about the topics or absence thereof.
 */
function getTopicIds($channelInfoKey) {
    global $channelInfoNew;

    // Get the dialog IDs for the user
    $myChans = made("", "getDialogIds()", -1);

    // Check if the channel exists in the peer database
    $channelOnPeerDatabase = CheckMyChannels($myChans, $channelInfoNew[$channelInfoKey]["chanid"]);

    // If the channel is not in the peer database
    if (!isset($channelOnPeerDatabase)) {
        return "<b>not on channel</b> (" . $channelInfoNew[$channelInfoKey]["chanid"] . ") use: <code>channelstatus</code><br>";
    }

    // Get the peer ID and fetch peer dialogs
    $peerID = $channelInfoNew[$channelInfoKey]["peerid"];
    $out = made("messages", "getPeerDialogs", ["peers" => [$peerID]]);

    usleep(200000); // Delay to prevent overloading the server

    // Initialize return message
    $returnMsg = "";

    // Check if the channel is a forum (has topics)
    if ($out['chats'][0]['forum'] == 1) {
        // Fetch forum topics for the channel
        $forumTopics = made("channels", "getForumTopics", ["channel" => $peerID, "limit" => 100]);
        $returnMsg .= "- topics on channel: <b>" . htmlspecialchars($channelInfoNew[$channelInfoKey]["name"]) . "</b>:<br>";

        // Loop through topics and append them to the return message
        foreach ($forumTopics['topics'] as $forum) {
            $id = $forum['id'];
            $title = $forum['title'];
            $returnMsg .= "[<code>" . $id . "</code>] - " . htmlspecialchars($title) . "<br>";
        }
    } else {
        $returnMsg = "no topics on channel: <b>" . htmlspecialchars($channelInfoNew[$channelInfoKey]["name"]) . "</b> (<code>" . $channelInfoNew[$channelInfoKey]["chanid"] . "</code>)<br>";
    }

    return $returnMsg;
}

/**
 * Retrieves the topic name for a given channel and topic ID.
 *
 * @param int $chanid The channel ID.
 * @param int $topic_id The topic ID to fetch the name for.
 * @return string Returns the topic name or a message indicating no topics.
 */
function getTopicName($chanid, $topic_id) {
    $peerid = "-100" . $chanid;
    $out = made("messages", "getPeerDialogs", ["peers" => [$peerid]]);

    usleep(200000); // Delay to prevent overloading the server

    // Initialize return message
    $returnMsg = "";

    // Check if the channel is a forum (has topics)
    if ($out['chats'][0]['forum'] == 1) {
        // Fetch forum topics for the channel
        $forumTopics = made("channels", "getForumTopics", ["channel" => $peerid, "limit" => 100]);

        // Loop through topics to find the requested topic
        foreach ($forumTopics['topics'] as $forum) {
            if ($forum['id'] == $topic_id) {
                $returnMsg = cleanupString($forum['title']);
                break;
            }
        }
    } else {
        $returnMsg = "no topics on channel (" . $chanid . ")";
    }

    return $returnMsg;
}

?>
