<?php
error_reporting(0);
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/time.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
$bbcode = new BBCode;

$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
$conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

if (isset($_POST['save_build'])) {
    header('Content-Type: application/json');

    $modelJson = $_POST['creation'];

    if (empty($modelJson)) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "No model data received."]);
        exit;
    }

    $decoded_json = json_decode($modelJson, true);
    if ($modelJson === null && json_last_error() !== JSON_ERROR_NONE) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Invalid creation format."]);
        exit;
    }

    if (!isset($token['user'])) {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(['error' => "Please login to save models."]);
        exit;
    }

    $user = $token['user'];

    $sql = "SELECT * FROM users WHERE id = '$user'";
    $result = $conn2->query($sql);

    if ($result->num_rows < 0) {
        header("HTTP/1.1 401 Unauthorized");
        echo json_encode(['error' => "Error fetching user account."]);
        exit;
    }

    $row = $result->fetch_assoc();

    $sql2 = "SELECT * FROM bans WHERE user = '$user' LIMIT 1";
    $result2 = $conn2->query($sql2);

    if ($result2->num_rows > 0) {
        $row2 = $result2->fetch_assoc();
        if ($row2['end_date'] >= time()) {
            header("HTTP/1.0 403 Forbidden");
            echo json_encode(['error' => "User account has been banned, as such you cannot post new models."]);
            exit;
        }
    }

    $file_id = bin2hex(random_bytes(16));
    $file_name = "../cre/" . $file_id . ".json";
    $db_file_name = $file_id . ".json";
    $desc = htmlspecialchars($_POST['desc']);
    $name = htmlspecialchars($_POST['name']);
    $date = date("Y-m-d H:i:s");
    $screenshot_path = "/img/no_image.png";

    if (!empty($_POST['screenshot'])) {
        $screenshot_data = $_POST['screenshot'];

        if (strpos($screenshot_data, 'data:image/png;base64,') === 0) {
            $screenshot_data = base64_decode(substr($screenshot_data, strlen('data:image/png;base64,')));
            $screenshot_path = "../cre/" . bin2hex(random_bytes(16)) . ".png";

            if (!file_put_contents($screenshot_path, $screenshot_data)) {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(['error' => "Failed to save screenshot."]);
                exit;
            }
        }
    }

    if (file_put_contents($file_name, $modelJson) === false) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => "Failed to save model JSON to filesystem."]);
        exit;
    }

    if ($conn->connect_error) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => "Database connection failed."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO model (user, model, description, name, date, screenshot) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => "Failed to save your creation to the database."]);
        exit;
    }
    $stmt->bind_param("isssss", $user, $db_file_name, $desc, $name, $date, $screenshot_path);

    if (!$stmt->execute()) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => "Failed to save your creation to the database."]);
        exit;
    }

    $_SESSION['last_request'] = time();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => "Your creation was saved successfully!", 'screenshot' => $screenshot_path]);
    exit;
}

