<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function office_name_from_row(array $row): string
{
    $office = trim((string)($row['office_custom_name'] ?? ''));
    if ($office === '') {
        $office = trim((string)($row['office_selected'] ?? ''));
    }
    if ($office === '') {
        $office = trim((string)($row['office_title'] ?? ''));
    }

    return $office !== '' ? $office : 'Unspecified';
}

function bool_labels(array $values, array $labels): array
{
    $result = [];
    foreach ($labels as $key => $label) {
        if (!empty($values[$key])) {
            $result[] = $label;
        }
    }
    return $result;
}

function selected_choice_label(array $values, array $labels): string
{
    foreach (array_values($values) as $index => $selected) {
        if ($selected) {
            return $labels[$index] ?? '-';
        }
    }
    return '-';
}

function selected_choice_index(array $values): ?int
{
    foreach (array_values($values) as $index => $selected) {
        if ($selected) {
            return $index + 1;
        }
    }

    return null;
}

function percentage(int $count, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    $value = round(($count / $total) * 100, 2);
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
}

function overall_score(array $distribution): string
{
    $total = array_sum($distribution);
    $na = (int)($distribution[6] ?? 0);
    $denominator = $total - $na;
    if ($denominator <= 0) {
        return '0%';
    }

    $agree = (int)($distribution[5] ?? 0) + (int)($distribution[4] ?? 0);
    return percentage($agree, $denominator);
}

function age_bucket(?int $age): string
{
    if ($age === null || $age <= 0) {
        return 'did_not_specify';
    }
    if ($age <= 19) {
        return '19_or_lower';
    }
    if ($age <= 34) {
        return '20_34';
    }
    if ($age <= 49) {
        return '35_49';
    }
    if ($age <= 64) {
        return '50_64';
    }
    return '65_or_higher';
}

