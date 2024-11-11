<?php
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;

    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629800);
    $years = round($seconds / 31556926);

    if ($seconds < 60) {
        return "$seconds seconds ago";
    } else if ($minutes < 60) {
        return "$minutes minutes ago";
    } else if ($hours < 24) {
        return "$hours hours ago";
    } else if ($days < 7) {
        return "$days days ago";
    } else if ($weeks < 4) {
        return "$weeks weeks ago";
    } else if ($months < 12) {
        return "$months months ago";
    } else {
        return "$years years ago";
    }
}
?>