<?php
declare(strict_types=1);

function csm_initialize_response_tables(mysqli $conn): bool
{
    $createResponseTableSql = <<<SQL
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

    $createDetailTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS client_satisfaction_response_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id INT UNSIGNED NOT NULL,
    office_selected VARCHAR(150) NULL,
    office_custom_name VARCHAR(255) NULL,
    office_title VARCHAR(255) NULL,
    transaction_type TEXT NULL,
    visit_date DATE NULL,
    age INT NULL,
    region_name VARCHAR(120) NULL,
    client_type_citizen TINYINT(1) NOT NULL DEFAULT 0,
    client_type_business TINYINT(1) NOT NULL DEFAULT 0,
    client_type_government TINYINT(1) NOT NULL DEFAULT 0,
    gender_male TINYINT(1) NOT NULL DEFAULT 0,
    gender_female TINYINT(1) NOT NULL DEFAULT 0,
    cc1_choice TINYINT UNSIGNED NULL,
    cc2_choice TINYINT UNSIGNED NULL,
    cc3_choice TINYINT UNSIGNED NULL,
    sqd0 TINYINT UNSIGNED NULL,
    sqd1 TINYINT UNSIGNED NULL,
    sqd2 TINYINT UNSIGNED NULL,
    sqd3 TINYINT UNSIGNED NULL,
    sqd4 TINYINT UNSIGNED NULL,
    sqd5 TINYINT UNSIGNED NULL,
    sqd6 TINYINT UNSIGNED NULL,
    sqd7 TINYINT UNSIGNED NULL,
    sqd8 TINYINT UNSIGNED NULL,
    suggestions TEXT NULL,
    email VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_response_id (response_id),
    CONSTRAINT fk_csm_response_details_response
        FOREIGN KEY (response_id) REFERENCES client_satisfaction_responses(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

    return $conn->query($createResponseTableSql) && $conn->query($createDetailTableSql);
}