function build_summary(array $rows): array
{
    $summary = [
        'total' => count($rows),
        'age' => [
            '19_or_lower' => 0,
            '20_34' => 0,
            '35_49' => 0,
            '50_64' => 0,
            '65_or_higher' => 0,
            'did_not_specify' => 0,
        ],
        'sex' => [
            'male' => 0,
            'female' => 0,
            'did_not_specify' => 0,
        ],
        'customer_type' => [
            'citizen' => 0,
            'business' => 0,
            'government' => 0,
            'did_not_specify' => 0,
        ],
        'cc' => [
            'cc1' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
            'cc2' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'cc3' => [1 => 0, 2 => 0, 3 => 0, 4 => 0],
        ],
        'sqd' => [],
    ];

    for ($i = 0; $i <= 8; $i++) {
        $summary['sqd'][$i] = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
    }

    foreach ($rows as $row) {
        $age = isset($row['age']) && $row['age'] !== '' ? (int)$row['age'] : null;
        $summary['age'][age_bucket($age)]++;

        $gender = json_decode((string)($row['gender_json'] ?? ''), true);
        $hasMale = is_array($gender) && !empty($gender['lalaki']);
        $hasFemale = is_array($gender) && !empty($gender['babae']);
        if ($hasMale) {
            $summary['sex']['male']++;
        }
        if ($hasFemale) {
            $summary['sex']['female']++;
        }
        if (!$hasMale && !$hasFemale) {
            $summary['sex']['did_not_specify']++;
        }

        $clientType = json_decode((string)($row['client_type_json'] ?? ''), true);
        $hasCitizen = is_array($clientType) && !empty($clientType['mamamayan']);
        $hasBusiness = is_array($clientType) && !empty($clientType['negosyo']);
        $hasGovernment = is_array($clientType) && !empty($clientType['gobyerno']);
        if ($hasCitizen) {
            $summary['customer_type']['citizen']++;
        }
        if ($hasBusiness) {
            $summary['customer_type']['business']++;
        }
        if ($hasGovernment) {
            $summary['customer_type']['government']++;
        }
        if (!$hasCitizen && !$hasBusiness && !$hasGovernment) {
            $summary['customer_type']['did_not_specify']++;
        }

        $cc1 = json_decode((string)($row['cc1_json'] ?? ''), true);
        $cc2 = json_decode((string)($row['cc2_json'] ?? ''), true);
        $cc3 = json_decode((string)($row['cc3_json'] ?? ''), true);
        $cc1Index = is_array($cc1) ? selected_choice_index($cc1) : null;
        $cc2Index = is_array($cc2) ? selected_choice_index($cc2) : null;
        $cc3Index = is_array($cc3) ? selected_choice_index($cc3) : null;
        if ($cc1Index !== null && isset($summary['cc']['cc1'][$cc1Index])) {
            $summary['cc']['cc1'][$cc1Index]++;
        }
        if ($cc2Index !== null && isset($summary['cc']['cc2'][$cc2Index])) {
            $summary['cc']['cc2'][$cc2Index]++;
        }
        if ($cc3Index !== null && isset($summary['cc']['cc3'][$cc3Index])) {
            $summary['cc']['cc3'][$cc3Index]++;
        }

        $sqd = json_decode((string)($row['sqd_json'] ?? ''), true);
        if (!is_array($sqd)) {
            continue;
        }

        for ($i = 0; $i <= 8; $i++) {
            $key = 'sqd' . $i;
            if (!isset($sqd[$key]) || $sqd[$key] === '') {
                continue;
            }

            $value = (int)$sqd[$key];
            if ($value >= 1 && $value <= 6) {
                $summary['sqd'][$i][$value]++;
            }
        }
    }

    return $summary;
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$conn->set_charset('utf8mb4');

$officeExpr = "COALESCE(NULLIF(TRIM(office_custom_name), ''), NULLIF(TRIM(office_selected), ''), NULLIF(TRIM(office_title), ''), 'Unspecified')";
$dateExpr = 'DATE(COALESCE(visit_date, created_at))';

$officeFilter = isset($_GET['office']) ? trim((string)$_GET['office']) : '';
$serviceFilter = isset($_GET['service']) ? trim((string)$_GET['service']) : '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

$where = [];
$params = [];
$types = '';

if ($officeFilter !== '') {
    $where[] = "{$officeExpr} = ?";
    $params[] = $officeFilter;
    $types .= 's';
}

if ($serviceFilter !== '') {
    $where[] = 'TRIM(COALESCE(transaction_type, \'\')) = ?';
    $params[] = $serviceFilter;
    $types .= 's';
}

if ($month >= 1 && $month <= 12) {
    $where[] = "MONTH($dateExpr) = ?";
    $params[] = $month;
    $types .= 'i';
} else {
    $month = 0;
}

if ($year >= 1900 && $year <= 9999) {
    $where[] = "YEAR($dateExpr) = ?";
    $params[] = $year;
    $types .= 'i';
} else {
    $year = 0;
}

if ($keyword !== '') {
    $where[] = '(transaction_type LIKE ? OR suggestions LIKE ? OR email LIKE ? OR region_name LIKE ?)';
    $searchValue = '%' . $keyword . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $types .= 'ssss';
}

$officeOptions = [];
$officeResult = $conn->query("SELECT DISTINCT {$officeExpr} AS office_name FROM client_satisfaction_responses ORDER BY office_name ASC");
if ($officeResult) {
    while ($row = $officeResult->fetch_assoc()) {
        $name = trim((string)($row['office_name'] ?? ''));
        if ($name !== '') {
            $officeOptions[] = $name;
        }
    }
    $officeResult->free();
}

$serviceOptions = [];
$serviceSql = 'SELECT DISTINCT TRIM(transaction_type) AS service_name FROM client_satisfaction_responses WHERE TRIM(COALESCE(transaction_type, \'\')) <> \'\'';
$serviceParams = [];
$serviceTypes = '';
if ($officeFilter !== '') {
    $serviceSql .= " AND {$officeExpr} = ?";
    $serviceParams[] = $officeFilter;
    $serviceTypes .= 's';
}
$serviceSql .= ' ORDER BY service_name ASC';
$serviceStmt = $conn->prepare($serviceSql);
if ($serviceStmt) {
    if (count($serviceParams) > 0) {
        $serviceStmt->bind_param($serviceTypes, ...$serviceParams);
    }
    $serviceStmt->execute();
    $serviceResult = $serviceStmt->get_result();
    while ($serviceResult && ($row = $serviceResult->fetch_assoc())) {
        $name = trim((string)($row['service_name'] ?? ''));
        if ($name !== '') {
            $serviceOptions[] = $name;
        }
    }
    $serviceStmt->close();
}

$yearOptions = [];
$yearResult = $conn->query("SELECT DISTINCT YEAR($dateExpr) AS yr FROM client_satisfaction_responses WHERE $dateExpr IS NOT NULL ORDER BY yr DESC");
if ($yearResult) {
    while ($row = $yearResult->fetch_assoc()) {
        $value = (int)($row['yr'] ?? 0);
        if ($value > 0) {
            $yearOptions[] = $value;
        }
    }
    $yearResult->free();
}

$sql = 'SELECT id, office_selected, office_custom_name, office_title, visit_date, region_name, age, transaction_type, client_type_json, gender_json, cc1_json, cc2_json, cc3_json, sqd_json, suggestions, email, created_at
        FROM client_satisfaction_responses';
if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $officeExpr . ' ASC, COALESCE(visit_date, created_at) DESC, id DESC';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo 'Failed to prepare analytics query.';
    exit;
}

