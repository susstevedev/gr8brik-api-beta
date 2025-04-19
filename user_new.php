<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/constants.php';
session_start();
$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
$loggedin = isLoggedin();

if(isset($_GET['ajax'])) {
    error_reporting(0);
}

function legacy_login() {
    $loggedin = false;

    if(isset($_COOKIE['token'])) {
        $session_statement = $conn->prepare("SELECT user, timestamp FROM sessions WHERE id = ?");
        $session_statement->bind_param("s", $_COOKIE['token']);
        $session_statement->execute();
        $tokendata = $session_statement->get_result();

        if($tokendata->num_rows === 0) {
            if ($_SERVER['PHP_SELF'] !== '/list.php') {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_unset();
                }

                setcookie('token', '', time() - 3600, "/", ".gr8brik.rf.gd");

                if(isset($_GET['ajax'])) {
                    header("HTTP/1.0 403 Forbidden");
                    echo json_encode(['error' => 'invalid login token provided']);
                    exit;
                }
                header('Location:/list.php?invalid_login=true&reason=' . urlencode('because your signin expired'));
                exit;
            }
        } else {
            $token = $tokendata->fetch_assoc();
            $id = $token['user'];

            $users_statement = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $users_statement->bind_param("i", $id);
            $users_statement->execute();
            $users_result = $users_statement->get_result();

            if($users_result->num_rows != 0) {
                $users_row = $users_result->fetch_assoc();

                $ban_stmt = $conn->prepare("SELECT * FROM bans WHERE user = ? LIMIT 1");
                $ban_stmt->bind_param("i", $id);
                $ban_stmt->execute();
                $ban_result = $ban_stmt->get_result();
                $ban_stmt->close();

                if ($_SERVER['PHP_SELF'] !== '/list.php') {
                    if ($ban_result->num_rows > 0) {
                        $ban_data = $ban_result->fetch_assoc();
                        if ($ban_data['end_date'] !== null && $ban_data['end_date'] >= time()) {
                            $delete_session = $conn->prepare("DELETE FROM sessions WHERE id = ?");
                            $delete_session->bind_param("s", $_COOKIE['token']);
                            $delete_session->execute();
                            $delete_session->close();
                        }
                    }
                }

                $user = $users_row['username'];
                $pwd = $users_row['password'];
                $email = $users_row['email'];
                $about = $users_row['description'];
                $x = $users_row['twitter'];
                $admin = (string)$users_row['admin'];
                $alert = (int)$users_row['alert'];
                $age = $users_row['age'];
                $changed = $users_row['changed'];
                $loggedin = true;

                /*$logindata = json_encode([
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
                    'changed' => $changed
                ]);*/

                $logindata = json_encode($users_row);

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
}

function login() {
    global $conn;
    global $loggedin;

    if($loggedin = true) {
        $id = $_SESSION['userid'];
        $token = ['user' => $id];

        $users_statement = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $users_statement->bind_param("i", $id);
        $users_statement->execute();
        $users_result = $users_statement->get_result();

        if($users_result->num_rows != 0) {
            $users_row = $users_result->fetch_assoc();

            $ban_stmt = $conn->prepare("SELECT * FROM bans WHERE user = ? LIMIT 1");
            $ban_stmt->bind_param("i", $id);
            $ban_stmt->execute();
            $ban_result = $ban_stmt->get_result();
            $ban_stmt->close();

            if ($ban_result->num_rows > 0) {
                $ban_data = $ban_result->fetch_assoc();
                if ($ban_data['end_date'] !== null && $ban_data['end_date'] >= time()) {
                    $_SESSION = [];
                    exit('Invalid user account');
                }
            } else {
                global $id, $user, $pwd, $email, $about, $x, $admin, $alert, $age, $changed;

                $id = $_SESSION['userid'];
                $user = $users_row['username'];
                $pwd = $users_row['password'];
                $email = $users_row['email'];
                $about = $users_row['description'];
                $x = $users_row['twitter'];
                $admin = (string)$users_row['admin'];
                $alert = (int)$users_row['alert'];
                $age = $users_row['age'];
                $changed = $users_row['changed'];

                $logindata = json_encode($users_row);
                $GLOBALS['users_row'] = $users_row;
                $GLOBALS['token'] = $token;
                $GLOBALS['tokendata'] = $users_row;
                
                if(isset($_GET['ajax'])) {
                    header('Content-type: application/json');
                    echo $logindata;
                    exit;
                }
            }
        }
    }
}
login();

function legacy_logout() {
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

function logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = array();
    }
}

function legacy_isLoggedin() {
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

function isLoggedin() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if(isset($_SESSION)) {
            if(isset($_SESSION['userid'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}
?>