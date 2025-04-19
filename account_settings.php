<?php
if(!isset($_GET['show_errors'])) {
    header('Content-type: application/json');
    error_reporting(0);
}

$response = ['error' => 'Unknown error occurred.'];

include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

if($loggedin === false) {
    header("HTTP/1.0 403 Forbidden");
    echo json_encode(['error' => 'Invalid sign in token provided', 'code' => '403']);
    exit;
}

function check_username_available($new) {
    global $users_row;
    global $conn;
    global $id;

    $available = '1';
    $reason = '0';

    if(empty($new) || $new === null) {
        $available = '0';
        $reason = 'Please provide a username.';
    }

    if(trim($new) === trim($users_row['username'])) {
        $available = '0';
        $reason = 'This is your current username.';
    }

    if(!ctype_alnum(str_replace(array('-', '_', '.'), '', $new))) {
        $available = '0';
        $reason = 'Username cannot contain anything other than A-Z, any number, or dash underscore and dot.';
    }

    if(strlen($new) > 15) {
        $available = '0';
        $reason = 'Username must be under 15 characters.';
    }

    if(strlen($new) < 2) {
        $available = '0';
        $reason = 'Username must be more than 2 characters.';
    }

    $result = $conn->query("SELECT * FROM users WHERE username = '$new'");
    $row = $result->fetch_assoc();

    if($result->num_rows != 0) {
        $available = '0';
        $reason = 'Username has been taken. Please choose another.';
    }

    if(!empty($users_row['changed'])) {
        $changed = $users_row['changed'];
        if (($changed - time()) < 86400) {
            $change_when = $changed - time();
            $reason = "You can change your username in " . date("H", $change_when) . " hours";
            $available = '0';
        }
    }

    return ['available' => $available, 'reason' => $reason];
}

if(isset($_GET['check_username_available'])) {
    $username = urldecode(htmlspecialchars($_GET['username']));
    $result = check_username_available($username);
    header("HTTP/1.0 200 OK");
    echo json_encode($result);
    exit;
}

function username_change($new) {
    global $users_row;
    global $conn;
    global $id;
    
    $result = check_username_available($new);
    if ($result['available'] === '0') {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $result['reason']]);
        exit;
    }

    $changed = time();

	$stmt = $conn->prepare("UPDATE users SET username = ?, changed = ? WHERE id = ?");
    $stmt->bind_param("sss", $new, $changed, $id);
    if ($stmt->execute()) {
        return ['success' => 'Username updated'];
    }
}

if(isset($_GET['username_change'])) {
    $username = urldecode(htmlspecialchars($_GET['username']));
    $result = username_change($username);
    header("HTTP/1.0 200 OK");
    echo json_encode($result);
    exit;
}


if(isset($_GET['twitter_change'])){
    $new = htmlspecialchars($_GET['handle']);
    $id = $token['user'];

    if(strlen($new) > 15) {
        $error_message = "Twitter handle's can only be 15 characters.";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }

	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

	$stmt_2 = $conn->prepare("UPDATE users SET twitter = ? WHERE id = ?");
    $stmt_2->bind_param("ss", $new, $id);
    if ($stmt_2->execute()) {
        echo "success";
        exit;
    }
}


if(isset($_GET['about_change'])){
    $new = urldecode(htmlspecialchars($_GET['description'], ENT_NOQUOTES));
    $id = $token['user'];

    if(strlen($new) > 200) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'About section can only be 200 characters.']);
        exit;
    }

	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
	$stmt_2 = $conn->prepare("UPDATE users SET description = ? WHERE id = ?");
    $stmt_2->bind_param("si", $new, $id);
    if ($stmt_2->execute()) {
        echo json_encode(['success' => 'success']);
        exit;
    } else {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Error changing about section.']);
        exit;
    }
    $stmt->close();
    header("HTTP/1.0 500 Internal Server Error");
    echo json_encode(['error' => 'Could not follow through any checks.']);
    exit;
}

if(isset($_GET['change'])) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    $old = md5($_GET['o_password']);
    $new = md5($_GET['n_password']);
    $confirm = md5($_GET['c_password']);
    $id = $token['user'];

    if($new == $confirm) {
        $sql = "SELECT * FROM users WHERE id = '$id'";
        $result = $conn->query($sql);
        $row = $result->fetch_assoc();

        if($result->num_rows != 0) {
            if($row['id'] === $id) {
                $sql2 = "UPDATE users SET password = '$new' WHERE id = '$id'";
                $result2 = $conn->query($sql2);

                if ($conn->query($sql2) === TRUE) {
                    header("HTTP/1.0 200 OK");
                    echo json_encode(['success' => 'Password updated']);
                    exit;
                } else {
                    $error_message = "Error changing password";
                    header("HTTP/1.0 500 Internal Server Error");
                    echo json_encode(['error' => $error_message]);
                    exit;
                }
            }
        }
    }
}

if (isset($_POST['change'])) {
    $oldPassword = $_POST['o_password'];
    $newPassword = $_POST['n_password'];
    $confirmPassword = $_POST['c_password'];
    $userid = $token['user'];

    if ($newPassword !== $confirmPassword) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => "New passwords do not match"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT password, salt FROM users WHERE id = ?");
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $stmt->bind_result($storedHash, $salt);
    $stmt->fetch();
    $stmt->close();

    if ($storedHash) {
        if ($storedHash === md5($oldPassword . $salt)) {
            $newSalt = bin2hex(random_bytes(16));
            $newHashedPassword = md5($newPassword . $newSalt);

            $stmt = $conn->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newHashedPassword, $newSalt, $userid);

            if ($stmt->execute()) {
                header("HTTP/1.0 200 OK");
                echo json_encode(['success' => 'Congrats! Your password was updated with success!']);
                exit;
            } else {
                header("HTTP/1.0 500 Internal Server Error");
                echo json_encode(['error' => "Unknown error changing password."]);
                exit;
            }
            $stmt->close();
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => "Incorrect old password."]);
            exit;
        }
    } else {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => "Account not found."]);
        exit;
    }
}

