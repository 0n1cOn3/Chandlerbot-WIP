<?php

declare(strict_types=1);

// Enable detailed error reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Displays configuration options for a specific channel.
 * 
 * @param string $cmd The command or channel key to configure.
 * 
 * @return void
 */
function getConf(string $cmd): void
{
    global $channelInfoNew, $typemapping, $shm_memory;

    // Attach shared memory and load configuration data
    $shm_key = fileinode("../bin/chandlerbot.php");
    $shm = shm_attach($shm_key, $shm_memory);
    $channelInfoNew = shm_get_var($shm, 4);

    // Output form with channel details
    echo '<form action="post">';
    echo '<input type="hidden" name="channelInfoKey" value="' . htmlspecialchars($cmd) . '">';
    echo '<table>';

    foreach ($channelInfoNew as $channelInfoKey => $channel) {
        if ($channelInfoKey === $cmd) {
            echo '<tr><td>source: </td><td><b style="color:red;">' . htmlspecialchars($channel['name']) . '</b> (' . $channel['chanid'] . ')</td></tr>';
            echo renderTypemappingSelect($channel['typemapping']);
            echo renderDisabledSelect($channel['disabled']);
            echo renderModeSelect($channel['mode']);
            echo renderRedirectOutCheckbox($channel['redirectout']);
            echo renderAutoSyncCheckbox($channel['autosync']);
            echo renderToChannels($channel['to']);
        }
    }

    echo '<tr><td><a style="text-decoration:none;" href="#" title="add">➕</a></td><td></td></tr>';
    echo '</table>';
    echo '</form>';
}

/**
 * Renders the typemapping select dropdown.
 * 
 * @param string $selected The selected typemapping.
 * 
 * @return string The HTML for the typemapping select dropdown.
 */
function renderTypemappingSelect(string $selected): string
{
    global $typemapping;

    $html = '<tr><td>typemapping: </td><td><select style="background: #b0b0a8; color: black;" name="typemapping" id="typemapping">';
    foreach ($typemapping as $mapping) {
        $isSelected = ($mapping === $selected) ? 'selected' : '';
        $html .= "<option value='{$mapping}' {$isSelected}>{$mapping}</option>";
    }
    $html .= '</select></td></tr>';

    return $html;
}

/**
 * Renders the disabled select dropdown.
 * 
 * @param string $selected The selected disabled value.
 * 
 * @return string The HTML for the disabled select dropdown.
 */
function renderDisabledSelect(string $selected): string
{
    $html = '<tr><td>disabled: </td><td><select style="background: #b0b0a8; color: black;" name="disabled" id="disabled">';
    
    $options = [
        '-1' => 'false',
        '1' => 'true',
        '2' => 'updatedb'
    ];

    foreach ($options as $value => $label) {
        $isSelected = ($value === $selected) ? 'selected' : '';
        $html .= "<option value='{$value}' {$isSelected}>{$label}</option>";
    }

    $html .= '</select></td></tr>';

    return $html;
}

/**
 * Renders the mode select dropdown.
 * 
 * @param string $selected The selected mode value.
 * 
 * @return string The HTML for the mode select dropdown.
 */
function renderModeSelect(string $selected): string
{
    $html = '<tr><td>mode: </td><td><select style="background: #b0b0a8; color: black;" name="mode" id="mode">';
    
    $modes = [
        'i' => 'interactiv',
        'ir' => 'interactiv+request',
        'r' => 'readonly'
    ];

    foreach ($modes as $value => $label) {
        $isSelected = ($value === $selected) ? 'selected' : '';
        $html .= "<option value='{$value}' {$isSelected}>{$label}</option>";
    }

    $html .= '</select></td></tr>';

    return $html;
}

/**
 * Renders the redirectout checkbox.
 * 
 * @param int $redirectOut The redirectout value.
 * 
 * @return string The HTML for the redirectout checkbox.
 */
function renderRedirectOutCheckbox(int $redirectOut): string
{
    $checked = ($redirectOut !== -1) ? 'checked' : '';
    return "<tr><td><a href='javascript:editchannel({$redirectOut});' title='{$redirectOut}' style='color:#b8bfba; text-decoration: none;'>redirectout</a></td>
            <td><input type='checkbox' id='autosync' name='autosync' value='yes' {$checked}> in progress</td></tr>";
}

/**
 * Renders the autosync checkbox.
 * 
 * @param int $autosync The autosync value.
 * 
 * @return string The HTML for the autosync checkbox.
 */
function renderAutoSyncCheckbox(int $autosync): string
{
    $checked = ($autosync === 1) ? 'checked' : '';
    return "<tr><td>autosync:</td><td><input type='checkbox' id='autosync' name='autosync' value='yes' {$checked}></td></tr>";
}

/**
 * Renders the to channels section with list of target channels.
 * 
 * @param array $toChannels List of target channels.
 * 
 * @return string The HTML for the to channels section.
 */
function renderToChannels(array $toChannels): string
{
    $html = "<tr><td style='vertical-align: top;'>to:</td><td><ul>";
    
    if (!empty($toChannels) && !array_key_exists("-1", $toChannels)) {
        foreach ($toChannels as $to) {
            $topicId = ($to['to_topic'] === -1) ? '' : ":{$to['to_topic']}";
            $topicName = htmlspecialchars($to['topic_name']);
            $channelName = htmlspecialchars($to['name']);
            $chanId = $to['chanid'];

            $html .= "<li><a style='color: #b8bfba; text-decoration:none;' href='javascript:editchannel({$chanId});' title='{$channelName}'>
                      {$chanId} <span title='topic: {$topicName}'>{$topicId}</span></a></li>";
            $html .= "<td><a style='text-decoration:none;' title='edit' href='javascript:yesno(infoarray)'>🈚</a></td>";
            $html .= "<td><a style='text-decoration:none;' title='remove' href='javascript:yesno(infoarray)'>❌</a></td>";
        }
    } else {
        $html .= "<li>no target channels configured</li>";
    }
    
    $html .= "</ul></td></tr>";
    return $html;
}

?>
