<?php
if(!isset($_GET['show_errors'])) {
    header('Content-type: application/json');
    error_reporting(0);
}

$response = ['error' => 'Unknown error occurred.'];

include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
    header("HTTP/1.0 403 Forbidden");
    echo json_encode(['error' => 'Encryption is required for this action']);
    exit;
}

    $sessionid = $_COOKIE['token'];
    $sql = "SELECT * FROM sessions WHERE id = '$sessionid'";
    $tokendata = $conn->query($sql);
    $token = $tokendata->fetch_assoc();

    if(!isset($_COOKIE['token']) || $tokendata->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid sign in token provided', 'code' => '403']);
        exit;
    }

if(isset($_GET['check_username_available'])) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $new = urldecode(htmlspecialchars($_GET['username']));
    $id = $token['user'];
    $available = '1';

    if(empty($new)) {
        $available = '0';
    }

    if(!ctype_alnum(str_replace(array('-', '_', '.'), '', $new))) {
        $available = '0';
    }

    if(strlen($new) > 15) {
        $available = '0';
    }

    if(strlen($new) < 2) {
        $available = '0';
    }

    $result = $conn->query("SELECT * FROM users WHERE username = '$new'");
    $row = $result->fetch_assoc();

    if($result->num_rows != 0) {
        $available = '0';
    }

    header("HTTP/1.0 200 OK");
    echo json_encode(['available' => $available]);
    exit;
}

if(isset($_GET['username_change'])) {
    $new = urldecode(htmlspecialchars($_GET['username']));
    $id = $token['user'];

    if(empty($new)) {
        $error_message = "Username cannot be blank";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }    
    if(!ctype_alnum(str_replace(array('-', '_', '.'), '', $new))) {
        $error_message = "Username has invalid characters";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }
    if(strlen($new) > 15) {
        $error_message = "Username can only be 15 characters";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }
    if(strlen($new) < 2) {
        $error_message = "Username must be more than 2 characters";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }
    if(!empty($changed)) {
        if (($changed - time()) < 86400) {
            $change_when = $changed - time();
            $error_message = "You can change your username in " . date("H", $change_when) . " hours";
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => $error_message]);
            exit;
        }
    }

	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    $sql = "SELECT * FROM users WHERE username = '$new'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if($result->num_rows != 0) {
        if($row['id'] === $id) {
            $error_message = "This is your current username";
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => $error_message]);
            exit;
        }
        $error_message = "Username is taken";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }

    $changed = time();

	$stmt_2 = $conn->prepare("UPDATE users SET username = ?, changed = ? WHERE id = ?");
    $stmt_2->bind_param("sss", $new, $changed, $id);
    if ($stmt_2->execute()) {
        header("HTTP/1.0 200 OK");
        echo json_encode(['success' => 'Username updated']);
        exit;
    }
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

if(isset($_GET['change'])){
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
    $userId = $token['user'];

    if ($newPassword !== $confirmPassword) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => "New passwords do not match"]);
        exit;
    }

    $stmt = $conn->prepare("select password, salt from users where id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($storedHash, $salt);
    $stmt->fetch();
    $stmt->close();

    if ($storedHash) {
        if ($storedHash === md5($oldPassword . $salt)) {
            $newSalt = uniqid('', true);
            $newHashedPassword = md5($newPassword . $newSalt);

            $stmt = $conn->prepare("update users set password = ?, salt = ? WHERE id = ?");
            $stmt->bind_param("ssi", $newHashedPassword, $newSalt, $userId);

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

if (isset($_POST['delete_account'])) {
    header('Content-Type: application/json');
    $id = $token['user'];
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);

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

        $stmt_models = $conn2->prepare("DELETE FROM model WHERE user = ?");
        $stmt_models->bind_param("i", $id);
        $stmt_models->execute();
        $stmt_models->close();

        $stmt_comments = $conn2->prepare("DELETE FROM comments WHERE user = ?");
        $stmt_comments->bind_param("i", $id);
        $stmt_comments->execute();
        $stmt_comments->close();

        $stmt_sessions = $conn->prepare("DELETE FROM sessions WHERE user = ?");
        $stmt_sessions->bind_param("i", $id);
        $stmt_sessions->execute();
        $stmt_sessions->close();

        $stmt_users = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_users->bind_param("i", $id);

        if ($stmt_users->execute()) {
            header("HTTP/1.0 200 OK");
            setcookie("token", "", time() - 3600, "/");
            echo json_encode(['success' => 'Your account has been permanently deleted.']);
            exit;
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => 'Something went wrong while deleting your account. ' . $stmt_users->error]);
            exit;
        }

        $stmt_users->close();
    } else {
        header("HTTP/1.0 404 Not Found");
        setcookie("token", "", time() - 3600, "/");
        $stmt_check->close();
        echo json_encode(['error' => "Hmm, we couldn't find your account."]);
        exit;
    }
    $conn->close();
}
echo json_encode($response);
exit;
?>