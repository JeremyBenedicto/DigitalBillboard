<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/csm_response_schema.php';

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

if (!csm_initialize_response_tables($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare CSM storage tables.']);
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

function selected_choice(array $values): ?int
{
    foreach (array_values($values) as $index => $isSelected) {
        if ($isSelected) {
            return $index + 1;
        }
    }

    return null;
}

$cc1Choice = selected_choice(is_array($cc1) ? $cc1 : []);
$cc2Choice = selected_choice(is_array($cc2) ? $cc2 : []);
$cc3Choice = selected_choice(is_array($cc3) ? $cc3 : []);

$sqd0 = isset($sqd['sqd0']) && $sqd['sqd0'] !== '' ? (int)$sqd['sqd0'] : null;
$sqd1 = isset($sqd['sqd1']) && $sqd['sqd1'] !== '' ? (int)$sqd['sqd1'] : null;
$sqd2 = isset($sqd['sqd2']) && $sqd['sqd2'] !== '' ? (int)$sqd['sqd2'] : null;
$sqd3 = isset($sqd['sqd3']) && $sqd['sqd3'] !== '' ? (int)$sqd['sqd3'] : null;
$sqd4 = isset($sqd['sqd4']) && $sqd['sqd4'] !== '' ? (int)$sqd['sqd4'] : null;
$sqd5 = isset($sqd['sqd5']) && $sqd['sqd5'] !== '' ? (int)$sqd['sqd5'] : null;
$sqd6 = isset($sqd['sqd6']) && $sqd['sqd6'] !== '' ? (int)$sqd['sqd6'] : null;
$sqd7 = isset($sqd['sqd7']) && $sqd['sqd7'] !== '' ? (int)$sqd['sqd7'] : null;
$sqd8 = isset($sqd['sqd8']) && $sqd['sqd8'] !== '' ? (int)$sqd['sqd8'] : null;

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

$detailStmt = $conn->prepare(
    'INSERT INTO client_satisfaction_response_details
    (response_id, office_selected, office_custom_name, office_title, transaction_type, visit_date, age, region_name,
     client_type_citizen, client_type_business, client_type_government, gender_male, gender_female,
     cc1_choice, cc2_choice, cc3_choice, sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, suggestions, email)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$detailStmt) {
    http_response_code(500);
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Failed to prepare response details statement.']);
    exit;
}

$conn->begin_transaction();

try {
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
        throw new RuntimeException('Failed to save response.');
    }

    $insertedId = $stmt->insert_id;

    $clientTypeCitizen = !empty($clientType['mamamayan']) ? 1 : 0;
    $clientTypeBusiness = !empty($clientType['negosyo']) ? 1 : 0;
    $clientTypeGovernment = !empty($clientType['gobyerno']) ? 1 : 0;
    $genderMale = !empty($gender['lalaki']) ? 1 : 0;
    $genderFemale = !empty($gender['babae']) ? 1 : 0;

    $detailStmt->bind_param(
        'isssssisiiiiiiiiiiiiiiiiiss',
        $insertedId,
        $officeSelected,
        $officeCustomName,
        $officeTitle,
        $transactionType,
        $visitDate,
        $age,
        $regionName,
        $clientTypeCitizen,
        $clientTypeBusiness,
        $clientTypeGovernment,
        $genderMale,
        $genderFemale,
        $cc1Choice,
        $cc2Choice,
        $cc3Choice,
        $sqd0,
        $sqd1,
        $sqd2,
        $sqd3,
        $sqd4,
        $sqd5,
        $sqd6,
        $sqd7,
        $sqd8,
        $suggestions,
        $email
    );

    if (!$detailStmt->execute()) {
        throw new RuntimeException('Failed to save response details.');
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    $stmt->close();
    $detailStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$stmt->close();
$detailStmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Response saved successfully.',
    'id' => $insertedId
]);