if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($result && ($row = $result->fetch_assoc())) {
    $row['office_display'] = office_name_from_row($row);
    $rows[] = $row;
}
$stmt->close();

$groupedResponses = [];
foreach ($rows as $row) {
    $groupedResponses[$row['office_display']][] = $row;
}

$allAnalyticsGroups = ['All Departments' => $rows];
foreach ($groupedResponses as $officeName => $officeRows) {
    $allAnalyticsGroups[$officeName] = $officeRows;
}

$analyticsGroups = [];
if ($officeFilter !== '') {
    $analyticsGroups[$officeFilter] = $groupedResponses[$officeFilter] ?? [];
} else {
    $analyticsGroups['All Departments'] = $rows;
}

$analyticsSummaries = [];
foreach ($analyticsGroups as $groupName => $groupRows) {
    $analyticsSummaries[$groupName] = build_summary($groupRows);
}

$ageLabels = [
    '19_or_lower' => '1. 19 or lower',
    '20_34' => '2. 20-34',
    '35_49' => '3. 35-49',
    '50_64' => '4. 50-64',
    '65_or_higher' => '5. 65 or higher',
    'did_not_specify' => '6. Did not specify',
];
$sexLabels = [
    'male' => '1. Male',
    'female' => '2. Female',
    'did_not_specify' => '3. Did not specify',
];
$customerTypeLabels = [
    'citizen' => 'D4. Citizen',
    'business' => 'D4. Business',
    'government' => 'D4. Government',
    'did_not_specify' => 'D4. Did not specify',
];
$ccQuestionLabels = [
    'cc1' => 'CC1. Which of the following describes your awareness of the CC?',
    'cc2' => 'CC2. If aware of CC, would you say that the CC of this office was...?',
    'cc3' => 'CC3. If aware of CC, how much did the CC help you in your transaction?',
];
$ccOptionLabels = [
    'cc1' => [
        1 => '1. I know what a CC is and I saw this office\'s CC.',
        2 => '2. I know what a CC is but I did not see this office\'s CC.',
        3 => '3. I learned of the CC only when I saw this office\'s CC.',
        4 => '4. I do not know what a CC is and I did not see this office\'s CC.',
    ],
    'cc2' => [
        1 => '1. Easy to see',
        2 => '2. Somewhat easy to see',
        3 => '3. Difficult to see',
        4 => '4. Not visible at all',
        5 => '5. N/A',
    ],
    'cc3' => [
        1 => '1. Helped very much',
        2 => '2. Somewhat helped',
        3 => '3. Did not help',
        4 => '4. N/A',
    ],
];
$sqdHeaderLabels = [
    5 => 'Strongly Agree',
    4 => 'Agree',
    3 => 'Neither Agree nor Disagree',
    2 => 'Disagree',
    1 => 'Strongly Disagree',
    6 => 'N/A',
];
$dimensionLabels = [
    1 => 'Responsiveness',
    2 => 'Reliability',
    3 => 'Access and Facilities',
    4 => 'Communication',
    5 => 'Costs',
    6 => 'Integrity',
    7 => 'Assurance',
    8 => 'Outcome',
];

