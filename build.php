<?php
error_reporting(0);
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/time.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ajax/filesize.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/com/bbcode.php';
$bbcode = new BBCode;

$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
$conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

if(isset($_GET['storage'])) {
    header('Content-Type: application/json');

    if(!isset($_GET['user'])) {
        if (!isset($token['user'])) {
            header("HTTP/1.1 401 Unauthorized");
            echo json_encode(['success' => false, 'message' => 'Please login to view this data.']);
            exit;
        }
        $user = $token['user'];
        $name_user = $users_row['username'];
    } else {
        $user = (int)$_GET['user'];
        
        $query = "SELECT username FROM users WHERE id = ? AND deactive IS NULL";
        $stmt = $conn2->prepare($query);
        $stmt->bind_param("i", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows > 0) {
        	$name_user = $result->fetch_assoc()['username'] ?? '[deleted]';
        } else {
            header("HTTP/1.1 404 Not Found");
            echo json_encode(['success' => false, 'message' => '404 not found']);
            exit;
        }
    }

    $stmt = $conn->prepare("SELECT SUM(size) as total_used FROM model WHERE user = ?");
    $stmt->bind_param("i", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total_used'] ?? 0;
    $stmt->close();

    echo json_encode(['success' => true, 'user' => $name_user, 't' => time(), 'total' => (int)$total]);
    exit;
}

if (isset($_POST['save_build'])) {
    header('Content-Type: application/json');

    $modelJson = $_POST['creation'];

    if (empty($modelJson)) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Request is empty."]);
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

    $stmt = $conn->prepare("SELECT SUM(size) as total_used FROM model WHERE user = ?");
    $stmt->bind_param("i", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total_used'] ?? 0;
    $stmt->close();

    $file_id = uniqid();
    $file_name = "../cre/" . $file_id . ".json";
    $db_file_name = "/cre/" . $file_id . ".json";
    $desc = htmlspecialchars($_POST['desc']);
    $name = htmlspecialchars($_POST['name']);
    $date = date("Y-m-d H:i:s");
    $screenshot_path = null;
    $db_screenshot = null;
    $db_file_size = strlen($modelJson);
    
    if (($total + $db_file_size) > MODEL_STORAGE_LIMIT) {
        header("HTTP/1.0 413 Payload Too Large");
        echo json_encode(['error' => "Storage limit of " . size_unit(MODEL_STORAGE_LIMIT) . " was reached. Please delete older creations to save new ones."]);
        exit;
    }

    if (!empty($_POST['screenshot'])) {
        $screenshot_data = $_POST['screenshot'];

        if (strpos($screenshot_data, 'data:image/png;base64,') === 0) {
            $screenshot_data = base64_decode(substr($screenshot_data, strlen('data:image/png;base64,')));
            $screenshot_path = "../cre/" . $file_id . ".png";
            $db_screenshot = "/cre/" . $file_id . ".png";

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

    $db_file_size = filesize($file_name);

    if ($conn->connect_error) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => "Database connection failed."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO model (user, model, description, name, date, size, screenshot) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => "Failed to save your creation to the database."]);
        exit;
    }
    $stmt->bind_param("issssis", $user, $db_file_name, $desc, $name, $date, $db_file_size, $db_screenshot);

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

function truncateStr($mystr) {
    $truncatedName = substr($mystr, 0, 30);
    if (strlen($mystr) >= 30) {
        $truncatedName .= '...';
    }
}

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
    $name = $row2['name'];
    $date = $row2['date'];
    $screenshot = $row2['screenshot'];
    $views = $row2['views'];
    $votes = $row2['likes'];
    $isRemoved = $row2['removed'];
    $decoded_description = $bbcode->toHTML(nl2br($row2['description']));
       
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

    if (!empty($row['deactive'])) {
        $username = $row['username'];
        $userid = 0;
    } elseif($result->num_rows > 0 || !empty($row['username'])) {
        $username = $row['username'];
        $model_admin = $row['admin'];
	} else {
        $username = "[deleted]";
        $userid = 0;
    }
    
    $result3 = $conn2->query("SELECT * FROM bans WHERE user = $userid");
    $row3 = $result3->fetch_assoc();

    while ($row3 = $result3->fetch_assoc()) {
        if ($result3->num_rows > 0 && $row3['end_date'] >= time()) {
            header('HTTP/1.0 500 Internal Server Error');
            return json_encode([
                "message" => 'Creation could not load as the account that made it has been banned',
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
                "error" => 'ACC_BLOCKING'
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
        'userid' => $userid, 
        'modelid' => $model_id, 
        'model' => $row2['model'], 
        'description' => $decoded_description, 
        'name' => $row2['name'], 
        'date' => $row2['date'], 
        'screenshot' => $row2['screenshot'], 
        'views' => $views, 
        'isRemoved' => $row2['removed'], 
        'voted' => $voted, 
        'likes' => $votes, 
        'decoded_description' => $decoded_description,
        'username' => $username, 
        'model_admin' => $row['admin'], 
        'message' => $message
    ];
    return json_encode($data);
}

if(isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    $model_id = htmlspecialchars((int)$_GET['buildId']);
    $data = fetch_build($model_id, $_SESSION['csrf']);
    exit($data);
}

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
        $userResult = $conn2->query("SELECT * FROM users WHERE id = '$c_user'");
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

        $username = $userRow['username'];

        if (!empty($userRow['deactive']) || $banResult->num_rows > 0 && $banRow['end_date'] >= time()) {
            $c_user = 0;
        }

        if ($userResult->num_rows === 0 || empty($userRow['id'])) {
            $username = "[deleted]";
            $c_user = 0;
        }

        if (!is_numeric($row['date'])) {
            $row['date'] = 0;
        }

        $count++;

        header("HTTP/1.0 200 OK");
        $comments[] = [
            'id' => $comment_id,
            'userid' => $c_user,
            'user_admin' => htmlspecialchars($userRow['admin']),
            'username' => htmlspecialchars($username),
            'picture' => $userRow['picture'],
            'comment' => $bbcode->toHTML(nl2br(htmlspecialchars($row['comment']))),
            'date' => time_ago(date('Y-m-d H:i:s', $row['date'])),
            'is_op' => $row['is_op'],
            'votes' => $comment_votes,
            'voted' => $is_voted,
            'message' => $message
        ];
    }
    $comResult->free();
    return json_encode($comments); 
}

function get_user_storage($name) {
    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $user_id = $row['id'] ?? 0;
    $name = $row['username'] ?? "[deleted]";
    $stmt->close();

    $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);

    $stmt = $conn->prepare("SELECT SUM(size) as total_used FROM model WHERE user = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_used = $result->fetch_assoc()['total_used'] ?? 0;
    $stmt->close();

    header("HTTP/1.0 200 OK");
    $data[] = [
        'userid' => $user_id,
        'username' => $name,
        'total_used' => $total_used
    ];
    return $data;
}

if(isset($_GET['getUserStorage'])) {
    header('Content-Type: application/json');
    $name = htmlspecialchars($_GET['name']);
    $usedStorage = get_user_storage($name);
    echo json_encode($usedStorage);
    exit;
}

if(isset($_GET['build_comments'])) {
    header('Content-Type: application/json');
    $model_id = htmlspecialchars((int)$_GET['buildId']);
    $comment_data = fetch_comments($model_id, $_SESSION['csrf']);
    exit($comment_data);
}

if(isset($_GET['list_all'])) {
    header('Content-Type: application/json');
    error_reporting(1);
    
        $conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);
        $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
        $is_search = false;

        $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        $offset = ($page - 1) * 12;

        if (isset($_COOKIE['token']) && $tokendata->num_rows != 0) {
            $stmt = $conn->prepare('SELECT * FROM follow WHERE userid = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            $followed_users = [];

            if ($result->num_rows != 0) {
                while ($row = $result->fetch_assoc()) {
                    $followed_users[] = $row['profileid'];
                }

                $sql = 'SELECT * FROM model WHERE user IN (' . implode(',', $followed_users) . ') ORDER BY date DESC LIMIT 12 OFFSET ' .  $offset;
                $better_feed_msg = false;
              
              if (
                isset($_GET['sort']) 
                && $_GET['sort'] === "Following" 
                || empty($_GET['sort']) 
                && empty($_GET['q'])
              ) {
                $better_feed_msg = true;
              } else {
                  $better_feed_msg = false;
              }
              
            } else {
                $sql = 'SELECT * FROM model WHERE removed = 0 ORDER BY id DESC LIMIT 12 OFFSET ' .  $offset;
                
                $following_empty = true;
            }
        } else {
            $sql = 'SELECT * FROM model WHERE removed = 0 ORDER BY id DESC LIMIT 12 OFFSET ' .  $offset;
        }

        if (isset($_GET['q']) && $_GET['q']) {
            $is_search = true;

            $query = isset($_GET['q']) ? trim($_GET['q']) : '';
            $search = "%" . $conn2->real_escape_string(htmlspecialchars($query)) . "%";

            $stmt = $conn2->prepare('SELECT * FROM model WHERE (name LIKE ? OR description LIKE ?) LIMIT 12 OFFSET ' .  $offset);
            $stmt->bind_param('ss', $search, $search);
            $stmt->execute();
            $result2 = $stmt->get_result();
        }

        if (isset($_GET['sort']) && $_GET['sort']) {
            if ($_GET['sort'] === 'feature') {
                $sql  = 'SELECT * FROM model WHERE feature = 1 ORDER BY date DESC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Featured creations';
            }
            if ($_GET['sort'] === 'views') {
                $sql  = 'SELECT * FROM model ORDER BY views DESC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Most viewed';
            }
            if ($_GET['sort'] === 'likes') {
                $sql  = 'SELECT * FROM model ORDER BY likes DESC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Most liked';
            }
            if ($_GET['sort'] === 'az') {
                $sql  = 'SELECT * FROM model ORDER BY name ASC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Alphabetical A-Z';
            }
            if ($_GET['sort'] === 'za') {
                $sql  = 'SELECT * FROM model ORDER BY name DESC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Alphabetical Z-A';
            }
            if ($_GET['sort'] === 'oldest') {
                $sql  = 'SELECT * FROM model ORDER BY date ASC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Oldest creations';
            }
            if ($_GET['sort'] === 'newest') {
                $sql  = 'SELECT * FROM model ORDER BY date DESC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'Newest creations';
            }
            if ($_GET['sort'] === 'all') {
                $sql  = 'SELECT * FROM model WHERE removed = 0 ORDER BY id DESC LIMIT 12 OFFSET ' .  $offset;
                $sort = 'All creations';
            }
            if (!empty($sort)) {
                echo '<p>Sorting by <b>' . $sort . '</b></p>';
            }
        }

        if(!$is_search && $page > 0) {
            $stmt = $conn2->prepare($sql);
            $stmt->execute();
            $result2 = $stmt->get_result();
        }
        
        // if there is some creations found from the query
        if ($result2->num_rows > 0) {
            $builds = [];
          
            $total = $result2->num_rows;
            while ($row = $result2->fetch_assoc()) {
              
                $model_id = $row['id'];
                $userid = $row['user'];

                /*
                    Fetch users, like count, and bans
                    We also check if those are invalid
                */
              
                $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->bind_param('i', $userid);
                $stmt->execute();
                $result3 = $stmt->get_result();
                $user = $result3->fetch_assoc();
                $username = isset($user['username']) ? $user['username'] : '';

                $stmt = $conn->prepare('SELECT * FROM bans WHERE user = ?');
                $stmt->bind_param('i', $userid);
                $stmt->execute();
                $banResult = $stmt->get_result();
                $banRow = $banResult->fetch_assoc();

                if (empty($row['name'])) {
                    $row['name'] = $username . "'s creation";
                }

                /*if ($result3->num_rows <= 0 || $banResult->num_rows > 0 && $banRow['end_date'] >= time()) {
                    continue;
                }*/

                $truncatedName = substr($row['name'], 0, 30);
                if (strlen($row['name']) >= 30) {
                    $truncatedName .= '...';
                }

                /*echo "<div class='w3-display-container w3-left w3-padding'>";
                echo "<a href='/build/" . $row['id'] . "'><img src='/cre/" . $row['screenshot'] . "' width='320' height='240' loading='lazy' class='gr8-theme w3-card-2 w3-hover-shadow w3-border w3-border-white'></a>";
                echo "<span class='gr8-theme w3-large w3-display-middle w3-card-2 w3-light-grey w3-padding-small'>" . $truncatedName . '</span>';
                echo "<span class='gr8-theme w3-display-bottommiddle w3-card-2 w3-light-grey w3-padding-small'>" . $row['views'] . ' views - ' . $likes['count'] . " likes - By ";
                echo "<a href='/user/" . $row['user'] . "'>" . $username . "</a></span></div>"; */

                $builds[] = [
                    'id' => $row['id'],
                    'screenshot' => $row['screenshot'],
                    'user_id' => htmlspecialchars($row['user']),
                    'username' => htmlspecialchars($username),
                    'date' => time_ago($row['date']),
                    'likes' => $row['likes'],
                    'views' => $row['views'],
                    'name' => htmlspecialchars($row['name']),
                    'total' => $total,
                    'better_feed_msg' => $better_feed_msg,
                    'following_empty' => $following_empty,
                ];
            }
            echo json_encode($builds);
            exit;
        } else {
            header("HTTP/1.0 500 Internal Server Error");
        	echo json_encode(['error' => 'No creations found.']);
        	exit;
        }
        // Foward back buttons
        $sorting = isset($_GET['sort']) ? $_GET['sort'] : "following";
        $searching = isset($_GET['q']) ? $_GET['q'] : "";
        echo '<a class="w3-btn w3-blue w3-hover-opacity w3-round-small w3-padding-small w3-border w3-border-indigo" href="?p=' . ($page - 1) . '&sort=' . $sorting . '&q=' . $searching . '">Back</a>&nbsp;';
        echo '<a class="w3-btn w3-blue w3-hover-opacity w3-round-small w3-padding-small w3-border w3-border-indigo" href="?p=' . ($page + 1) . '&sort=' . $sorting . '&q=' . $searching . '">Forward</a>';
}

if(isset($_POST['comment'])) {
    header('Content-Type: application/json');
    error_reporting(0);

	$comment = $_POST['commentbox'];
    $csrf = $_POST['csrf_token'];
    $model_id = $_POST['buildId'];
	$conn = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME2);
    $conn2 = new mysqli(DB_SERVER, DB_USER, DB_PASSWORD, DB_NAME);

    if ($_SESSION['csrf'] !== $_POST['csrf_token']) {
        header('HTTP/1.0 403 Forbidden');
        echo json_encode(['error' => 'Your cross-site-request-forgery token seems to be invalid.']);
        exit;
    }

    if(!loggedin()) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Please login to comment.']);
        exit;
    }

    if($users_row['verify_token'] != NULL) {
        header("HTTP/1.0 500 Internal Server Error");
        echo json_encode(['error' => 'Please verify your account to comment.']);
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
    
    $stmt4 = $conn->prepare("SELECT user FROM model WHERE id = ?");
    $stmt4->bind_param("i", $model_id);
    $stmt4->execute();
    $result = $stmt4->get_result();
    $userid = (int)$result->fetch_assoc()['user'];
		
	$date = time();
    $is_op = $_SESSION['userid'] === $userid ? 1 : 0;
    
    $sql = "INSERT INTO comments (user, model, comment, date, is_op) VALUES (?, ?, ?, ?, ?) LIMIT 1";
    $stmt2 = $conn->prepare($sql);
    $stmt2->bind_param("iissi", $id, $model_id, $comment, $date, $is_op);
    
    if ($stmt2->execute()) {
        if($_SESSION['userid'] != $userid) {
            $time = time();
            $content = $model_id;
            $category = 2;
            $sql = "INSERT INTO notifications (user, profile, timestamp, content, category) VALUES (?, ?, ?, ?, ?)";

            $stmt = $conn2->prepare($sql);
            $stmt->bind_param("iisii", $userid, $id, $time, $content, $category);
            $stmt->execute();
            $stmt->close();

            $date = time();
            $sql = "SELECT alert FROM users WHERE id = ?";
            $stmt3 = $conn2->prepare($sql);
            $stmt3->bind_param("i", $userid);
            $stmt3->bind_result($alert);
            $stmt3->execute();
            $stmt3->close();

            $alertnum = $alert + 1;
            $stmt = $conn2->prepare("UPDATE users SET alert = ? WHERE id = ?");
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

        $stmt = $conn->prepare("UPDATE model SET likes = likes - 1 WHERE id = ?");
        $stmt->bind_param("i", $model_id);
        if (!$stmt->execute()) {
            echo json_encode(['error' => 'failed to update likes']);
            $stmt->close();
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM votes WHERE user = ? AND creation = ?");
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
            echo json_encode(['error' => 'Model or user that created it was not found.']);
            exit;
        }

        $model_id = (int)$_POST['model_id'];
        
        $stmt = $conn->prepare("SELECT user FROM model WHERE id = ?");
        $stmt->bind_param("i", $model_id);
        $stmt->execute();
        $stmt->bind_result($m_user_id);
        $stmt->fetch();
        $stmt->close();
        
        if ($m_user_id === 0) {
            echo json_encode(['error' => 'Model or user that created it was not found.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM votes WHERE creation = ? AND user = ?");
        $stmt->bind_param("ii", $model_id, $id);
        $stmt->execute();
        $stmt->bind_result($vote_count);
        $stmt->fetch();
        $stmt->close();

        if ($vote_count > 0) {
            echo json_encode(['error' => 'You have already liked this!']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE model SET likes = likes + 1 WHERE id = ?");
        $stmt->bind_param("i", $model_id);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO votes (creation, user) VALUES (?, ?)");
        $stmt->bind_param("ii", $model_id, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => 'Liked creation with success!']);
        } else {
            echo json_encode(['error' => 'An unknown error has occured. Please try again later.']);
        }
        $stmt->close();
        exit;
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