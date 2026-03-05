<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';

if (!isset($conn) || !$conn) {
    if (!isset($db_host, $db_username, $db_password, $db_name)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database configuration is incomplete.']);
        exit;
    }

    $bootstrapConn = @new mysqli($db_host, $db_username, $db_password);
    if ($bootstrapConn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $bootstrapConn->set_charset('utf8mb4');
    if (!$bootstrapConn->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to initialize database.']);
        exit;
    }
    $bootstrapConn->close();

    $conn = @new mysqli($db_host, $db_username, $db_password, $db_name);
}

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to connect to database.']);
    exit;
}

$conn->set_charset('utf8mb4');

$createTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS client_satisfaction_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_selected VARCHAR(150) NULL,
    office_custom_name VARCHAR(255) NULL,
    office_title VARCHAR(255) NULL,
    client_type_json LONGTEXT NULL,
    visit_date DATE NULL,
    gender_json LONGTEXT NULL,
    age INT NULL,
    region_name VARCHAR(120) NULL,
    transaction_type TEXT NULL,
    cc1_json LONGTEXT NULL,
    cc2_json LONGTEXT NULL,
    cc3_json LONGTEXT NULL,
    sqd_json LONGTEXT NULL,
    suggestions TEXT NULL,
    email VARCHAR(190) NULL,
    payload_json LONGTEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

if (!$conn->query($createTableSql)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare responses table.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string)$rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$officeSelected = (string)($payload['office']['selected'] ?? '');
$officeCustomName = (string)($payload['office']['custom_name'] ?? '');
$officeTitle = (string)($payload['office']['title'] ?? '');
$clientType = $payload['client_type'] ?? [];
$visitDate = (string)($payload['respondent']['date'] ?? '');
$gender = $payload['respondent']['kasarian'] ?? [];
$age = $payload['respondent']['edad'] ?? null;
$regionName = (string)($payload['respondent']['rehiyon'] ?? '');
$transactionType = (string)($payload['transaction_type'] ?? '');
$cc1 = $payload['citizen_charter']['cc1'] ?? [];
$cc2 = $payload['citizen_charter']['cc2'] ?? [];
$cc3 = $payload['citizen_charter']['cc3'] ?? [];
$sqd = $payload['sqd_ratings'] ?? [];
$suggestions = (string)($payload['suggestions'] ?? '');
$email = (string)($payload['email'] ?? '');

if ($visitDate === '') {
    $visitDate = null;
}
if ($age === '' || $age === null) {
    $age = null;
} else {
    $age = (int)$age;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = '';
}

$clientTypeJson = json_encode($clientType, JSON_UNESCAPED_UNICODE);
$genderJson = json_encode($gender, JSON_UNESCAPED_UNICODE);
$cc1Json = json_encode($cc1, JSON_UNESCAPED_UNICODE);
$cc2Json = json_encode($cc2, JSON_UNESCAPED_UNICODE);
$cc3Json = json_encode($cc3, JSON_UNESCAPED_UNICODE);
$sqdJson = json_encode($sqd, JSON_UNESCAPED_UNICODE);
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$stmt = $conn->prepare(
    'INSERT INTO client_satisfaction_responses 
    (office_selected, office_custom_name, office_title, client_type_json, visit_date, gender_json, age, region_name, transaction_type, cc1_json, cc2_json, cc3_json, sqd_json, suggestions, email, payload_json, ip_address, user_agent)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare insert statement.']);
    exit;
}

$stmt->bind_param(
    'ssssssisssssssssss',
    $officeSelected,
    $officeCustomName,
    $officeTitle,
    $clientTypeJson,
    $visitDate,
    $genderJson,
    $age,
    $regionName,
    $transactionType,
    $cc1Json,
    $cc2Json,
    $cc3Json,
    $sqdJson,
    $suggestions,
    $email,
    $payloadJson,
    $ipAddress,
    $userAgent
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save response.']);
    $stmt->close();
    exit;
}

$insertedId = $stmt->insert_id;
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Response saved successfully.',
    'id' => $insertedId
]);
