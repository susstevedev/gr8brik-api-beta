<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/constants.php';
session_start();
$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
$loggedin = loggedin();

if(isset($_GET['ajax'])) {
    error_reporting(0);
}

if(loggedin() === true) {
    if (rand(1, 10) === 1) {
        //regenerate_session();
        delete_old_sessions();
    }
}

/*if(isset($_COOKIE['token'])) {
    $session_statement = $conn->prepare("SELECT user, timestamp FROM sessions WHERE id = ?");
    $session_statement->bind_param("s", $_COOKIE['token']);
    $session_statement->execute();
    $tokendata = $session_statement->get_result();

    if($tokendata->num_rows === 0) {
        if ($_SERVER['PHP_SELF'] !== '/list.php') {
            $reason = "of an invalid session ID";
            logout($reason, true);
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

            if(!empty($users_row['deactive'])) {
                $reason = "your account was deactivated";
                logout($reason, true);
            }

            $ban_stmt = $conn->prepare("SELECT * FROM bans WHERE user = ? LIMIT 1");
            $ban_stmt->bind_param("i", $id);
            $ban_stmt->execute();
            $ban_result = $ban_stmt->get_result();
            $ban_stmt->close();

            if ($_SERVER['PHP_SELF'] !== '/list.php') {
                if ($ban_result->num_rows > 0) {
                    $ban_data = $ban_result->fetch_assoc();
                    if ($ban_data['end_date'] !== null && $ban_data['end_date'] >= time()) {
                        $reason = "your account has been banned";
                        logout($reason, true);
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
            $loggedin = loggedin();

            $logindata = json_encode([
                'success' => true, 
                'loggedin' => loggedin(), 
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
            ]);

            if(isset($_GET['ajax'])) {
                header('Content-type: application/json');
                echo $logindata;
                exit;
            }
        } else {
            if(isset($_GET['ajax'])) {
                header("HTTP/1.0 403 Forbidden");
                echo json_encode(['error' => 'no user was account found']);
                exit;
            }
            $reason = "your account was deleted";
            logout($reason, true);
        }
    }
} */

function login() {
    global $conn, $id, $token, $tokendata, $users_row;

    if (loggedin() === true) {
        $sessionid = $_COOKIE['token'];

        $session_stmt = $conn->prepare("SELECT user, timestamp FROM sessions WHERE id = ?");
        $session_stmt->bind_param("s", $sessionid);
        $session_stmt->execute();
        $tokendata = $session_stmt->get_result();

        if($tokendata->num_rows != 0) {
            $token = $tokendata->fetch_assoc();
            $id = $token['user'];

            $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $id);
            $user_stmt->execute();
            $user_res = $user_stmt->get_result();

            if($user_res->num_rows != 0) {
                global $user, $pwd, $email, $about, $x, $admin, $alert, $age, $changed, $loggedin;

                $users_row = $user_res->fetch_assoc();
                $user = $users_row['username'];
                $pwd = $users_row['password'];
                $email = $users_row['email'];
                $about = $users_row['description'];
                $x = $users_row['twitter'];
                $admin = (string)$users_row['admin'];
                $alert = (int)$users_row['alert'];
                $age = $users_row['age'];
                $changed = $users_row['changed'];
                $loggedin = loggedin();

                return true;
            }
        }
    }

    return false;
}
login();

if(isset($_GET['ajax'])) {
    header('Content-type: application/json');
    if(loggedin() === true) {
        $logindata = json_encode([
            'success' => true, 
            'loggedin' => true, 
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
        ]);
        echo $logindata;
        exit;
    } else {
        echo json_encode(['error' => 'User is not authenticated']);
        exit;
    }
}

function logout($reason, $redirect) {
    global $conn, $token;
    $sessionid = $token['id'];

    if (isset($sessionid)) {
        $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $sessionid);
        $stmt->execute();
        $stmt->close();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
    }

    if(isset($_COOKIE['token'])) {
        unset($_COOKIE['token']); 
        setcookie('token', '', time()-3600, "/", ".gr8brik.rf.gd");
    }

    if(isset($_GET['ajax'])) {
        header("HTTP/1.0 403 Forbidden");
        echo json_encode(['error' => 'Invalid session token provided']);
        exit;
    }

    if($redirect === true) {
        header('Location:/list.php?invalid_login=true&reason=' . urlencode($reason));
        exit;
    }
}

function regenerate_session() {
    global $conn;

    if (loggedin() === true) {
        $oldid = $_COOKIE['token'];

        $stmt = $conn->prepare("SELECT timestamp FROM sessions WHERE id = ? LIMIT 1");
        $stmt->bind_param("s", $oldid);
        $stmt->execute();
        $res = $stmt->get_result();

        if($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $newid = bin2hex(random_bytes(16));
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $timestamp =  time()+(60 * 60 * 24 * 400);

            $stmt2 = $conn->prepare("UPDATE sessions SET id = ?, timestamp = ?, login_from = ? WHERE id = ?");
            $stmt2->bind_param("siss", $newid, $timestamp, $oldid, $user_ip);
            if ($stmt2->execute()) {
                setcookie('token', $newid, $timestamp, "/", ".gr8brik.rf.gd");
                $_COOKIE['token'] = $newid;
                return true;
            }
        }
    }
    return false;
}

function delete_old_sessions() {
    global $conn;
    if (loggedin()) {
        $expiry = time() - (60 * 60 * 24 * 30);
        $old_sessions_stmt = $conn->prepare("DELETE FROM sessions WHERE timestamp < ?");
        $old_sessions_stmt->bind_param("i", $expiry);
        $old_sessions_stmt->execute();
        $old_sessions_stmt->close();
        return true;
    }
    return false;
}

function loggedin() {
    global $conn;

    if (isset($_COOKIE['token'])) {
        $session_stmt = $conn->prepare("SELECT * FROM sessions WHERE id = ?");
        $session_stmt->bind_param("s", $_COOKIE['token']);
        $session_stmt->execute();
        $session_res = $session_stmt->get_result();

        if($session_res->num_rows !== 0) {
            $session = $session_res->fetch_assoc();
            $userid = $session['user'];

            $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $userid);
            $user_stmt->execute();
            $user_res = $user_stmt->get_result();

            if($user_res->num_rows !== 0) {
                $user_data = $user_res->fetch_assoc();

                if(!empty($user_row['deactive'])) {
                    return false;
                }

                $ban_stmt = $conn->prepare("SELECT * FROM bans WHERE user = ? LIMIT 1");
                $ban_stmt->bind_param("i", $userid);
                $ban_stmt->execute();
                $ban_res = $ban_stmt->get_result();

                if ($ban_res->num_rows !== 0) {
                    $ban_data = $ban_res->fetch_assoc();
                    if ($ban_data['end_date'] !== null && $ban_data['end_date'] >= time()) {
                        return false;
                    }
                }
            }

            return true;
        }
    }

    return false;
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