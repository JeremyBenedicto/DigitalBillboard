<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/csm_catalog.php';
require_once __DIR__ . '/config/csm_response_schema.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$conn->set_charset('utf8mb4');
if (!csm_initialize_catalog($conn) || !csm_initialize_response_tables($conn)) {
    http_response_code(500);
    echo 'Failed to prepare CSM tables.';
    exit;
}

function fetch_table_rows(mysqli $conn, string $table, string $orderBy): array
{
    $rows = [];
    $result = $conn->query("SELECT * FROM {$table} ORDER BY {$orderBy}");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

$payload = [
    'exported_at' => date('c'),
    'source' => 'digitalBillboard CSM backup',
    'tables' => [
        'csm_offices' => fetch_table_rows($conn, 'csm_offices', 'id ASC'),
        'csm_office_services' => fetch_table_rows($conn, 'csm_office_services', 'id ASC'),
        'client_satisfaction_responses' => fetch_table_rows($conn, 'client_satisfaction_responses', 'id ASC'),
        'client_satisfaction_response_details' => fetch_table_rows($conn, 'client_satisfaction_response_details', 'id ASC'),
    ],
];

$fileName = 'csm_backup_' . date('Ymd_His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
