<?php

declare(strict_types=1);

// Enable comprehensive error diagnostics
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * Load configuration and render the channel information table from shared memory.
 *
 * @global int $shm_memory Shared memory allocation size
 * @return void
 */
function renderChannelConfiguration(): void
{
    global $shm_memory;

    $shmKey = fileinode(__DIR__ . '/../bin/chandlerbot.php');

    if ($shmKey === false) {
        echo "<b style='color:red;'>Error: Cannot retrieve file inode for shared memory key.</b>";
        return;
    }

    $shm = shm_attach($shmKey, $shm_memory);
    if ($shm === false) {
        echo "<b style='color:red;'>Error: Failed to attach to shared memory segment.</b>";
        return;
    }

    $channelInfo = shm_get_var($shm, 4);
    if (!is_array($channelInfo)) {
        echo "<b style='color:red;'>Error: No valid channel data found in memory.</b>";
        return;
    }

    echo "<table style='font-size: 12px;'>";

    // Header row
    echo <<<HTML
        <tr>
            <th style='text-align: left; border-bottom: 1px solid;'>&nbsp;source 
                <a href='javascript:sortchannels("sortchannels", "cmd");' title='A-Z' style='color:white; text-decoration: none;'>&nbsp;&nbsp;↑️</a>
                <a href='#' title='Z-A' style='color:gray; text-decoration: none;'>↓&nbsp;&nbsp;</a>
            </th>
            <th style='text-align: left; border-bottom: 1px solid;'>type 
                <a href='#' title='asc' style='color:white; text-decoration: none;'>&nbsp;&nbsp;↑️</a>
                <a href='#' title='desc' style='color:gray; text-decoration: none;'>↓&nbsp;&nbsp;</a>
            </th>
            <th style='text-align: left; border-bottom: 1px solid;'>status 
                <a href='#' title='asc' style='color:white; text-decoration: none;'>&nbsp;&nbsp;↑️</a>
                <a href='#' title='desc' style='color:gray; text-decoration: none;'>↓&nbsp;&nbsp;</a>
            </th>
            <th style='text-align: left; border-bottom: 1px solid;'>targets</th>
            <th style='text-align: left;'>&nbsp;</th>
            <th style='text-align: left;'>&nbsp;</th>
        </tr>
    HTML;

    foreach ($channelInfo as $channelId => $info) {
        if (!isset($info['mode']) || $info['mode'] === 'a') {
            continue;
        }

        $name = htmlspecialchars(substr($info['name'], 0, 50));
        $statusSymbol = ($info['status'] === 'nook')
            ? "<p style='color:red; display:inline;' title='" . htmlspecialchars($info['info']) . "'>&#10060;</p>"
            : "<p style='color:#71fe04; display:inline; text-align:center;'><b>&nbsp;&#10003;</b></p>";

        echo "<tr>";
        echo "<td><b><a style='color:#9e6108; text-decoration:none;' href='javascript:editchannel({$channelId});'>{$name}</a></b></td>";
        echo "<td>" . htmlspecialchars($info['typemapping']) . "</td>";
        echo "<td>{$statusSymbol}</td>";

        // Render targets
        if (!isset($info['to']['-1'])) {
            $tooltip = '';
            $counter = 1;

            foreach ($info['to'] as $target) {
                $tooltip .= $counter++ . '. ' . $target['name'] . "\n";
            }

            $targetCount = count($info['to']);
            $tooltipHtml = htmlspecialchars($tooltip);
            echo "<td style='border: 1px dotted;' title='{$tooltipHtml}'>
                    <a style='color: #b8bfba; text-decoration:none;' href='javascript:editchannel({$channelId});'>{$targetCount} to channel(s)</a>
                  </td>";
        } else {
            echo "<td style='color:#222224;'>no to channels</td>";
        }

        // Autosync icon
        $autosyncIcon = ($info['autosync'] !== -1)
            ? "<td style='font-size: 15px;' title='autosync active'>&#128663;</td>"
            : "<td>&nbsp;</td>";
        echo $autosyncIcon;

        // Sort control
        echo "<td>
                <a href='#' title='move up' style='color:white; text-decoration: none;'>&nbsp;&nbsp;↑️</a>
                &nbsp;&nbsp;
                <a href='#' title='move down' style='color:gray; text-decoration: none;'>↓&nbsp;&nbsp;</a>
              </td>";
        echo "</tr>";
    }

    // Add-row placeholder
    echo <<<HTML
        <tr>
            <td><a href='#' title='add' style='text-decoration: none;'>&nbsp;&nbsp;&nbsp;➕</a></td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    HTML;

    echo "</table>";
}