/*if($_GET['fetch']) {
    if ($conn->connect_error) {
        exit($conn->connect_error);
    }
    $model_id = $conn->real_escape_string($_GET['buildId']);

    if (!is_numeric($model_id)){
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "Invalid creation Id"]);
        exit;
    }

    $sql = "SELECT * FROM model WHERE id = '$model_id' AND removed = '0'";
    $result = $conn->query($sql);
    $row2 = $result->fetch_assoc();

    $userid = $row2['user'];
    $model = $row2['model'];
    $description = $row2['description'];
    $name = $row2['name'];
    $date = $row2['date'];
    $screenshot = $row2['screenshot'];
    $views = $row2['views'] + 1;
    $isRemoved = $row2['removed'];
    $decoded_description = htmlentities($description, ENT_QUOTES, 'UTF-8');
       
    if ($conn2->connect_error) {
        exit($conn2->connect_error);
    }

    if($result->num_rows === 0 || empty($row2['id'])) {
        header('HTTP/1.0 500 Internal Server Error');
        echo json_encode(['error' => "Creation not found"]);
        exit;
    }

    $sql = "SELECT * FROM users WHERE id = $userid";
    $result = $conn2->query($sql);
    $row = $result->fetch_assoc();

    $username = $row['username'];
    $model_admin = $row['admin'];
    $model_verified = $row['verified'];
    
    $result3 = $conn2->query("SELECT * FROM bans WHERE user = $userid");
    $row3 = $result3->fetch_assoc();

    while ($row3 = $result3->fetch_assoc()) {
        if ($result3->num_rows > 0 && $row3['end_date'] >= time()) {
            header('HTTP/1.0 500 Internal Server Error');
            echo json_encode(['error' => "This account is suspended"]);
            exit;
        }
    }

    if(isset($token['user'])) {
        $find_votes = $conn->query("SELECT * FROM votes WHERE user = '$id' AND creation = '$model_id' LIMIT 1");
        $vote_results = $find_votes->fetch_assoc();
        if ($find_votes->num_rows > 0) {
            $voted = true;
        } else {
            $voted = false;
        }
    }

    $count_result = $conn->query("SELECT COUNT(*) as vote_count FROM votes WHERE creation = $model_id");
    $count_row = $count_result->fetch_assoc();
    $sql = "UPDATE model SET views = '$views' WHERE id = '$model_id'";
    $result = $conn->query($sql);

    echo json_encode([
        'userid' => $row2['user'], 
        'modelid' => $model_id, 
        'model' => $row2['model'], 
        'description' => $row2['description'], 
        'name' => $row2['name'], 
        'date' => $row2['date'], 
        'screenshot' => $row2['screenshot'], 
        'views' => $views, 
        'isRemoved' => $row2['removed'], 
        'voted' => $voted, 
        'likes' => $count_row['vote_count'], 
        'decoded_description' => htmlentities($description, ENT_QUOTES, 'UTF-8'), 
        'username' => $row['username'], 
        'model_admin' => $row['admin'], 
        'model_verified' => $row['verified'], 
        'myUserId' => $token['user'] ?: false    
    ]);
    header("HTTP/1.0 200 OK");
    exit;
}*/