$totalResponses = count($rows);
$latestSaved = $totalResponses > 0 ? (string)$rows[0]['created_at'] : 'N/A';
$activeViewLabel = $officeFilter !== '' ? $officeFilter : 'All Departments';
$exportQuery = http_build_query([
    'office' => $officeFilter,
    'service' => $serviceFilter,
    'month' => $month > 0 ? $month : '',
    'year' => $year > 0 ? $year : '',
    'keyword' => $keyword,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSM Office Analytics</title>
    <style>
        :root {
            --bg: #edf3f7;
            --card: rgba(255, 255, 255, 0.88);
            --card-solid: #ffffff;
            --line: rgba(149, 163, 184, 0.22);
            --line-strong: rgba(80, 104, 132, 0.22);
            --text: #14324b;
            --muted: #5f7288;
            --brand: #0f5ea8;
            --brand-deep: #0d2f4f;
            --brand-soft: rgba(47, 122, 194, 0.12);
            --accent: #0f8a5b;
            --accent-soft: rgba(15, 138, 91, 0.1);
            --shadow-lg: 0 28px 60px rgba(16, 42, 67, 0.14);
            --shadow-md: 0 18px 36px rgba(16, 42, 67, 0.08);
            --shadow-sm: 0 10px 24px rgba(16, 42, 67, 0.05);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                radial-gradient(circle at top left, rgba(15, 94, 168, 0.14), transparent 24%),
                radial-gradient(circle at 85% 12%, rgba(15, 138, 91, 0.10), transparent 18%),
                linear-gradient(180deg, #f8fbfd 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Georgia, "Times New Roman", serif;
        }
        .wrap {
            max-width: 1440px;
            margin: 0 auto;
            padding: 28px;
        }
        .hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 22px;
            padding: 28px 30px;
            border-radius: 30px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.16), transparent 28%),
                linear-gradient(135deg, #0d2f4f 0%, #105d91 56%, #1d8e6a 100%);
            color: #fff;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        .hero::after {
            content: "";
            position: absolute;
            inset: auto -90px -120px auto;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
        }
        .hero h1 {
            margin: 0;
            font-size: 38px;
            letter-spacing: 0.02em;
        }
        .hero p {
            margin: 10px 0 0;
            color: rgba(255,255,255,0.82);
            font-size: 15px;
            line-height: 1.6;
            max-width: 720px;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .hero-copy,
        .hero-actions {
            position: relative;
            z-index: 1;
        }
        .hero-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.14);
            font: 700 12px/1 "Segoe UI", Arial, sans-serif;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .hero-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .hero-stat {
            min-width: 140px;
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
        }
        .hero-stat .meta {
            display: block;
            color: rgba(255,255,255,0.72);
            font: 700 11px/1.2 "Segoe UI", Arial, sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .hero-stat strong {
            display: block;
            margin-top: 8px;
            font-size: 24px;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(180deg, #ffffff, #eef5fb);
            color: var(--brand-deep);
            box-shadow: 0 10px 18px rgba(12, 31, 51, 0.10);
            font-family: "Segoe UI", Arial, sans-serif;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 24px rgba(12, 31, 51, 0.14);
        }
        .btn.secondary {
            background: rgba(255,255,255,0.10);
            color: #fff;
            border-color: rgba(255,255,255,0.16);
            box-shadow: none;
        }
        .filters,
        .office-card,
        .summary-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(16px);
        }
        .filters {
            margin-bottom: 22px;
            padding: 20px;
        }
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            align-items: end;
        }
        .field {
            display: grid;
            gap: 8px;
        }
        .field label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .field input,
        .field select {
            width: 100%;
            border: 1px solid var(--line-strong);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255,255,255,0.9);
            font-size: 14px;
            color: var(--text);
            font-family: "Segoe UI", Arial, sans-serif;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.45);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .summary-card {
            padding: 18px;
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--brand), #35a46e);
        }
        .summary-card .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .summary-card .value {
            margin-top: 12px;
            font-size: 34px;
            font-weight: 800;
            color: var(--brand-deep);
        }
        .summary-card .value.small {
            font-size: 18px;
            line-height: 1.4;
            color: var(--text);
        }
        .report-section {
            display: grid;
            gap: 22px;
            margin-bottom: 24px;
        }
        .report-card {
            background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(247,250,252,0.96));
            border: 1px solid var(--line);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        .report-card .office-head {
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }
        .report-body {
            padding: 22px;
            display: grid;
            gap: 20px;
        }
        .report-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .report-table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 22px;
            background: rgba(255,255,255,0.88);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.45);
        }
        .report-table {
            width: 100%;
            min-width: 640px;
            border-collapse: collapse;
        }
        .report-table th,
        .report-table td {
            border: 1px solid rgba(25, 50, 74, 0.12);
            padding: 10px 12px;
            font-size: 13px;
            text-align: center;
        }
        .report-table th {
            background: linear-gradient(180deg, #1b5f94, #144a74);
            color: #fff;
            font-weight: 800;
            letter-spacing: 0.03em;
        }
        .report-table td:first-child,
        .report-table th:first-child {
            text-align: left;
        }
        .report-table td strong {
            font-weight: 800;
            color: var(--brand-deep);
        }
        .formula-note {
            padding: 18px 20px;
            border-radius: 18px;
            background: linear-gradient(180deg, rgba(15, 94, 168, 0.07), rgba(15, 138, 91, 0.06));
            border: 1px dashed rgba(15, 94, 168, 0.22);
            color: #24425d;
            font-weight: 700;
            text-align: center;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .office-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 22px 24px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.10), transparent 28%),
                linear-gradient(135deg, #0e314f, #165785);
            color: #fff;
        }
        .office-head h2 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 0.01em;
        }
        .office-meta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 126px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            color: #fff;
            font-weight: 800;
            font-size: 13px;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .empty {
            padding: 44px 28px;
            text-align: center;
            color: var(--muted);
            background: rgba(255,255,255,0.86);
            border: 1px dashed rgba(98, 116, 136, 0.32);
            border-radius: 24px;
            box-shadow: var(--shadow-sm);
            font: 600 16px/1.6 "Segoe UI", Arial, sans-serif;
        }
        @media (max-width: 900px) {
            .wrap {
                padding: 16px;
            }
            .hero {
                flex-direction: column;
                border-radius: 24px;
                padding: 22px 20px;
            }
            .office-head {
                flex-direction: column;
                align-items: flex-start;
            }
            .summary-card .value {
                font-size: 24px;
            }
            .report-grid {
                grid-template-columns: 1fr;
            }
            .hero h1 {
                font-size: 30px;
            }
        }
        @media (max-width: 640px) {
            .wrap {
                padding: 12px;
            }
            .filters,
            .summary-card,
            .report-card {
                border-radius: 20px;
            }
            .hero {
                padding: 20px 18px;
            }
            .hero h1 {
                font-size: 26px;
            }
            .hero-stats {
                gap: 10px;
            }
            .hero-stat {
                min-width: 0;
                flex: 1 1 130px;
            }
            .report-body {
                padding: 16px;
            }
            .report-table-wrap {
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <section class="hero">
            <div class="hero-copy">
                <div class="hero-kicker">CSM Dashboard</div>
                <h1>CSM Office Analytics</h1>
                <p>Track service quality, compare departments, and export filtered response data from `client_satisfaction.php` in one cleaner reporting view.</p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="meta">Responses</span>
                        <strong><?= (int)$totalResponses ?></strong>
                    </div>
                    <div class="hero-stat">
                        <span class="meta">Active View</span>
                        <strong style="font-size:18px; line-height:1.35;"><?= esc($activeViewLabel) ?></strong>
                    </div>
                    <div class="hero-stat">
                        <span class="meta">Latest Saved</span>
                        <strong style="font-size:18px; line-height:1.35;"><?= esc($latestSaved) ?></strong>
                    </div>
                </div>
            </div>
            <div class="actions hero-actions">
                <a class="btn" href="analytics_export.php<?= $exportQuery !== '' ? '?' . esc($exportQuery) : '' ?>">Export to Excel</a>
                <button class="btn" onclick="window.print()">Print</button>
                <a class="btn secondary" href="admin.php">Back to Admin</a>
            </div>
        </section>

        <section class="filters">
            <form method="get">
                <div class="field">
                    <label for="office">Office</label>
                    <select id="office" name="office">
                        <option value="">All Offices</option>
                        <?php foreach ($officeOptions as $office): ?>
                            <option value="<?= esc($office) ?>" <?= $officeFilter === $office ? 'selected' : '' ?>><?= esc($office) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="service">Service</label>
                    <select id="service" name="service">
                        <option value="">All Services</option>
                        <?php foreach ($serviceOptions as $service): ?>
                            <option value="<?= esc($service) ?>" <?= $serviceFilter === $service ? 'selected' : '' ?>><?= esc($service) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="month">Month</label>
                    <select id="month" name="month">
                        <option value="">All Months</option>
                        <option value="1" <?= $month === 1 ? 'selected' : '' ?>>January</option>
                        <option value="2" <?= $month === 2 ? 'selected' : '' ?>>February</option>
                        <option value="3" <?= $month === 3 ? 'selected' : '' ?>>March</option>
                        <option value="4" <?= $month === 4 ? 'selected' : '' ?>>April</option>
                        <option value="5" <?= $month === 5 ? 'selected' : '' ?>>May</option>
                        <option value="6" <?= $month === 6 ? 'selected' : '' ?>>June</option>
                        <option value="7" <?= $month === 7 ? 'selected' : '' ?>>July</option>
                        <option value="8" <?= $month === 8 ? 'selected' : '' ?>>August</option>
                        <option value="9" <?= $month === 9 ? 'selected' : '' ?>>September</option>
                        <option value="10" <?= $month === 10 ? 'selected' : '' ?>>October</option>
                        <option value="11" <?= $month === 11 ? 'selected' : '' ?>>November</option>
                        <option value="12" <?= $month === 12 ? 'selected' : '' ?>>December</option>
                    </select>
                </div>
                <div class="field">
                    <label for="year">Year</label>
                    <select id="year" name="year">
                        <option value="">All Years</option>
                        <?php foreach ($yearOptions as $yr): ?>
                            <option value="<?= (int)$yr ?>" <?= $year === (int)$yr ? 'selected' : '' ?>><?= (int)$yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="keyword">Keyword</label>
                    <input type="text" id="keyword" name="keyword" value="<?= esc($keyword) ?>" placeholder="Service, region, email, suggestion">
                </div>
                <div class="actions">
                    <button class="btn" type="submit">Apply</button>
                    <a class="btn secondary" href="analytics.php">Reset</a>
                </div>
            </form>
        </section>

        <section class="summary-grid">
            <article class="summary-card">
                <div class="label">Total Responses</div>
                <div class="value"><?= (int)$totalResponses ?></div>
            </article>
            <article class="summary-card">
                <div class="label">Offices With Responses</div>
                <div class="value"><?= (int)count($groupedResponses) ?></div>
            </article>
            <article class="summary-card">
                <div class="label">Active View</div>
                <div class="value small"><?= esc($activeViewLabel) ?></div>
            </article>
            <article class="summary-card">
                <div class="label">Service Filter</div>
                <div class="value small"><?= esc($serviceFilter !== '' ? $serviceFilter : 'All Services') ?></div>
            </article>
            <article class="summary-card">
                <div class="label">Latest Saved</div>
                <div class="value small"><?= esc($latestSaved) ?></div>
            </article>
        </section>

        <section class="report-section">
            <?php if ($totalResponses === 0): ?>
                <div class="empty">No responses found for the selected filters.</div>
            <?php else: ?>
                <?php foreach ($analyticsGroups as $groupName => $groupRows): ?>
                    <?php $summary = $analyticsSummaries[$groupName]; ?>
                    <?php $groupTotal = (int)$summary['total']; ?>
                    <article class="report-card">
                        <div class="office-head">
                            <h2><?= esc($groupName) ?></h2>
                            <div class="office-meta"><?= $groupTotal ?> response(s)</div>
                        </div>
                        <div class="report-body">
                            <div class="report-grid">
                                <div class="report-table-wrap">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <th>D1. Age and D2. Sex</th>
                                                <th>External</th>
                                                <th>Internal</th>
                                                <th>Overall</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ageLabels as $key => $label): ?>
                                                <tr>
                                                    <td><?= esc($label) ?></td>
                                                    <td><strong><?= esc(percentage((int)$summary['age'][$key], $groupTotal)) ?></strong></td>
                                                    <td>0%</td>
                                                    <td><strong><?= esc(percentage((int)$summary['age'][$key], $groupTotal)) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr><td colspan="4"></td></tr>
                                            <?php foreach ($sexLabels as $key => $label): ?>
                                                <tr>
                                                    <td><?= esc($label) ?></td>
                                                    <td><strong><?= esc(percentage((int)$summary['sex'][$key], $groupTotal)) ?></strong></td>
                                                    <td>0%</td>
                                                    <td><strong><?= esc(percentage((int)$summary['sex'][$key], $groupTotal)) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="report-table-wrap">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <th>Customer Type</th>
                                                <th>External</th>
                                                <th>Internal</th>
                                                <th>Overall</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customerTypeLabels as $key => $label): ?>
                                                <tr>
                                                    <td><?= esc($label) ?></td>
                                                    <td><strong><?= esc(percentage((int)$summary['customer_type'][$key], $groupTotal)) ?></strong></td>
                                                    <td>0%</td>
                                                    <td><strong><?= esc(percentage((int)$summary['customer_type'][$key], $groupTotal)) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="report-table-wrap">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Citizen's Charter Answers</th>
                                            <th>Responses</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ccQuestionLabels as $ccKey => $questionLabel): ?>
                                            <?php $ccTotal = array_sum($summary['cc'][$ccKey]); ?>
                                            <tr>
                                                <td><strong><?= esc($questionLabel) ?></strong></td>
                                                <td><?= $ccTotal ?></td>
                                                <td><?= esc($ccTotal > 0 ? '100%' : '0%') ?></td>
                                            </tr>
                                            <?php foreach ($ccOptionLabels[$ccKey] as $optionKey => $optionLabel): ?>
                                                <tr>
                                                    <td><?= esc($optionLabel) ?></td>
                                                    <td><strong><?= (int)$summary['cc'][$ccKey][$optionKey] ?></strong></td>
                                                    <td><strong><?= esc(percentage((int)$summary['cc'][$ccKey][$optionKey], $ccTotal)) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if ($ccKey !== 'cc3'): ?>
                                                <tr><td colspan="3"></td></tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="report-table-wrap">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>SQD0</th>
                                            <?php foreach ($sqdHeaderLabels as $label): ?>
                                                <th><?= esc($label) ?></th>
                                            <?php endforeach; ?>
                                            <th>Total Responses</th>
                                            <th>Overall</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sqd0 = $summary['sqd'][0]; ?>
                                        <tr>
                                            <td>SQD0</td>
                                            <td><?= (int)$sqd0[5] ?></td>
                                            <td><?= (int)$sqd0[4] ?></td>
                                            <td><?= (int)$sqd0[3] ?></td>
                                            <td><?= (int)$sqd0[2] ?></td>
                                            <td><?= (int)$sqd0[1] ?></td>
                                            <td><?= (int)$sqd0[6] ?></td>
                                            <td><?= array_sum($sqd0) ?></td>
                                            <td><strong><?= esc(overall_score($sqd0)) ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="report-table-wrap">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Service Quality Dimensions</th>
                                            <?php foreach ($sqdHeaderLabels as $label): ?>
                                                <th><?= esc($label) ?></th>
                                            <?php endforeach; ?>
                                            <th>Total Responses</th>
                                            <th>Overall</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($dimension = 1; $dimension <= 8; $dimension++): ?>
                                            <?php $distribution = $summary['sqd'][$dimension]; ?>
                                            <tr>
                                                <td><?= esc($dimensionLabels[$dimension]) ?></td>
                                                <td><?= (int)$distribution[5] ?></td>
                                                <td><?= (int)$distribution[4] ?></td>
                                                <td><?= (int)$distribution[3] ?></td>
                                                <td><?= (int)$distribution[2] ?></td>
                                                <td><?= (int)$distribution[1] ?></td>
                                                <td><?= (int)$distribution[6] ?></td>
                                                <td><?= array_sum($distribution) ?></td>
                                                <td><strong><?= esc(overall_score($distribution)) ?></strong></td>
                                            </tr>
                                        <?php endfor; ?>
                                        <?php
                                        $overallDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
                                        for ($dimension = 1; $dimension <= 8; $dimension++) {
                                            foreach ($summary['sqd'][$dimension] as $value => $count) {
                                                $overallDistribution[$value] += $count;
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><strong>Overall</strong></td>
                                            <td><?= (int)$overallDistribution[5] ?></td>
                                            <td><?= (int)$overallDistribution[4] ?></td>
                                            <td><?= (int)$overallDistribution[3] ?></td>
                                            <td><?= (int)$overallDistribution[2] ?></td>
                                            <td><?= (int)$overallDistribution[1] ?></td>
                                            <td><?= (int)$overallDistribution[6] ?></td>
                                            <td><?= array_sum($overallDistribution) ?></td>
                                            <td><strong><?= esc(overall_score($overallDistribution)) ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="formula-note">
                                Overall Score = (Number of 'Strongly Agree' answers + Number of 'Agree' answers) / (Total Number of Respondents - Number of 'N/A' answers)
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
