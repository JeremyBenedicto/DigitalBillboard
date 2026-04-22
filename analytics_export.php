<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/csm_response_schema.php';

function esc_export(string $value): string
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

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$conn->set_charset('utf8mb4');
if (!csm_initialize_response_tables($conn)) {
    http_response_code(500);
    echo 'Failed to prepare CSM tables.';
    exit;
}

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
    $searchValue = '%' . $keyword . '%';
    $where[] = '(transaction_type LIKE ? OR suggestions LIKE ? OR email LIKE ? OR region_name LIKE ?)';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $types .= 'ssss';
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
    echo 'Failed to prepare export query.';
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

$groupName = $officeFilter !== '' ? $officeFilter : 'All Departments';
$summary = build_summary($rows);
$groupTotal = (int)$summary['total'];

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

$filterParts = [];
$filterParts[] = 'Office: ' . ($officeFilter !== '' ? $officeFilter : 'All Departments');
$filterParts[] = 'Service: ' . ($serviceFilter !== '' ? $serviceFilter : 'All Services');
$filterParts[] = 'Month: ' . ($month > 0 ? (string)$month : 'All');
$filterParts[] = 'Year: ' . ($year > 0 ? (string)$year : 'All');
$filterParts[] = 'Keyword: ' . ($keyword !== '' ? $keyword : 'None');
$filterSummary = implode(' | ', $filterParts);