function fetch_build($model_id, $csrf) {
    global $users_row;
    global $tokendata;
    global $token;
    global $conn;
    global $conn2;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
    $bbcode = new BBCode();

    if (empty($csrf) || $csrf != $_SESSION['csrf']) {
        return json_encode([
            "message" => 'No CSRF token provided, or it is invalid!',
            "error" => 'INVALID_CSRF'
        ]);
    }
    
    if ($conn->connect_error) {
        exit($conn->connect_error);
    }

    if (!is_numeric($model_id)){
        return json_encode([
            "message" => 'Invalid ID provided for creation',
            "error" => 'INVALID_ID'
        ]);
    }

    $sql = "SELECT * FROM model WHERE id = '$model_id' AND removed = '0'";
    $result = $conn->query($sql);
    $row2 = $result->fetch_assoc();

    $userid = $row2['user'];
    $model = $row2['model'];
    $description = $row2['description'];
    $name = $row2['name'];
    $date = $row2['date'];
    $screenshot = $row2['screenshot'];
    $views = $row2['views'];
    $isRemoved = $row2['removed'];
    $decoded_description = htmlentities($description, ENT_QUOTES, 'UTF-8');
       
    if ($conn2->connect_error) {
        exit($conn2->connect_error);
    }

    if($result->num_rows === 0 || empty($row2['id'])) {
        header('HTTP/1.0 500 Internal Server Error');
        return json_encode([
            "message" => 'Creation not found',
            "error" => '404'
        ]);
    }

    $sql = "SELECT * FROM users WHERE id = $userid";
    $result = $conn2->query($sql);
    $row = $result->fetch_assoc();

    $username = $row['username'];
    $model_admin = $row['admin'];
    $model_verified = $row['verified'];
    
    $result3 = $conn2->query("SELECT * FROM bans WHERE user = $userid");
    $row3 = $result3->fetch_assoc();

    while ($row3 = $result3->fetch_assoc()) {
        if ($result3->num_rows > 0 && $row3['end_date'] >= time()) {
            header('HTTP/1.0 500 Internal Server Error');
            return json_encode([
                "message" => 'Creation could not load as the account that made it has been suspended',
                "error" => 'ACC_BANNED'
            ]);
        }
    }

    if(isset($token['user'])) {
        $id = $token['user'];

        $sql = "SELECT * FROM user_blocks WHERE userid = ? AND profileid = ? LIMIT 1";
        $stmt = $conn2->prepare($sql);
        $stmt->bind_param("ii", $userid, $id);
        $stmt->execute();
        $result4 = $stmt->get_result();
        $stmt->close();

        if ($result4->num_rows > 0) {
            header("HTTP/1.0 403 Forbidden");
            return json_encode([
                "message" => htmlspecialchars($row['username']) . " has blocked you.",
            ]);
        }

        $find_votes = $conn->query("SELECT * FROM votes WHERE user = '$id' AND creation = '$model_id' LIMIT 1");
        $vote_results = $find_votes->fetch_assoc();
        if ($find_votes->num_rows > 0) {
            $voted = true;
        } else {
            $voted = false;
        }
    }

    if (!isset($_SESSION['viewed_creation_ids'])) {
        $_SESSION['viewed_creation_ids'] = [];
    }

    if (!in_array($model_id, $_SESSION['viewed_creation_ids'])) {
        $view_stmt = $conn->prepare("UPDATE model SET views = views + 1 WHERE id = ?");
        $view_stmt->bind_param("i", $model_id);
        $view_stmt->execute();
        $_SESSION['viewed_creation_ids'][] = $model_id;
    }

    header("HTTP/1.0 200 OK");
    $message = "OK";
    $data = [
        'userid' => $row2['user'], 
        'modelid' => $model_id, 
        'model' => $row2['model'], 
        'description' => $row2['description'], 
        'name' => $row2['name'], 
        'date' => $row2['date'], 
        'screenshot' => $row2['screenshot'], 
        'views' => $views, 
        'isRemoved' => $row2['removed'], 
        'voted' => $voted, 
        'likes' => $count_row['vote_count'], 
        'decoded_description' => htmlentities($description, ENT_QUOTES, 'UTF-8'), 
        'username' => $row['username'], 
        'model_admin' => $row['admin'], 
        'model_verified' => $row['verified'], 
        'myUserId' => $token['user'] ?: false, 
        'message' => $message
    ];
    //return json_encode($data);
    return json_encode($data);
}

if(isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    $model_id = htmlspecialchars((int)$_GET['buildId']);
    $data = fetch_build($model_id, $_SESSION['csrf']);
    exit($data);
}

