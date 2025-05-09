<?php

/**
 * Executes a bot command based on the provided action.
 *
 * @param string $action The action to perform ('start', 'stop', 'restart').
 * @return void
 */
function executeBotCommand(string $action): void
{
    $validActions = ['start', 'stop', 'restart'];

    // Validate action to prevent invalid commands
    if (!in_array($action, $validActions, true)) {
        echo "<b><p style='color:red;'>Invalid action: $action</p></b>";
        return;
    }

    // Execute the corresponding bot command
    $output = runShellCommand("./bot.sh $action");

    // Output the result of the command
    echo "<pre>$output</pre>";
}

/**
 * Runs a shell command in the parent directory.
 *
 * @param string $command The command to execute.
 * @return string The output of the command.
 */
function runShellCommand(string $command): string
{
    $escapedCommand = escapeshellcmd($command); // Escape to prevent security vulnerabilities
    $output = shell_exec("cd ..; $escapedCommand");

    if ($output === null) {
        return "Error: Failed to execute command: $command";
    }

    return $output;
}
?>
