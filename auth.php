<?php
/*
    2025 Gr8brik team
    This page, auth.php, is exactly what it sounds like: an authentication system used by Gr8brik
    TODO: Add prepared statements without using OO (object oriented) MySQLi
*/

header('Content-type: application/json');
error_reporting(0);
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';

if (isset($_GET['revoke']) && isset($_GET['tokenId'])) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "db connection failed"]);
        exit;
    }

    $session = $_GET['tokenId'];

    $check = $conn->prepare("SELECT id FROM sessions WHERE id = ?");
    $check->bind_param("s", $session);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        echo json_encode(['error' => "Session could not be found or selected."]);
        exit;
    } elseif($_COOKIE['token'] === $session) {
        echo json_encode(['error' => "Session could not be deleted as it is the session currently being used."]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ? LIMIT 1");
    $stmt->bind_param("s", $session);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Session deleted with success."]);
        exit;
    } else {
        header('HTTP/1.0 404 Not Found');
        echo json_encode(['error' => "Could not delete session."]);
        exit;
    }

    $stmt->close();
    $conn->close();
    exit;
}

function login_user($user, $pwd) {
    $conn = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    if (mysqli_connect_errno()) {
        header('HTTP/1.0 500 Internal Server Error');
        return ['error' => "Database connection failed"];
    }       

    $user = mysqli_real_escape_string($conn, $user);
    $pwd = mysqli_real_escape_string($conn, $pwd);

    if (empty($user)) {
        header('HTTP/1.0 500 Internal Server Error');
        return ['error' => "Email or username cannot be blank"];
    }

    $sql = "SELECT * FROM users WHERE email = '$user' OR username = '$user' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    if (mysqli_num_rows($result) > 0) {
        // For accounts created before Feb of 2025
        // I plan to remove this in a later iteration of the website
        if (!empty($row['salt'])) {
            $db_hashed_pwd = md5($pwd . $row['salt']);
        } else {
            $db_hashed_pwd = md5($pwd);
        }

        if ($db_hashed_pwd === $row['password']) {
            $userid = $row['id'];
            $login_from = $_SERVER['REMOTE_ADDR'];
            $tokenid = bin2hex(random_bytes(16));
            $time = time();

            // For account deactivation (soft deletion until a certain date)
            if (!empty($row['deactive'])) {
                if ($_SERVER['REQUEST_TIME'] - 2592000 < strtotime($row['deactive'])) {
                    $sql = "INSERT INTO sessions (id, user, password, timestamp) VALUES ('$tokenid', '$userid', '$pwd', '$time') LIMIT 1";
                    if (mysqli_query($conn, $sql)) {
                        header('HTTP/1.0 500 Internal Server Error');
                        return ['popup' => "Do you want to reactivate your account?", 'error' => "See modal for more info", 'goto' => "http://www.gr8brik.rf.gd/acc/index?reactive=1&token=" . $tokenid];
                    }
                } else {
                    header('HTTP/1.0 500 Internal Server Error');
                    return ['error' => "This account will be deleted"];
                }
            }

            // Account ban system
            $sql = "SELECT * FROM bans WHERE user = $userid AND end_date >= UNIX_TIMESTAMP() ORDER BY end_date DESC LIMIT 1";
            $result2 = mysqli_query($conn, $sql);

            while ($row2 = mysqli_fetch_assoc($result2)) {
                if (!empty($result2)) {
                    header('HTTP/1.0 500 Internal Server Error');
                    return ['error' => "This account has been banned until " . date("M d, Y H:i", $row2['end_date']) . ". " . htmlspecialchars($row2['reason'])];
                }
            }

            // Create login session
            $sql = "INSERT INTO sessions (id, login_from, user, password, timestamp) VALUES ('$tokenid', '$login_from', '$userid', '$db_hashed_pwd', '$time') LIMIT 1";
            if (mysqli_query($conn, $sql)) {
                setcookie('token', $tokenid, $time + (10 * 365 * 24 * 60 * 60), "/", ".gr8brik.rf.gd");
                $_SESSION['userid'] = $userid;
                return ['success' => true];
            }
        } else {
            header('HTTP/1.0 500 Internal Server Error');
            return ['error' => "Invalid password"];
        }
    } else {
        header('HTTP/1.0 500 Internal Server Error');
        return ['error' => "Invalid email or username"];
    }

    return ['error' => 'An error occured. Please try again later.'];
}

if (isset($_POST['login'])) {
    $login_user = login_user($_POST['mail'], $_POST['pwd']);
    echo json_encode($login_user);
    exit;
}

function register_user($username, $password, $email) {
    $conn = mysqli_connect(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    if (mysqli_connect_errno()) {
        header('HTTP/1.0 500 Internal Server Error');
        return ['error' => "Database connection failed"];
        exit;
    }

    $username = mysqli_real_escape_string($conn, htmlspecialchars($username));
    $salt = bin2hex(random_bytes(16));	
    $password = md5($password . $salt);
    $email = mysqli_real_escape_string($conn, $email);
    $created = date("Y-m-d");

    if (empty($password) || empty($email) || empty($username)) {
        header("HTTP/1.0 500 Internal Server Error");
        if (empty($password)) {
            return ['error' => "Password field is blank"];
        }
        if (empty($email)) {
            return ['error' => "Email field is blank"];
        }
        if (empty($username)) {
            return ['error' => "Username field is blank"];
        }
    }

    // Prevents re-registration for banned or deactivated accounts
    $sql_check = "SELECT id FROM users WHERE username = '$username' UNION ALL SELECT id FROM users WHERE email = '$email'";
    $stmt = mysqli_query($conn, $sql_check);

    if (mysqli_num_rows($stmt) > 0) {
        header("HTTP/1.0 500 Internal Server Error");
        return ['error' => "Username or email already in use"];
        exit;
    } else {
        $sql = "INSERT INTO users (username, password, salt, email, age) VALUES ('$username', '$password', '$salt', '$email', '$created') LIMIT 1";
        if (mysqli_query($conn, $sql)) {
            $sql = "SELECT id FROM users WHERE username = '$username'";
            $result = mysqli_query($conn, $sql);
            $row2 = mysqli_fetch_assoc($result);

            $userid = $row2['id'];
            $tokenid = bin2hex(random_bytes(16));
            $login_from = $_SERVER['REMOTE_ADDR'];
            $time = time();

            // Create session token
            $sql = "INSERT INTO sessions (id, login_from, user, password, timestamp) VALUES ('$tokenid', '$login_from', '$userid', '$password', '$time') LIMIT 1";
            if (mysqli_query($conn, $sql)) {
                setcookie('token', $tokenid, $time + (60 * 60 * 24 * 400), "/", ".gr8brik.rf.gd");
                $_SESSION['userid'] = $userid;
                return ['success' => true];
            }
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            return ['error' => "Error running register SQL query"];
        }
    }

    return ['error' => 'An error occured. Please try again later.'];  
}

if (isset($_POST['register'])) {
    $register_user = register_user(htmlspecialchars($_POST['name']), htmlspecialchars($_POST['pwd']), htmlspecialchars($_POST['mail']));
    echo json_encode($register_user);
    exit;
}

// Error if no query params where sent, or no function was ran
header('HTTP/1.0 500 Internal Server Error');
exit(json_encode(["error" => "An error occured. Please try again later."]));
?>