/*if($_GET['build_comments']) {
    header('Content-Type: application/json');

    $model_id = $_GET['buildId'];
	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$sql = "SELECT DISTINCT * FROM comments WHERE model = $model_id ORDER BY id ASC";
	$comResult = $conn->query($sql);

    $count_result = $conn->query("SELECT COUNT(*) as reply_count FROM comments WHERE model = $model_id");
    $count_row = $count_result->fetch_assoc();

    $count = 0;
	while ($row = $comResult->fetch_assoc()) {
        $comment_id = $row['id'];
        $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
        if ($conn2->connect_error) {
            exit($conn2->connect_error);
        }

        $voted_query = $conn->query("SELECT * FROM comment_votes WHERE comment_id = '$comment_id'");
        $comment_votes = 0;
        $is_voted = false;
        while ($voted_row = $voted_query->fetch_assoc()) {
            $comment_votes++;
            if ($loggedin === true) {
                if ((int)$voted_row['user_id'] === (int)$id) {
                    $is_voted = true;
                }
            }
        }
        
        $c_user = $row['user'];
        $userResult = $conn2->query("SELECT * FROM users WHERE id = $c_user");
        $userRow = $userResult->fetch_assoc();

        $pfp = 'ajax/pfp?method=image&id=' . base64_encode($c_user);

        $banResult = $conn2->query("SELECT * FROM bans WHERE user = '$c_user' LIMIT 1");
        $banRow = $banResult->fetch_assoc();

        if ($banResult->num_rows > 0 && $banRow['end_date'] >= time()) {
            continue;
        }

        if (!empty($userRow['deactive'] || empty($userRow['username']) || $userResult->num_rows < 0)) {
            continue;
        }

        if (!is_numeric($row['date'])) {
            $row['date'] = time();
        }

        $count++;

        $comments[] = array(
            'id' => $comment_id, 
            'userid' => $c_user, 
            'pfp' => $pfp, 
            'username' => $conn2->real_escape_string(htmlspecialchars($userRow['username'])), 
            'comment' => $bbcode->toHTML(htmlspecialchars($row['comment'])), 
            'date' => time_ago(date('Y-m-d H:i:s', $row['date'])), 
            'count' => $count, 
            'votes' => $comment_votes, 
            'voted' => $is_voted, 
            'loggedin' => $loggedin, 
        );
    }

    echo json_encode($comments);

    header("HTTP/1.0 200 OK");
    $comResult->free();
    exit;
} */

function fetch_comments($model_id, $csrf) {
    global $users_row;
    global $tokendata;
    global $token;
    global $loggedin;
    global $conn;
    global $conn2;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
    $bbcode = new BBCode();

    if (empty($csrf) || $csrf != $_SESSION['csrf']) {
        return json_encode([
            "message" => 'No CSRF token provided, or it is invalid!',
            "error" => 'INVALID_CSRF'
        ]);
    }

    $id = $token['user'];

	$sql = "SELECT DISTINCT * FROM comments WHERE model = $model_id ORDER BY id ASC";
	$comResult = $conn->query($sql);

    $count_result = $conn->query("SELECT COUNT(*) as reply_count FROM comments WHERE model = $model_id");
    $count_row = $count_result->fetch_assoc();

    $count = 0;
    $comments = [];
	while ($row = $comResult->fetch_assoc()) {
        $comment_id = $row['id'];

        $voted_query = $conn->query("SELECT * FROM comment_votes WHERE comment_id = '$comment_id'");
        $comment_votes = 0;
        $is_voted = false;
        while ($voted_row = $voted_query->fetch_assoc()) {
            $comment_votes++;
            if ($loggedin === true) {
                if ((int)$voted_row['user_id'] === (int)$id) {
                    $is_voted = true;
                }
            }
        }
        
        $c_user = $row['user'];
        $userResult = $conn2->query("SELECT * FROM users WHERE id = $c_user");
        $userRow = $userResult->fetch_assoc();

        $banResult = $conn2->query("SELECT * FROM bans WHERE user = '$c_user' LIMIT 1");
        $banRow = $banResult->fetch_assoc();

        $youBlocked = false;
        $theyBlocked = false;
        $blockResult = $conn2->query("SELECT * FROM user_blocks WHERE (userid = '$c_user' AND profileid = '$id') OR (userid = '$id' AND profileid = '$c_user')");
        if ($blockResult && $blockResult->num_rows > 0) {
            while ($row = $blockResult->fetch_assoc()) {
                if ($row['userid'] == $id && $row['profileid'] == $c_user) {
                    $youBlocked = true;
                } elseif ($row['userid'] == $c_user && $row['profileid'] == $id) {
                    $theyBlocked = true;
                }
            }
            if ($youBlocked && $theyBlocked) {
                $message = "You blocked @" . $userRow['username'] . ", and they blocked you.";
            } elseif ($youBlocked) {
                $message = "You blocked @" . $userRow['username'];
            } elseif ($theyBlocked) {
                $message = "You're blocked from @" . $userRow['username'];
            }
            continue;
        }

        if ($banResult->num_rows > 0 && $banRow['end_date'] >= time()) {
            continue;
        }

        if (!empty($userRow['deactive'] || $userResult->num_rows < 0)) {
            continue;
        }

        if (!is_numeric($row['date'])) {
            $row['date'] = time();
        }

        $count++;

        header("HTTP/1.0 200 OK");
        $comments[] = [
            'id' => $comment_id,
            'userid' => $c_user,
            'user_admin' => htmlspecialchars($userRow['admin']),
            // if you want me to add this code snippet that does nothing ask me
            // 'username' => empty(htmlspecialchars($userRow['username'])) ? 'Deleted User' : htmlspecialchars($userRow['username']),
            'username' => htmlspecialchars($userRow['username']),
            'user_about' => empty(htmlspecialchars($userRow['description'])) ? 'No description for this user.' : htmlspecialchars($userRow['description']),
            'comment' => $bbcode->toHTML(nl2br(htmlspecialchars($row['comment']))),
            'date' => time_ago(date('Y-m-d H:i:s', $row['date'])),
            'votes' => $comment_votes,
            'voted' => $is_voted,
            'loggedin' => $loggedin,
            'message' => $message
        ];
    }
    $comResult->free();
    return json_encode($comments); 

    /*echo json_encode($comments);
    exit; */
}

