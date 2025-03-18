<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/constants.php';
session_start();
$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
if(isset($_GET['ajax'])) {
    error_reporting(0);
}

$loggedin = false;

if(isset($_COOKIE['token'])) {
    $session_statement = $conn->prepare("SELECT user, username, password, timestamp FROM sessions WHERE id = ?");
    $session_statement->bind_param("s", $_COOKIE['token']);
    $session_statement->execute();
    $tokendata = $session_statement->get_result();

    if($tokendata->num_rows === 0) {
        unset($_COOKIE['token']); 
        setcookie('token', null, $time - 3600, "/", ".gr8brik.rf.gd");
        if(isset($_GET['ajax'])) {
            header("HTTP/1.0 403 Forbidden");
            echo json_encode(['error' => 'invalid login token provided']);
            exit;
        }
        header('Location:/list.php?invalid_login=true&reason=' . urlencode('because your signin expired'));
        exit;
    } else {
        if(!isset($_SESSION['username'])) {
            $_SESSION['username'] = $conn->real_escape_string($token['user']);
            header("Refresh:0");
        }
        $token = $tokendata->fetch_assoc();
        $id = $token['user'];

        $users_statement = $conn->prepare("SELECT username, password, email, description, twitter, admin, alert, age, changed FROM users WHERE id = ?");
        $users_statement->bind_param("i", $id);
        $users_statement->execute();
        $users_result = $users_statement->get_result();

        if($users_result->num_rows != 0) {
            $users_row = $users_result->fetch_assoc();

            $user = $users_row['username'];
            $pwd = $users_row['password'];
            $email = $users_row['email'];
            $about = $users_row['description'];
            $x = $users_row['twitter'];
            $admin = (string)$users_row['admin'];
            $alert = (int)$users_row['alert'];
            $age = $users_row['age'];
            $changed = $users_row['changed'];
            $pic = '/acc/users/pfps/' . $id . '.jpg';
            $loggedin = true;

            $logindata = json_encode([
                'success' => 'You are logged in', 
                'loggedin' => $loggedin, 
                'requested' => time(), 
                'token' => $conn->real_escape_string($_COOKIE['token']), 
                'id' => $id,  
                'user' => $user, 
                'pwd' => $pwd, 
                'email' => $email, 
                'about' => $about, 
                'x' => $x, 
                'admin' => $admin, 
                'alert' => $alert, 
                'age' => $age, 
                'changed' => $changed, 
                'pic' => $pic, 
            ]);

            if(isset($_GET['ajax'])) {
                header('Content-type: application/json');
                echo $logindata;
                exit;
            }
        } else {
            if(isset($_GET['ajax'])) {
                header("HTTP/1.0 403 Forbidden");
                echo json_encode(['error' => 'no user account found']);
                exit;
            }
        }
    }
}

    function logout() {
        global $conn;
        global $sessionid;

        if ($sessionid !== null) {
            $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ? LIMIT 1");
            $stmt->bind_param("s", $sessionid);
            $stmt->execute();
            $stmt->close();
        }
        setcookie("token", "", time() - 3600, "/");
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    function isLoggedin() {
        global $tokendata;

        if (isset($_COOKIE['token'])) {
            if ($tokendata && $tokendata->num_rows != 0) {
                if ($_SERVER['PHP_SELF'] === '/acc/login.php' || $_SERVER['PHP_SELF'] === '/acc/register.php' || $_SERVER['PHP_SELF'] === '/index.php') {
                    header('Location: /list');
                    exit;
                }
            } else {
                setcookie("token", "", time() - 3600, "/");
                if ($_SERVER['PHP_SELF'] != '/acc/login.php' && $_SERVER['PHP_SELF'] != '/acc/register.php' && $_SERVER['PHP_SELF'] != '/index.php') {
                    header('Location: /acc/login.php');
                    exit;
                }
            }
        } else {
            if ($_SERVER['PHP_SELF'] != '/acc/login.php' && $_SERVER['PHP_SELF'] != '/acc/register.php' && $_SERVER['PHP_SELF'] != '/index.php') {
                header('Location: /acc/login.php');
                exit;
            }
        }
    }
?>