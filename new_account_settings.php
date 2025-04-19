<?php
if(!isset($_GET['show_errors'])) {
    header('Content-type: application/json');
    error_reporting(0);
}
include $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
    header("HTTP/1.0 403 Forbidden");
    echo json_encode(['error' => 'Encryption is required for this action']);
    exit;
}

$sessionid = $_COOKIE['token'];
$stmt = $conn->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->bind_param("s", $sessionid);
$stmt->execute();
$tokendata = $stmt->get_result();
$token = $tokendata->fetch_assoc();
$stmt->close();

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

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $new);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

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

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $new);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

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
    $new = $_GET['description'];
    $id = $token['user'];

    if(strlen($new) > 200) {
        $error_message = "Bio can only be 200 characters.";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }

    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $stmt_2 = $conn->prepare("UPDATE users SET description = ? WHERE id = ?");
    $stmt_2->bind_param("ss", $new, $id);
    if ($stmt_2->execute()) {
        echo "success";
        exit;
    } else {
        $error_message = "Error changing bio.";
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $error_message]);
        exit;
    }
    $stmt->close();
    exit;
}

if(isset($_GET['change'])){
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    $old = md5($_GET['o_password']);
    $new = md5($_GET['n_password']);
    $confirm = md5($_GET['c_password']);
    $id = $token['user'];

    if($new == $confirm) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if($result->num_rows != 0) {
            if($row['id'] === $id) {
                $stmt_2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_2->bind_param("ss", $new, $id);
                if ($stmt_2->execute()) {
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

if(isset($_GET['email_change'])){
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
    $id = $token['user'];
    $new = $_POST['email'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?"); // Prepared statement
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if($result->num_rows != 0) {
        if($row['id'] === $id) {
            $stmt_2 = $conn->prepare("UPDATE users SET email = ? WHERE id = ?"); // Prepared statement
            $stmt_2->bind_param("ss", $new, $id);
            if ($stmt_2->execute()) {
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
	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

	$stmt = $conn->prepare("SELECT alert FROM users WHERE id = ?");
	$stmt->bind_param("i", $id);
	$stmt->execute();
	$stmt->bind_result($db_alert);
	$stmt->fetch();
    if($stmt->num_rows === 0) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Could not find user account.']);
        exit;
    }
	$stmt->close();
	$alertnum = 0;
    if($db_alert === 0 || empty($db_alert) || $db_alert === null) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'There are no inbox notifications to be cleared.']);
        exit;
    }
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
	$stmt->close();
	die;
}

if (isset($_POST['delete_account'])) {
    header('Content-Type: application/json');
    $id = $token['user'];
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => $conn->connect_error]);
        exit;
    }

    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if(isset($_COOKIE['token']) && $tokendata->num_rows != 0 && $stmt_check->num_rows != 0) {
        $stmt_check->close();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("HTTP/1.0 200 OK");
            setcookie("token", "", time() - 3600, "/");
            echo json_encode(['success' => 'Your account has been permanently deleted.']);
            exit;
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            echo json_encode(['error' => 'Something went wrong while deleting your account. ' . $stmt->error]);
            exit;
        }

        $stmt->close();
    } else {
        header("HTTP/1.0 404 Not Found");
        setcookie("token", "", time() - 3600, "/");
        $stmt_check->close();
        echo json_encode(['error' => "Hmm, we couldn't find your account."]);
        exit;
    }
    $conn->close();
}
?>