if(isset($_GET['build_comments'])) {
    header('Content-Type: application/json');
    $model_id = htmlspecialchars((int)$_GET['buildId']);
    $comment_data = fetch_comments($model_id, $_SESSION['csrf']);
    exit($comment_data);
}

if(isset($_POST['comment'])){
    header('Content-Type: application/json');

	$comment = $_POST['commentbox'];
    $model_id = $_POST['buildId'];
	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);

    if(!isset($_COOKIE['token']) && $tokendata->num_rows === 0) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode([ 'error' => 'Please login to comment.' ]);
        exit;
    }

    if(strlen($comment) > 500) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Comment must be less than 500 characters.']);
        exit;
    }

    if(strlen($comment) < 2) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Comment must be more than 2 characters.']);
        exit;
    }
		
	$date = time();
    $sql = "INSERT INTO comments (user, model, comment, date) VALUES (?, ?, ?, ?) LIMIT 1";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("iiss", $id, $model_id, $comment, $date);
    if ($stmt2->execute()) {
        if($id != $userid) {
            $time = time();
            $content = $_GET['id'];
            $category = 2;
            $sql = "INSERT INTO notifications (user, profile, timestamp, content, category) VALUES (?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisii", $userid, $id, $time, $content, $category);
            $stmt->execute();
            $stmt->close();

            $date = time();
            $sql = "SELECT alert FROM users WHERE id = ?";
            $stmt3 = $conn->prepare($sql);
            $stmt3->bind_param("i", $userid);
            $stmt3->bind_result($alert);
            $stmt3->execute();
            $stmt3->close();

            $alertnum = $alert + 1;
            $stmt = $conn->prepare("UPDATE users SET alert = ? WHERE id = ?");
            $stmt->bind_param("si", $alertnum, $userid);

            $stmt->close();
        }
        $conn->close();
        header("HTTP/1.0 200 OK");
        echo json_encode(['success' => 'Comment sent.']);
        exit;
    } else {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Could not send comment. Please try again later.']);
        exit;
    }
    $stmt2->close();
}

function delete_build($model_id) {
    global $conn, $loggedin, $users_row;

    $sql = "SELECT * FROM model WHERE id = ? AND removed = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $model_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $build_row = $result->fetch_assoc();

    if ($result->num_rows === 0) {
        return 'Error: Creation not found';
    }

    if (!$loggedin || $users_row['id'] !== $build_row['user']) {
        return 'Error: Unauthorized';
    }

    $model_path = '../cre/' . $build_row['model'];
    if (file_exists($model_path)) {
        unlink($model_path);
    }

    $screenshot_path = $build_row['screenshot'];
    if (file_exists($screenshot_path)) {
        unlink($screenshot_path);
    }

    $sql = "DELETE FROM model WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $model_id);
    $stmt->execute();

    return 'Success';
}