$fileName = 'csm_summary_export_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #eef4f8;
            color: #15324d;
            font-family: Calibri, Arial, sans-serif;
        }
        .sheet {
            background: #ffffff;
            border: 1px solid #d8e3eb;
            border-radius: 24px;
            overflow: hidden;
        }
        .hero {
            padding: 28px 32px;
            background: linear-gradient(135deg, #0d2f4f 0%, #0f5ea8 55%, #198f6f 100%);
            color: #ffffff;
        }
        .kicker {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        h1 {
            margin: 0;
            font-size: 28px;
        }
        .subtitle {
            margin-top: 10px;
            font-size: 13px;
            line-height: 1.6;
            opacity: 0.92;
        }
        .meta-row {
            margin-top: 18px;
        }
        .meta-chip {
            display: inline-block;
            margin: 0 8px 8px 0;
            padding: 7px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.14);
            font-size: 11px;
            font-weight: 700;
        }
        .summary {
            padding: 18px 24px;
            background: #f8fbfd;
            border-bottom: 1px solid #dbe5ee;
            font-size: 12px;
            color: #52667c;
        }
        .section {
            padding: 18px 20px 24px;
        }
        .section-title {
            margin: 0 0 12px;
            font-size: 18px;
            font-weight: 700;
            color: #15324d;
        }
        .grid-2 {
            width: 100%;
        }
        .card {
            margin-bottom: 18px;
            border: 1px solid #d8e3eb;
            border-radius: 18px;
            overflow: hidden;
            background: #ffffff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(180deg, #17486f, #103652);
            color: #ffffff;
            border: 1px solid #284c69;
            padding: 10px 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: center;
        }
        td {
            border: 1px solid #d9e3eb;
            padding: 9px 8px;
            font-size: 12px;
            color: #1f354c;
            text-align: center;
            vertical-align: top;
        }
        td:first-child,
        th:first-child {
            text-align: left;
        }
        tbody tr:nth-child(even) td {
            background: #f7fafc;
        }
        .formula-note {
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 16px;
            background: #f5f9fd;
            border: 1px dashed #b7cada;
            color: #284761;
            text-align: center;
            font-weight: 700;
        }
        .empty {
            padding: 36px 24px;
            text-align: center;
            color: #617689;
            font-size: 14px;
        }
        .spacer {
            height: 14px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="hero">
            <div class="kicker">CSM Summary Export</div>
            <h1>Client Satisfaction Analytics Summary</h1>
            <div class="subtitle">Styled Excel-compatible summary report based on the same tables shown in analytics.</div>
            <div class="meta-row">
                <span class="meta-chip">Generated: <?= esc_export(date('Y-m-d H:i:s')) ?></span>
                <span class="meta-chip">View: <?= esc_export($groupName) ?></span>
                <span class="meta-chip">Responses: <?= $groupTotal ?></span>
            </div>
        </div>

        <div class="summary">
            <strong>Filters:</strong> <?= esc_export($filterSummary) ?>
        </div>

        <div class="section">
            <?php if ($groupTotal === 0): ?>
                <div class="empty">No responses found for the selected filters.</div>
            <?php else: ?>
                <div class="card">
                    <table>
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
                                    <td><?= esc_export($label) ?></td>
                                    <td><strong><?= esc_export(percentage((int)$summary['age'][$key], $groupTotal)) ?></strong></td>
                                    <td>0%</td>
                                    <td><strong><?= esc_export(percentage((int)$summary['age'][$key], $groupTotal)) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr><td colspan="4"></td></tr>
                            <?php foreach ($sexLabels as $key => $label): ?>
                                <tr>
                                    <td><?= esc_export($label) ?></td>
                                    <td><strong><?= esc_export(percentage((int)$summary['sex'][$key], $groupTotal)) ?></strong></td>
                                    <td>0%</td>
                                    <td><strong><?= esc_export(percentage((int)$summary['sex'][$key], $groupTotal)) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <table>
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
                                    <td><?= esc_export($label) ?></td>
                                    <td><strong><?= esc_export(percentage((int)$summary['customer_type'][$key], $groupTotal)) ?></strong></td>
                                    <td>0%</td>
                                    <td><strong><?= esc_export(percentage((int)$summary['customer_type'][$key], $groupTotal)) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <table>
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
                                    <td><strong><?= esc_export($questionLabel) ?></strong></td>
                                    <td><?= $ccTotal ?></td>
                                    <td><?= esc_export($ccTotal > 0 ? '100%' : '0%') ?></td>
                                </tr>
                                <?php foreach ($ccOptionLabels[$ccKey] as $optionKey => $optionLabel): ?>
                                    <tr>
                                        <td><?= esc_export($optionLabel) ?></td>
                                        <td><strong><?= (int)$summary['cc'][$ccKey][$optionKey] ?></strong></td>
                                        <td><strong><?= esc_export(percentage((int)$summary['cc'][$ccKey][$optionKey], $ccTotal)) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($ccKey !== 'cc3'): ?>
                                    <tr><td colspan="3"></td></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>SQD0</th>
                                <?php foreach ($sqdHeaderLabels as $label): ?>
                                    <th><?= esc_export($label) ?></th>
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
                                <td><strong><?= esc_export(overall_score($sqd0)) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Service Quality Dimensions</th>
                                <?php foreach ($sqdHeaderLabels as $label): ?>
                                    <th><?= esc_export($label) ?></th>
                                <?php endforeach; ?>
                                <th>Total Responses</th>
                                <th>Overall</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($dimension = 1; $dimension <= 8; $dimension++): ?>
                                <?php $distribution = $summary['sqd'][$dimension]; ?>
                                <tr>
                                    <td><?= esc_export($dimensionLabels[$dimension]) ?></td>
                                    <td><?= (int)$distribution[5] ?></td>
                                    <td><?= (int)$distribution[4] ?></td>
                                    <td><?= (int)$distribution[3] ?></td>
                                    <td><?= (int)$distribution[2] ?></td>
                                    <td><?= (int)$distribution[1] ?></td>
                                    <td><?= (int)$distribution[6] ?></td>
                                    <td><?= array_sum($distribution) ?></td>
                                    <td><strong><?= esc_export(overall_score($distribution)) ?></strong></td>
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
                                <td><strong><?= esc_export(overall_score($overallDistribution)) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="formula-note">
                    Overall Score = (Number of 'Strongly Agree' answers + Number of 'Agree' answers) / (Total Number of Respondents - Number of 'N/A' answers)
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
