<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$department = isset($_GET['department']) ? trim((string)$_GET['department']) : '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$where = [];
$params = [];
$types = '';
$officeExpr = "COALESCE(NULLIF(TRIM(office_custom_name), ''), NULLIF(TRIM(office_selected), ''), NULLIF(TRIM(office_title), ''), 'Unspecified')";
$dateExpr = 'DATE(COALESCE(visit_date, created_at))';

$departmentOptions = [];
$deptRes = $conn->query("SELECT DISTINCT {$officeExpr} AS department_name FROM client_satisfaction_responses ORDER BY department_name ASC");
if ($deptRes) {
    while ($dept = $deptRes->fetch_assoc()) {
        $name = trim((string)($dept['department_name'] ?? ''));
        if ($name !== '') {
            $departmentOptions[] = $name;
        }
    }
    $deptRes->free();
}

$yearOptions = [];
$yearRes = $conn->query("SELECT DISTINCT YEAR($dateExpr) AS yr FROM client_satisfaction_responses WHERE $dateExpr IS NOT NULL ORDER BY yr DESC");
if ($yearRes) {
    while ($yr = $yearRes->fetch_assoc()) {
        $value = isset($yr['yr']) ? (int)$yr['yr'] : 0;
        if ($value > 0) {
            $yearOptions[] = $value;
        }
    }
    $yearRes->free();
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = "$dateExpr >= ?";
    $params[] = $dateFrom;
    $types .= 's';
} else {
    $dateFrom = '';
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = "$dateExpr <= ?";
    $params[] = $dateTo;
    $types .= 's';
} else {
    $dateTo = '';
}