if(isset($_GET['delete_build'])) {
    $model_id = $_GET['model_id'];
    $delete = delete_build($model_id);
    return $delete;
}

if(isset($_GET['picture'])) {
    $model_id = $_GET['id'];
    $sql = "SELECT screenshot FROM model WHERE id = '$model_id' AND removed = '0'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    $name = $row['screenshot'];
    $imagePath = '../cre/' . $name;

    if(!file_exists($imagePath)) {
        $imagePath = '../img/no_image.png';
    }

    $fp = fopen($imagePath, 'rb');
    $contents = file_get_contents($imagePath);
    $data = 'data:image/png;base64,'. base64_encode($contents);

    if ($_GET['method'] === 'image') {
        header("Content-Type: image/jpg");
        header("Content-Length: " . filesize($imagePath));
        fpassthru($fp);
        echo $data;
        exit;
    }
}

if ($loggedin === true) {
    $id = $token['user'];
    if (isset($_POST['downvote'])) {
        if (!isset($_POST['model_id']) || !is_numeric($_POST['model_id'])) {
            echo json_encode(['error' => 'invalid model']);
            exit;
        }
        $model_id = (int)$_POST['model_id'];

        $stmt = $conn->prepare("DELETE FROM votes WHERE user = ? AND creation = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $model_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => 'vote removed']);
        } else {
            echo json_encode(['error' => 'failed to remove vote']);
        }
        $stmt->close();
    }

    if (isset($_POST['upvote'])) {
        header('Content-Type: application/json');

        if (!isset($_POST['model_id']) || !is_numeric($_POST['model_id'])) {
            echo json_encode(['error' => 'invalid model']);
            exit;
        }
        $model_id = (int) $_POST['model_id'];

        $stmt = $conn->prepare("INSERT INTO votes (creation, user) VALUES (?, ?)");
        $stmt->bind_param("ii", $model_id, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => 'vote added']);
        } else {
            echo json_encode(['error' => 'failed to add vote']);
        }
        $stmt->close();
    }

    if (isset($_POST['upvote_comment'])) {
        header('Content-Type: application/json');

        if (!isset($_POST['comment_id']) || !is_numeric($_POST['comment_id'])) {
            echo json_encode(['error' => 'invalid comment']);
            exit;
        }
        $id = $token['user'];
        $username = $users_row['username'];
        $comment_id = (int) $_POST['comment_id'];

        $stmt = $conn->prepare("SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $comment_id, $id);
        $stmt->execute();
        $stmt->bind_result($vote_count);
        $stmt->fetch();
        $stmt->close();

        if ($vote_count > 0) {
            echo json_encode(['error' => 'already voted on this comment']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO comment_votes (comment_id, user_id, user_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $comment_id, $id, $username);
        if ($stmt->execute()) {
            echo json_encode(['success' => 'comment upvoted']);
        } else {
            echo json_encode(['error' => 'failed to insert upvote']);
        }
        $stmt->close();
    }

    if (isset($_POST['downvote_comment'])) {
        header('Content-Type: application/json');

        if (!isset($_POST['comment_id']) || !is_numeric($_POST['comment_id'])) {
            echo json_encode(['error' => 'invalid comment']);
            exit;
        }
        $id = $token['user'];
        $comment_id = (int) $_POST['comment_id'];

        if (!isset($id) || !is_numeric($id)) {
            echo json_encode(['error' => 'not authenticated']);
            exit;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) as vote_count FROM comment_votes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $comment_id, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $vote_count = (int)$row['vote_count']; 
        $stmt->close();

        if ($vote_count === 0) {
            echo json_encode(['error' => 'did not vote on this comment']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM comment_votes WHERE comment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $comment_id, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => 'comment downvoted']);
        } else {
            echo json_encode(['error' => 'failed to downvote']);
        }
        $stmt->close();
    }
}
?>