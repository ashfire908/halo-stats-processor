<?php
// Shared code

// Settings
define('METADATA_FILE', './.local_metadata');

// True/False boolean to Yes/No string
function btt($var) {
    if ($var === true) {
        return 'Yes';
    } elseif ($var === false) {
        return 'No';
    }
}

// Format time period
function tperiod($seconds) {
    $hours = (int) ($seconds / 3600);
    $minutes = (int) ($seconds / 60 - $hours * 60);
    $seconds = $seconds % 60;
    if ($hours > 0) {
        if ($minutes < 10) {
            $minutes = '0' . (string) $minutes;
        }
        if ($seconds < 10) {
            $seconds = '0' . (string) $seconds;
        }
        return "$hours:$minutes:$seconds";
    } elseif ($minutes > 0) {
        if ($seconds < 10) {
            $seconds = '0' . (string) $seconds;
        }
        return "$minutes:$seconds";
    } else {
        return $seconds;
    }
}
