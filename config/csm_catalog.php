<?php
declare(strict_types=1);

function csm_default_offices(): array
{
    return [
        ['code' => 'TAGATALA', 'label' => 'REGISTRY', 'title' => 'PAMBAYANG TANGGAPAN NG TAGATALA'],
        ['code' => 'AGRIKULTURA', 'label' => 'AGRICULTURE', 'title' => 'PAMBAYANG TANGGAPAN NG AGRIKULTURA'],
        ['code' => 'INHINYERO', 'label' => 'ENGINEERING', 'title' => 'PAMBAYANG TANGGAPAN NG INHINYERO'],
        ['code' => 'INGAT-YAMAN', 'label' => 'TREASURY', 'title' => 'PAMBAYANG TANGGAPAN NG INGAT-YAMAN'],
        ['code' => 'KALUSUGANG BAYAN', 'label' => 'RHU', 'title' => 'PAMBAYANG TANGGAPAN NG KALUSUGANG BAYAN'],
        ['code' => 'PAGPAPLANO AT PAGPAPAUNLAD', 'label' => 'PLANNING AND DEVELOPMENT', 'title' => 'PAMBAYANG TANGGAPAN NG PAGPAPLANO AT PAGPAPAUNLAD'],
        ['code' => 'PUNONG BAYAN', 'label' => "MAYOR'S OFFICE", 'title' => 'PAMBAYANG TANGGAPAN NG PUNONG BAYAN'],
        ['code' => 'PAMAMAHALA SA YAMANG TAO', 'label' => 'HRMO (HUMAN RESOURCE MANAGEMENT)', 'title' => 'PAMBAYANG TANGGAPAN NG PAMAMAHALA SA YAMANG TAO'],
        ['code' => 'BADYET', 'label' => 'BUDGET OFFICE', 'title' => 'PAMBAYANG TANGGAPAN NG BADYET'],
        ['code' => 'TAGASURI', 'label' => "ASSESSOR'S OFFICE", 'title' => 'PAMBAYANG TANGGAPAN NG TAGASURI'],
        ['code' => 'KALIKASAN AT LIKAS NA YAMAN', 'label' => 'MENRO (ENVIRONMENT AND NATURAL RESOURCES)', 'title' => 'PAMBAYANG TANGGAPAN NG KALIKASAN AT LIKAS NA YAMAN'],
        ['code' => 'PANANALAPI', 'label' => 'ACCOUNTING', 'title' => 'PAMBAYANG TANGGAPAN NG PANANALAPI'],
        ['code' => 'SISTEMA NG TUBIG', 'label' => 'WATER SYSTEM', 'title' => 'PAMBAYANG TANGGAPAN NG SISTEMA NG TUBIG'],
        ['code' => 'SANGGUNIANG BAYAN', 'label' => 'SANGGUNIANG BAYAN', 'title' => 'PAMBAYANG TANGGAPAN NG SANGGUNIANG BAYAN'],
        ['code' => 'PAMBAYANG PAMAMAHALA SA SAKUNA AT KALAMIDAD', 'label' => 'MDRRMO (DISASTER RISK REDUCTION)', 'title' => 'PAMBAYANG TANGGAPAN NG PAMBAYANG PAMAMAHALA SA SAKUNA AT KALAMIDAD'],
        ['code' => 'TURISMO', 'label' => 'TOURISM', 'title' => 'PAMBAYANG TANGGAPAN NG TURISMO'],
        ['code' => 'BPLO', 'label' => 'BPLO', 'title' => 'PAMBAYANG TANGGAPAN NG BPLO'],
        ['code' => 'PESO', 'label' => 'PESO', 'title' => 'PAMBAYANG TANGGAPAN NG PESO'],
        ['code' => 'BAC', 'label' => 'BAC', 'title' => 'PAMBAYANG TANGGAPAN NG BAC'],
        ['code' => 'COOP', 'label' => 'COOP', 'title' => 'PAMBAYANG TANGGAPAN NG COOP'],
        ['code' => 'GSO', 'label' => 'GSO', 'title' => 'PAMBAYANG TANGGAPAN NG GSO'],
    ];
}

function csm_initialize_catalog(mysqli $conn): bool
{
    $officeTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS csm_offices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_code VARCHAR(150) NOT NULL,
    office_label VARCHAR(255) NOT NULL,
    office_title VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_office_code (office_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

    $serviceTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS csm_office_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_code VARCHAR(150) NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_office_service (office_code, service_name),
    KEY idx_office_code (office_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

    if (!$conn->query($officeTableSql) || !$conn->query($serviceTableSql)) {
        return false;
    }

    $seedStmt = $conn->prepare(
        'INSERT INTO csm_offices (office_code, office_label, office_title, sort_order)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            office_label = VALUES(office_label),
            office_title = VALUES(office_title),
            sort_order = VALUES(sort_order)'
    );

    if (!$seedStmt) {
        return false;
    }

    foreach (csm_default_offices() as $index => $office) {
        $sortOrder = $index + 1;
        $seedStmt->bind_param(
            'sssi',
            $office['code'],
            $office['label'],
            $office['title'],
            $sortOrder
        );
        if (!$seedStmt->execute()) {
            $seedStmt->close();
            return false;
        }
    }

    $seedStmt->close();
    return true;
}

function csm_fetch_offices(mysqli $conn): array
{
    $offices = [];
    $result = $conn->query(
        'SELECT office_code, office_label, office_title
         FROM csm_offices
         WHERE is_active = 1
         ORDER BY sort_order ASC, office_label ASC'
    );

    if (!$result) {
        return $offices;
    }

    while ($row = $result->fetch_assoc()) {
        $offices[] = [
            'code' => (string)($row['office_code'] ?? ''),
            'label' => (string)($row['office_label'] ?? ''),
            'title' => (string)($row['office_title'] ?? ''),
        ];
    }

    $result->free();
    return $offices;
}

function csm_fetch_services_grouped(mysqli $conn): array
{
    $servicesByOffice = [];
    $result = $conn->query(
        'SELECT id, office_code, service_name
         FROM csm_office_services
         WHERE is_active = 1
         ORDER BY office_code ASC, sort_order ASC, service_name ASC'
    );

    if (!$result) {
        return $servicesByOffice;
    }

    while ($row = $result->fetch_assoc()) {
        $officeCode = (string)($row['office_code'] ?? '');
        if ($officeCode === '') {
            continue;
        }

        if (!isset($servicesByOffice[$officeCode])) {
            $servicesByOffice[$officeCode] = [];
        }

        $servicesByOffice[$officeCode][] = [
            'id' => (int)($row['id'] ?? 0),
            'service_name' => (string)($row['service_name'] ?? ''),
        ];
    }

    $result->free();
    return $servicesByOffice;
}