if(isset($_GET['email_change'])){
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $id = $token['user'];
    $new = $_POST['email'];

    $sql = "SELECT * FROM users WHERE id = '$id'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if($result->num_rows != 0) {
        if($row['id'] === $id) {
            $sql2 = "UPDATE users SET email = '$new' WHERE id = '$id'";
            $result2 = $conn->query($sql2);

            if ($conn->query($sql2) === TRUE) {
                header("HTTP/1.0 200 OK");
                echo json_encode(['success' => 'Email updated']);
                exit;
            } else {
                $error_message = "Error changing email";
                header("HTTP/1.0 500 Internal Server Error");
                echo json_encode(['error' => $error_message]);
                exit;
            }
        }
    }
}

if(isset($_GET['clear_notifications'])) {
    header('Content-Type: application/json');
    $id = (int)$token['user'];
	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
	$alertnum = 0;
	$stmt_2 = $conn->prepare("UPDATE users SET alert = ? WHERE id = ? LIMIT 1");
	$stmt_2->bind_param("ii", $alertnum, $id);

	if ($stmt_2->execute()) {
        header("HTTP/1.0 200 OK");
        echo json_encode(['success' => 'Cleared inbox notifications. Would you like to reload the web page?']);
        exit;
	} else {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Error clearing inbox notifications.']);
        exit;
	}

	$stmt_2->close();
	exit;
}

if (isset($_POST['logout']) && isset($_POST['token'])) {
    header('Content-Type: application/json');
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $sessionid = $_COOKIE['token'];
    
    $stmt = $conn->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->bind_param("s", $sessionid);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows != 0 && $sessionid === $_POST['token']) {
        $stmt2 = $conn->prepare("DELETE FROM sessions WHERE id = ? LIMIT 1");
        $stmt2->bind_param("s", $sessionid);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION = [];
        setcookie('token', '', time() - 3600, "/", ".gr8brik.rf.gd");

        header('Location:/list.php?invalid_login=true');
        exit;
    } else {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Invalid session token']);
        exit;
    }
}

if (isset($_POST['delete_account'])) {
    header('Content-Type: application/json');
    $id = $token['user'];
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
    $conn3 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME3);

    if ($conn->connect_error) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $conn->connect_error]);
        exit;
    }

    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if (isset($_COOKIE['token']) && $tokendata->num_rows != 0 && $stmt_check->num_rows != 0) {
        $stmt_check->close();

        $stmt_forum = $conn3->prepare("UPDATE posts, replies SET posts.user = 0, replies.user = 0 WHERE posts.user = ? OR replies.user = ?");
        $stmt_forum->bind_param("ii", $id, $id);
        $stmt_forum->execute();
        $stmt_forum->close();

        $stmt_models = $conn2->prepare("DELETE FROM model WHERE user = ?");
        $stmt_models->bind_param("i", $id);
        $stmt_models->execute();
        $stmt_models->close();

        $stmt_comments = $conn2->prepare("DELETE FROM comments WHERE user = ?");
        $stmt_comments->bind_param("i", $id);
        $stmt_comments->execute();
        $stmt_comments->close();

        $stmt_follows = $conn->prepare("DELETE FROM follow WHERE userid = ? OR profileid = ?");
        $stmt_follows->bind_param("ii", $id, $id);
        $stmt_follows->execute();
        $stmt_follows->close();

        $stmt_blocks = $conn->prepare("DELETE FROM user_blocks WHERE userid = ? OR profileid = ?");
        $stmt_blocks->bind_param("ii", $id, $id);
        $stmt_blocks->execute();
        $stmt_blocks->close();

        $stmt_notif = $conn->prepare("DELETE FROM notifications WHERE user = ? OR profile = ?");
        $stmt_notif->bind_param("ii", $id, $id);
        $stmt_notif->execute();
        $stmt_notif->close();

        $stmt_bans = $conn->prepare("DELETE FROM bans WHERE user = ?");
        $stmt_bans->bind_param("i", $id);
        $stmt_bans->execute();
        $stmt_bans->close();

        $stmt_appeals = $conn->prepare("DELETE FROM appeals WHERE user = ?");
        $stmt_appeals->bind_param("i", $id);
        $stmt_appeals->execute();
        $stmt_appeals->close();

        $extensions = ['jpg', 'png', 'jpeg', 'webp'];
        foreach ($extensions as $ext) {
            $file = "../acc/users/pfps/$id.$ext";
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $stmt_users = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_users->bind_param("i", $id);
        
        $stmt_sessions = $conn->prepare("DELETE FROM sessions WHERE user = ?");
        $stmt_sessions->bind_param("i", $id);
        $stmt_sessions->execute();
        $stmt_sessions->close();

        $_SESSION = [];
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        setcookie('token', '', time() - 3600, '/');

        if ($stmt_users->execute()) {
            header("HTTP/1.0 200 OK");
            setcookie("token", "", time() - 3600, "/");
            echo json_encode(['success' => 'Your account has been permanently deleted from our servers.']);
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => 'Something went wrong while deleting your account. ' . $stmt_users->error]);
        }
        $stmt_users->close();
    } else {
        header("HTTP/1.0 404 Not Found");
        setcookie("token", "", time() - 3600, "/");
        $stmt_check->close();
        echo json_encode(['error' => "Hmm, we couldn't find your account."]);
    }
    $conn->close();
    $conn2->close();
    exit;
}
echo json_encode($response);
exit;
?>