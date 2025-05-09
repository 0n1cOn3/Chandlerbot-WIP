<?php

/**
 * Custom sprintf with multi-byte string support.
 *
 * @param string $format The format string.
 * @param mixed ...$args The arguments to be formatted.
 * @return string The formatted string.
 */
function mb_sprintf($format, ...$args) {
    $params = $args;
    $callback = function ($length) use (&$params) {
        $value = array_shift($params);
        return $length[0] + strlen($value) - mb_strwidth($value);
    };
    
    // Adjust formatting for multi-byte strings
    $format = preg_replace_callback('/(?<=%|%-)\d+(?=s)/', $callback, $format);
    return sprintf($format, ...$args);
}

/**
 * Reverts the output of print_r back to an array or object.
 *
 * @param string $input The print_r output.
 * @return mixed The original array or object.
 */
function print_r_reverse($input) {
    $lines = preg_split('#\r?\n#', trim($input));

    if (trim($lines[0]) !== 'Array' && trim($lines[0]) !== 'stdClass Object') {
        return $input === '' ? null : $input; // Non-array or non-object, return as-is
    }

    $is_object = trim($lines[0]) === 'stdClass Object';
    array_shift($lines); // Remove 'Array' or 'stdClass Object'
    array_shift($lines); // Remove '('
    array_pop($lines);   // Remove ')'

    $input = implode("\n", $lines);
    preg_match_all("/^\s{4}\[(.+?)\] \=\> /m", $input, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    $pos = [];
    $previous_key = '';
    $in_length = strlen($input);

    foreach ($matches as $match) {
        $key = $match[1][0];
        $start = $match[0][1] + strlen($match[0][0]);
        $pos[$key] = [$start, $in_length];
        if ($previous_key !== '') {
            $pos[$previous_key][1] = $match[0][1] - 1;
        }
        $previous_key = $key;
    }

    $result = [];
    foreach ($pos as $key => $where) {
        $result[$key] = print_r_reverse(substr($input, $where[0], $where[1] - $where[0]));
    }

    return $is_object ? (object)$result : $result;
}

/**
 * Converts seconds into a human-readable format (days, hours, minutes, seconds).
 *
 * @param int $seconds The number of seconds.
 * @param int $mode The output format mode.
 * @return string|array The formatted time string or array.
 */
function dhms($seconds, $mode = 1) {
    $days = floor($seconds / 86400);
    $hrs = floor($seconds / 3600);
    $mins = (int) ($seconds / 60) % 60;
    $sec = (int) ($seconds % 60);

    if ($days > 0) {
        $hrs = str_pad($hrs, 2, '0', STR_PAD_LEFT);
        $hours = $hrs - ($days * 24);
        $return_days = $days . "d";
        $hrs = str_pad($hours, 2, '0', STR_PAD_LEFT);
    } else {
        $return_days = "";
        $hrs = str_pad($hrs, 2, '0', STR_PAD_LEFT);
    }

    $mins = str_pad($mins, 2, '0', STR_PAD_LEFT);
    $sec = str_pad($sec, 2, '0', STR_PAD_LEFT);

    if ($mode !== 1) {
        return [
            "day" => $return_days === "" ? "00" : $days,
            "hour" => $hrs,
            "min" => $mins,
            "sec" => $sec
        ];
    }

    return $return_days . $hrs . "h" . $mins . "m" . $sec . "s";
}

/**
 * Returns a human-readable file size.
 *
 * @param string $path The file path.
 * @return string The formatted file size.
 */
function filesize_formatted($path) {
    $size = filesize($path);
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

?>
