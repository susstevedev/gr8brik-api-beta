<?php
ob_start();

// constants
require_once 'constants.php';

/* session_name('loginid');
ini_set('session_save_path', $_SERVER['DOCUMENT_ROOT'] . '/acc/users/sessions/');
session_start(); */

function sess_open($save_path, $session_name) {
    global $sess_db;
    $sess_db = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    if ($sess_db->connect_error) {
        echo "Session DB connection failed: " . $sess_db->connect_error;
        return false;
    }
    return true;
}

function sess_close() {
    global $sess_db;
    return $sess_db->close();
}

function sess_read($id) {
    global $sess_db;
    $stmt = $sess_db->prepare("SELECT data FROM php_sessions WHERE id = ?");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $stmt->bind_result($data);
    $stmt->fetch();
    return $data ?? '';
}

function sess_write($id, $data) {
    global $sess_db;
    $time = time();
    $ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $sess_db->prepare("REPLACE INTO php_sessions (id, data, timestamp, ip) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $id, $data, $time, $ip);
    return $stmt->execute();
}

function sess_destroy($id) {
    global $sess_db;
    $stmt = $sess_db->prepare("DELETE FROM php_sessions WHERE id = ?");
    $stmt->bind_param("s", $id);
    return $stmt->execute();
}

function sess_gc($maxlifetime) {
    global $sess_db;
    $old = time() - $maxlifetime;
    $stmt = $sess_db->prepare("DELETE FROM php_sessions WHERE timestamp < ?");
    $stmt->bind_param("i", $old);
    return $stmt->execute();
}

session_set_save_handler(
    'sess_open',
    'sess_close',
    'sess_read',
    'sess_write',
    'sess_destroy',
    'sess_gc'
);

register_shutdown_function('session_write_close');
ini_set('session.gc_maxlifetime', 1000);
ini_set('session.gc_probability', 5);
session_start();

if (!isset($_SESSION['requests'])) {
    $_SESSION['requests'] = [];
}

$_SESSION['requests'] = array_filter($_SESSION['requests'], function ($timestamp) {
    return $timestamp > time() - 60;
});

if (count($_SESSION['requests']) >= 60) {
    $oldest = min($_SESSION['requests']);
    $remaining = 60 - (time() - $oldest);
    $requests = $_SESSION['requests'];

    http_response_code(429);
    header("Retry-After: " . $remaining);
    $error = 'You are sending too many requests. Please wait ' .  $remaining . ' seconds.';
    if(isset($_GET['ajax'])) {
        echo json_encode(['error' => $error, 'requests' => $requests]);
    } else {
        echo 'Error: ' . $error;
    }
    exit;
}

$_SESSION['requests'][] = time();

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(128));
}

define('csrf', $_SESSION['csrf']);

if (isset($_GET['get_csrf_token'])) {
    echo json_encode(['csrf_token' => $_SESSION['csrf']]);
    exit;
}
?>