if ($department !== '') {
    $where[] = "{$officeExpr} = ?";
    $params[] = $department;
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

$sql = 'SELECT id, office_selected, office_custom_name, office_title, visit_date, region_name, client_type_json, gender_json, sqd_json, created_at
        FROM client_satisfaction_responses';
if (count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC';

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
    $rows[] = $row;
}
$stmt->close();

$totalResponses = count($rows);
$officeCounts = [];
$regionCounts = [];
$clientTypeCounts = [
    'Mamamayan' => 0,
    'Negosyo' => 0,
    'Gobyerno' => 0
];
$genderCounts = [
    'Lalaki' => 0,
    'Babae' => 0
];
$sqdSums = array_fill(0, 9, 0.0);
$sqdCounts = array_fill(0, 9, 0);
$recentRows = array_slice($rows, 0, 20);

foreach ($rows as $row) {
    $office = trim((string)$row['office_custom_name']);
    if ($office === '') {
        $office = trim((string)$row['office_selected']);
    }
    if ($office === '') {
        $office = trim((string)$row['office_title']);
    }
    if ($office === '') {
        $office = 'Unspecified';
    }
    $officeCounts[$office] = ($officeCounts[$office] ?? 0) + 1;

    $region = trim((string)$row['region_name']);
    if ($region === '') {
        $region = 'Unspecified';
    }
    $regionCounts[$region] = ($regionCounts[$region] ?? 0) + 1;

    $clientType = json_decode((string)$row['client_type_json'], true);
    if (is_array($clientType)) {
        if (!empty($clientType['mamamayan'])) { $clientTypeCounts['Mamamayan']++; }
        if (!empty($clientType['negosyo'])) { $clientTypeCounts['Negosyo']++; }
        if (!empty($clientType['gobyerno'])) { $clientTypeCounts['Gobyerno']++; }
    }

    $gender = json_decode((string)$row['gender_json'], true);
    if (is_array($gender)) {
        if (!empty($gender['lalaki'])) { $genderCounts['Lalaki']++; }
        if (!empty($gender['babae'])) { $genderCounts['Babae']++; }
    }

    $sqd = json_decode((string)$row['sqd_json'], true);
    if (!is_array($sqd)) {
        continue;
    }
    for ($i = 0; $i <= 8; $i++) {
        $key = 'sqd' . $i;
        if (!isset($sqd[$key]) || $sqd[$key] === null || $sqd[$key] === '') {
            continue;
        }
        $value = (int)$sqd[$key];
        if ($value >= 1 && $value <= 6) {
            $sqdSums[$i] += $value;
            $sqdCounts[$i]++;
        }
    }
}

arsort($officeCounts);
arsort($regionCounts);

$topOffices = array_slice($officeCounts, 0, 10, true);
$topRegions = array_slice($regionCounts, 0, 10, true);

$sqdAverages = [];
for ($i = 0; $i <= 8; $i++) {
    $avg = $sqdCounts[$i] > 0 ? ($sqdSums[$i] / $sqdCounts[$i]) : 0;
    $sqdAverages[$i] = round($avg, 2);
}

$maxOfficeCount = count($topOffices) > 0 ? max($topOffices) : 1;
$maxRegionCount = count($topRegions) > 0 ? max($topRegions) : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSM Analytics Report</title>
    <style>
        :root {
            --bg: #f2f6fb;
            --card: #ffffff;
            --line: #dfe7f1;
            --text: #1f2a37;
            --muted: #65758b;
            --brand: #0f62fe;
            --good: #1f9d55;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .title h1 {
            margin: 0;
            font-size: 28px;
        }
        .title p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13px;
        }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            background: var(--brand);
            color: #fff;
        }
        .btn.secondary { background: #55657a; }
        .filter {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
        }
        .filter form {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
        }
        .field { display: grid; gap: 5px; }
        .field label { font-size: 12px; color: var(--muted); }
        .field input {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px 10px;
            min-width: 170px;
        }
        .field select {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 8px 10px;
            min-width: 170px;
            background: #fff;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
        }
        .kpi-label { font-size: 12px; color: var(--muted); }
        .kpi-value { font-size: 30px; font-weight: 700; margin-top: 6px; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        .panel-title {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 700;
        }
        .bar-row {
            display: grid;
            grid-template-columns: 170px 1fr 45px;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
        }
        .bar-label {
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bar-track {
            background: #edf2f8;
            border-radius: 999px;
            height: 10px;
            overflow: hidden;
        }
        .bar-fill {
            background: linear-gradient(90deg, #2f80ed, #56ccf2);
            height: 100%;
        }
        .bar-value {
            text-align: right;
            color: var(--muted);
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid var(--line);
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f7faff;
            font-weight: 700;
        }
        .note { color: var(--muted); font-size: 12px; }
        .pill {
            display: inline-block;
            background: #e9f7ef;
            color: var(--good);
            border-radius: 999px;
            padding: 3px 8px;
            margin-right: 5px;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .grid-2 { grid-template-columns: 1fr; }
            .bar-row { grid-template-columns: 130px 1fr 40px; }
        }
        @media print {
            @page { size: letter portrait; margin: 0.4in; }
            body { background: #fff; }
            .wrap { max-width: none; padding: 0; }
            .filter, .actions { display: none !important; }
            .card {
                box-shadow: none;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .kpi-value { font-size: 24px; }
            .title h1 { font-size: 20px; }
            .title p { font-size: 11px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="toolbar">
            <div class="title">
                <h1>CSM Analytics Report</h1>
                <p>Date filter:
                    <?= esc($dateFrom !== '' ? $dateFrom : 'All') ?>
                    to
                    <?= esc($dateTo !== '' ? $dateTo : 'All') ?>
                    | Department:
                    <?= esc($department !== '' ? $department : 'All') ?>
                    | Month:
                    <?= $month > 0 ? (int)$month : 'All' ?>
                    | Year:
                    <?= $year > 0 ? (int)$year : 'All' ?>
                </p>
            </div>
            <div class="actions">
                <button class="btn" onclick="window.print()">Print Report</button>
                <a class="btn secondary" href="admin.php">Back to Admin</a>
            </div>
        </div>

        <div class="filter">
            <form method="get">
                <div class="field">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?= esc($dateFrom) ?>">
                </div>
                <div class="field">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?= esc($dateTo) ?>">
                </div>
                <div class="field">
                    <label for="department">Department</label>
                    <select id="department" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departmentOptions as $opt): ?>
                            <option value="<?= esc($opt) ?>" <?= $department === $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
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
                <button class="btn" type="submit">Apply Filter</button>
                <a class="btn secondary" href="analytics_report.php">Reset</a>
            </form>
        </div>

        <div class="cards">
            <div class="card">
                <div class="kpi-label">Total Responses</div>
                <div class="kpi-value"><?= (int)$totalResponses ?></div>
            </div>
            <div class="card">
                <div class="kpi-label">Unique Offices</div>
                <div class="kpi-value"><?= (int)count($officeCounts) ?></div>
            </div>
            <div class="card">
                <div class="kpi-label">Unique Regions</div>
                <div class="kpi-value"><?= (int)count($regionCounts) ?></div>
            </div>
            <div class="card">
                <div class="kpi-label">Latest Response</div>
                <div class="kpi-value" style="font-size:18px;">
                    <?= $totalResponses > 0 ? esc((string)$rows[0]['created_at']) : 'N/A' ?>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2 class="panel-title">Top Offices</h2>
                <?php if (count($topOffices) === 0): ?>
                    <p class="note">No data available.</p>
                <?php else: ?>
                    <?php foreach ($topOffices as $label => $count): ?>
                        <?php $width = $maxOfficeCount > 0 ? ($count / $maxOfficeCount) * 100 : 0; ?>
                        <div class="bar-row">
                            <div class="bar-label" title="<?= esc((string)$label) ?>"><?= esc((string)$label) ?></div>
                            <div class="bar-track"><div class="bar-fill" style="width: <?= (float)$width ?>%"></div></div>
                            <div class="bar-value"><?= (int)$count ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="card">
                <h2 class="panel-title">Top Regions</h2>
                <?php if (count($topRegions) === 0): ?>
                    <p class="note">No data available.</p>
                <?php else: ?>
                    <?php foreach ($topRegions as $label => $count): ?>
                        <?php $width = $maxRegionCount > 0 ? ($count / $maxRegionCount) * 100 : 0; ?>
                        <div class="bar-row">
                            <div class="bar-label" title="<?= esc((string)$label) ?>"><?= esc((string)$label) ?></div>
                            <div class="bar-track"><div class="bar-fill" style="width: <?= (float)$width ?>%"></div></div>
                            <div class="bar-value"><?= (int)$count ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h2 class="panel-title">Client Type Counts</h2>
                <table>
                    <thead><tr><th>Type</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($clientTypeCounts as $label => $count): ?>
                            <tr><td><?= esc($label) ?></td><td><?= (int)$count ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h2 class="panel-title">Gender Counts</h2>
                <table>
                    <thead><tr><th>Gender</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($genderCounts as $label => $count): ?>
                            <tr><td><?= esc($label) ?></td><td><?= (int)$count ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <h2 class="panel-title">Average SQD Ratings</h2>
            <table>
                <thead>
                    <tr>
                        <th>SQD Item</th>
                        <th>Average Score (1-6)</th>
                        <th>Answered Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i <= 8; $i++): ?>
                        <tr>
                            <td>SQD<?= (int)$i ?></td>
                            <td><?= number_format((float)$sqdAverages[$i], 2) ?></td>
                            <td><?= (int)$sqdCounts[$i] ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <p class="note" style="margin-top:8px;">Scale: 1 (lowest) to 6 (N/A option included as value 6 in stored responses).</p>
        </div>

        <div class="card">
            <h2 class="panel-title">Recent Responses (Last 20)</h2>
            <?php if (count($recentRows) === 0): ?>
                <p class="note">No responses found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Office</th>
                            <th>Date</th>
                            <th>Region</th>
                            <th>Client Type</th>
                            <th>Saved At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRows as $r): ?>
                            <?php
                            $office = trim((string)$r['office_custom_name']) !== ''
                                ? (string)$r['office_custom_name']
                                : (trim((string)$r['office_selected']) !== '' ? (string)$r['office_selected'] : (string)$r['office_title']);
                            $ctype = json_decode((string)$r['client_type_json'], true);
                            if (!is_array($ctype)) { $ctype = []; }
                            $labels = [];
                            if (!empty($ctype['mamamayan'])) { $labels[] = 'Mamamayan'; }
                            if (!empty($ctype['negosyo'])) { $labels[] = 'Negosyo'; }
                            if (!empty($ctype['gobyerno'])) { $labels[] = 'Gobyerno'; }
                            ?>
                            <tr>
                                <td><?= (int)$r['id'] ?></td>
                                <td><?= esc($office) ?></td>
                                <td><?= esc((string)$r['visit_date']) ?></td>
                                <td><?= esc((string)$r['region_name']) ?></td>
                                <td>
                                    <?php if (count($labels) === 0): ?>
                                        <span class="note">-</span>
                                    <?php else: ?>
                                        <?php foreach ($labels as $l): ?>
                                            <span class="pill"><?= esc($l) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc((string)$r['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
