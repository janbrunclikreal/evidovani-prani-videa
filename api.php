<?php
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

$hostname = $_SERVER['HTTP_HOST'];
$config = $dbConfig['default'];

if (isset($dbConfig[$hostname])) {
    $config = $dbConfig[$hostname];
}

$servername = $config['servername'];
$username = $config['username'];
$password = $config['password'];
$dbname = $config['dbname'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

session_start();

$action = isset($_GET['action']) ? $_GET['action'] : '';

function checkAuth() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You are not logged in.']);
    exit;
}

function checkAdmin() {
    $user = checkAuth();
    if ($user['role'] === 'admin') {
        return true;
    }
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Administrator privileges required.']);
    exit;
}

switch($action) {

    case 'login':
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        if (empty($user) || empty($pass)) {
            echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
            break;
        }
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'User not found.']);
        } else {
            $row = $result->fetch_assoc();
            if (password_verify($pass, $row['password'])) {
                $_SESSION['user'] = [
                    'id' => $row['id'],
                    'username' => $row['username'],
                    'role' => $row['role']
                ];
                echo json_encode(['status' => 'success', 'message' => 'Logged in successfully.', 'user' => $_SESSION['user']]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
            }
        }
        $stmt->close();
        break;

    case 'checkSession':
        if (isset($_SESSION['user'])) {
            echo json_encode(['status' => 'success', 'isLoggedIn' => true, 'user' => $_SESSION['user']]);
        } else {
            echo json_encode(['status' => 'success', 'isLoggedIn' => false]);
        }
        break;

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
        break;

    case 'userRegister':
        checkAdmin();
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        if (empty($username) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => 'Username and password are required.']);
            break;
        }
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
                $stmt->close();
                break;
            }
            $stmt->close();
        }
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("sss", $username, $hashedPassword, $role);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'User registered successfully', 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'userChangePassword':
        checkAuth();
        $user = $_SESSION['user'];
        $userId = $user['id'];
        $newPassword = trim($_POST['password'] ?? '');
        if (empty($newPassword)) {
            echo json_encode(['status' => 'error', 'message' => 'New password is required.']);
            break;
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("si", $hashedPassword, $userId);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'adminUserEdit':
        checkAdmin();
        $userId = $_POST['id'] ?? '';
        $newPassword = trim($_POST['password'] ?? '');
        if (empty($userId) || empty($newPassword)) {
            echo json_encode(['status' => 'error', 'message' => 'User ID and new password are required.']);
            break;
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("si", $hashedPassword, $userId);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'User password updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'adminUserUpdate':
        checkAdmin();
        $userId = $_POST['id'] ?? '';
        $newUsername = trim($_POST['username'] ?? '');
        $newRole = trim($_POST['role'] ?? '');
        if (empty($userId)) {
            echo json_encode(['status' => 'error', 'message' => 'User ID is required.']);
            break;
        }
        $fields = [];
        $params = [];
        $types = "";
        if (!empty($newUsername)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
            $stmt->bind_param("si", $newUsername, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Username already exists.']);
                $stmt->close();
                break;
            }
            $stmt->close();
            $fields[] = "username = ?";
            $types .= "s";
            $params[] = $newUsername;
        }
        if (!empty($newRole)) {
            $fields[] = "role = ?";
            $types .= "s";
            $params[] = $newRole;
        }
        if (empty($fields)) {
            echo json_encode(['status' => 'error', 'message' => 'Nothing to update.']);
            break;
        }
        $setClause = implode(", ", $fields);
        $stmt = $conn->prepare("UPDATE users SET $setClause WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $types .= "i";
        $params[] = $userId;
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            if (!empty($newUsername)) {
                $_SESSION['user']['username'] = $newUsername;
            }
            echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'listUsers':
        checkAdmin();
        $result = $conn->query("SELECT id, username, role FROM users");
        $users = [];
        while($row = $result->fetch_assoc()){
            $users[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $users]);
        break;

    case 'create':
        checkAdmin();
        $datum   = $_POST['datum']  ?? '';
        $jmeno   = $_POST['jmeno']  ?? '';
        $ucet    = $_POST['ucet']   ?? '';
        $castka  = $_POST['castka'] ?? 60;
        $stav    = $_POST['stav']   ?? 'prijato';
        $prani   = $_POST['prani']  ?? '';
        $nick    = $_POST['nick']   ?? '-';
        $link    = $_POST['link']   ?? null;
        $faktura = $_POST['faktura'] ?? null;
        $stmt = $conn->prepare("INSERT INTO prani_video (datum, jmeno, ucet, castka, stav, prani, nick, link, faktura) VALUES (STR_TO_DATE(?, '%d.%m.%y'), ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("sssisssss", $datum, $jmeno, $ucet, $castka, $stav, $prani, $nick, $link, $faktura);
        if($stmt->execute()){
            echo json_encode(['status' => 'success', 'id' => $stmt->insert_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'list':
        checkAuth();
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $result = $conn->query("SELECT SQL_CALC_FOUND_ROWS *, DATE_FORMAT(datum, '%d.%m.%y') AS datum_formatted FROM prani_video ORDER BY id DESC LIMIT $offset, $limit");
        $records = [];
        while($row = $result->fetch_assoc()){
            $records[] = $row;
        }
        $totalResult = $conn->query("SELECT FOUND_ROWS() AS total");
        $total = $totalResult->fetch_assoc()['total'];
        echo json_encode(['status' => 'success', 'data' => $records, 'total' => $total, 'page' => $page]);
        break;

    case 'daily':
        checkAuth();
        $date = $_GET['date'] ?? '';
        if(!$date) {
            echo json_encode(['status' => 'error', 'message' => 'Missing date parameter']);
            break;
        }
        $stmt = $conn->prepare("SELECT *, DATE_FORMAT(datum, '%d.%m.%y') AS datum_formatted FROM prani_video WHERE datum = STR_TO_DATE(?, '%d.%m.%y')");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $records = [];
        while($row = $result->fetch_assoc()){
            $records[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $records]);
        $stmt->close();
        break;

    case 'monthly':
        checkAuth();
        $month = $_GET['month'] ?? '';
        $year  = $_GET['year']  ?? '';
        if(!$month || !$year) {
            echo json_encode(['status' => 'error', 'message' => 'Missing month or year parameter']);
            break;
        }
        $stmt = $conn->prepare("SELECT DATE_FORMAT(datum, '%d.%m.%y') AS datum_formatted, castka FROM prani_video WHERE MONTH(datum)=? and YEAR(datum)=? ");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $dailyRecords = [];
        $total = 0;
        while($row = $result->fetch_assoc()){
            $dailyRecords[] = $row;
            $total += $row['castka'];
        }
        echo json_encode(['status' => 'success', 'data' => $dailyRecords, 'total' => $total]);
        $stmt->close();
        break;
        
    case 'yearly':
        checkAuth();
        $year = $_GET['year'] ?? '';
        if(!$year) {
            echo json_encode(['status' => 'error', 'message' => 'Missing year parameter']);
            break;
        }
        $stmt = $conn->prepare("SELECT MONTH(datum) as month, SUM(castka) as total_castka FROM prani_video WHERE YEAR(datum)=? GROUP BY MONTH(datum) ORDER BY MONTH(datum)");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $monthlyTotals = [];
        $totalForYear = 0;
        while($row = $result->fetch_assoc()){
            $monthlyTotals[] = $row;
            $totalForYear += $row['total_castka'];
        }
        echo json_encode(['status' => 'success', 'data' => $monthlyTotals, 'total' => $totalForYear]);
        $stmt->close();
        break;

    case 'updateStatus':
        checkAdmin();
        $id = $_POST['id'] ?? '';
        $stav = $_POST['stav'] ?? '';
        if(!$id || !$stav) {
            echo json_encode(['status' => 'error', 'message' => 'Missing id or stav parameter']);
            break;
        }
        $stmt = $conn->prepare("UPDATE prani_video SET stav=? WHERE id=?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("si", $stav, $id);
        if($stmt->execute()){
            echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'updateRecord':
        checkAdmin();
        $id = $_POST['id'] ?? '';
        $prani = $_POST['prani'] ?? '';
        $nick = $_POST['nick'] ?? '';
        $link = $_POST['link'] ?? '';
        $faktura = $_POST['faktura'] ?? '';
        if(!$id) {
            echo json_encode(['status' => 'error', 'message' => 'Missing id parameter']);
            break;
        }
        $stmt = $conn->prepare("UPDATE prani_video SET prani = ?, nick = ?, link = ?, faktura = ? WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            break;
        }
        $stmt->bind_param("ssssi", $prani, $nick, $link, $faktura, $id);
        if($stmt->execute()){
            echo json_encode(['status' => 'success', 'message' => 'Record updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
        break;

    case 'export':
        checkAdmin();
        $filename = "export_" . date("y-m-d_H-i") . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=$filename");
        $output = fopen('php://output', 'w');
        fputcsv($output, array('datum', 'jmeno', 'ucet', 'castka', 'stav', 'prani', 'nick', 'link', 'faktura'));
        $result = $conn->query("SELECT DATE_FORMAT(datum, '%d.%m.%y') as datum, jmeno, ucet, castka, stav, prani, nick, link, faktura FROM prani_video");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, $row);
        }
        fclose($output);
        break;

    case 'import':
        checkAdmin();
        $importMode = $_POST['importMode'] ?? 'append';
        if (!isset($_FILES['importFile']) || $_FILES['importFile']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'Soubor se nepodařilo nahrát.']);
            break;
        }
        if ($importMode === 'delete') {
            $conn->query("TRUNCATE TABLE prani_video");
        }
        $file = $_FILES['importFile']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle === false) {
            echo json_encode(['status' => 'error', 'message' => 'Nepodařilo se otevřít soubor.']);
            break;
        }
        fgetcsv($handle);
        $imported = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if(count($data) < 9) continue;
            $datum = $data[0];
            $jmeno = $data[1];
            $ucet = $data[2];
            $castka = $data[3];
            $stav = $data[4];
            $prani = $data[5];
            $nick = $data[6];
            $link = $data[7];
            $faktura = $data[8];
            $stmt = $conn->prepare("INSERT INTO prani_video (datum, jmeno, ucet, castka, stav, prani, nick, link, faktura) VALUES (STR_TO_DATE(?, '%d.%m.%y'), ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssisssss", $datum, $jmeno, $ucet, $castka, $stav, $prani, $nick, $link, $faktura);
                $stmt->execute();
                $stmt->close();
                $imported++;
            }
        }
        fclose($handle);
        echo json_encode(['status' => 'success', 'message' => "Importováno záznamů: $imported"]);
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'No valid action specified']);
}

$conn->close();
?>
