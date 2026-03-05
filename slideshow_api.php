<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';

if (!isset($conn) || !$conn) {
    if (!isset($db_host, $db_username, $db_password, $db_name)) {
        http_response_code(500);
        echo json_encode(['error' => 'Database configuration is incomplete']);
        exit;
    }

    $bootstrapConn = @new mysqli($db_host, $db_username, $db_password);
    if ($bootstrapConn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    $bootstrapConn->set_charset('utf8mb4');
    if (!$bootstrapConn->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to initialize database']);
        exit;
    }
    $bootstrapConn->close();

    $conn = @new mysqli($db_host, $db_username, $db_password, $db_name);
}

$conn->set_charset('utf8mb4');
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to select database']);
    exit;
}

$sql = <<<SQL
CREATE TABLE IF NOT EXISTS slideshow_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
$conn->query($sql);

$result = $conn->query('SELECT id, file_path FROM slideshow_images ORDER BY id ASC');
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch slideshow images']);
    exit;
}

$images = [];
while ($row = $result->fetch_assoc()) {
    $images[] = [
        'id' => (int)$row['id'],
        'src' => (string)$row['file_path'],
    ];
}
$result->free();

echo json_encode($images);
