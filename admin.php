<?php
declare(strict_types=1);

$messages = [];
$errors = [];

require_once __DIR__ . '/config/config.php';

if (!isset($conn) || !$conn) {
    // If DB does not exist yet, bootstrap it using the same config credentials.
    if (!isset($db_host, $db_username, $db_password, $db_name)) {
        $errors[] = 'Database configuration is incomplete.';
    } else {
        $bootstrapConn = @new mysqli($db_host, $db_username, $db_password);
        if ($bootstrapConn->connect_error) {
            $errors[] = 'Database connection failed: ' . $bootstrapConn->connect_error;
        } else {
            $bootstrapConn->set_charset('utf8mb4');
            if (!$bootstrapConn->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                $errors[] = 'Failed to initialize database: ' . $bootstrapConn->error;
            } else {
                $bootstrapConn->close();
                $conn = @new mysqli($db_host, $db_username, $db_password, $db_name);
            }
        }
    }
}

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    $createTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS slideshow_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;

    if (!$conn->query($createTableSql)) {
        $errors[] = 'Failed to prepare slideshow table: ' . $conn->error;
    }
} else {
    if (empty($errors)) {
        $errors[] = 'Database connection failed.';
    }
}

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'slideshow';
$uploadWebPath = 'uploads/slideshow';

if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $errors[] = 'Failed to create upload directory: ' . $uploadDir;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && $conn instanceof mysqli) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
            $errors[] = 'No image files received.';
        } else {
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];

            $successCount = 0;
            $failedCount = 0;

            $names = $_FILES['images']['name'];
            $tmpNames = $_FILES['images']['tmp_name'];
            $sizes = $_FILES['images']['size'];
            $errorsUpload = $_FILES['images']['error'];

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            for ($i = 0; $i < count($names); $i++) {
                if (!isset($errorsUpload[$i]) || $errorsUpload[$i] !== UPLOAD_ERR_OK) {
                    $failedCount++;
                    continue;
                }

                if (!isset($sizes[$i]) || (int)$sizes[$i] > 10 * 1024 * 1024) {
                    $failedCount++;
                    continue;
                }

                $tmpFile = $tmpNames[$i] ?? '';
                $detectedMime = $finfo->file($tmpFile);
                if (!isset($allowedMimes[$detectedMime])) {
                    $failedCount++;
                    continue;
                }

                $ext = $allowedMimes[$detectedMime];
                $originalName = basename((string)$names[$i]);
                $targetFileName = 'slide_' . bin2hex(random_bytes(10)) . '.' . $ext;
                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetFileName;
                $relativePath = $uploadWebPath . '/' . $targetFileName;

                if (!move_uploaded_file($tmpFile, $targetPath)) {
                    $failedCount++;
                    continue;
                }

                $stmt = $conn->prepare('INSERT INTO slideshow_images (file_name, file_path) VALUES (?, ?)');
                if (!$stmt) {
                    @unlink($targetPath);
                    $failedCount++;
                    continue;
                }

                $stmt->bind_param('ss', $originalName, $relativePath);
                if ($stmt->execute()) {
                    $successCount++;
                } else {
                    @unlink($targetPath);
                    $failedCount++;
                }
                $stmt->close();
            }

            if ($successCount > 0) {
                $messages[] = $successCount . ' image(s) uploaded successfully.';
            }
            if ($failedCount > 0) {
                $errors[] = $failedCount . ' image(s) failed to upload. Check file type/size (max 10MB).';
            }
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $stmt = $conn->prepare('SELECT file_path FROM slideshow_images WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if ($row && isset($row['file_path'])) {
                    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string)$row['file_path']);

                    $deleteStmt = $conn->prepare('DELETE FROM slideshow_images WHERE id = ?');
                    if ($deleteStmt) {
                        $deleteStmt->bind_param('i', $id);
                        if ($deleteStmt->execute()) {
                            if (is_file($absolutePath)) {
                                @unlink($absolutePath);
                            }
                            $messages[] = 'Image deleted.';
                        } else {
                            $errors[] = 'Failed to delete record.';
                        }
                        $deleteStmt->close();
                    }
                } else {
                    $errors[] = 'Image not found.';
                }
            }
        }
    } elseif ($action === 'delete_csm') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $deleteCsmStmt = $conn->prepare('DELETE FROM client_satisfaction_responses WHERE id = ?');
            if ($deleteCsmStmt) {
                $deleteCsmStmt->bind_param('i', $id);
                if ($deleteCsmStmt->execute()) {
                    if ($deleteCsmStmt->affected_rows > 0) {
                        $messages[] = 'CSM response deleted.';
                    } else {
                        $errors[] = 'CSM response not found.';
                    }
                } else {
                    $errors[] = 'Failed to delete CSM response.';
                }
                $deleteCsmStmt->close();
            } else {
                $errors[] = 'Failed to prepare CSM delete query.';
            }
        }
    }
}

$images = [];
if (empty($errors) && $conn instanceof mysqli) {
    $res = $conn->query('SELECT id, file_name, file_path, created_at FROM slideshow_images ORDER BY id DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $images[] = $row;
        }
        $res->free();
    }
}

$csmResponses = [];
$csmLoadError = '';
$csmMonthFilter = isset($_GET['csm_month']) ? (int)$_GET['csm_month'] : 0;
$csmYearFilter = isset($_GET['csm_year']) ? (int)$_GET['csm_year'] : 0;
$csmYearOptions = [];
if ($conn instanceof mysqli) {
    $csmTableExistsRes = $conn->query("SHOW TABLES LIKE 'client_satisfaction_responses'");
    $csmTableExists = $csmTableExistsRes && $csmTableExistsRes->num_rows > 0;
    if ($csmTableExistsRes) {
        $csmTableExistsRes->free();
    }

    if ($csmTableExists) {
        $yearListRes = $conn->query(
            'SELECT DISTINCT YEAR(DATE(COALESCE(visit_date, created_at))) AS yr
             FROM client_satisfaction_responses
             ORDER BY yr DESC'
        );
        if ($yearListRes) {
            while ($yrRow = $yearListRes->fetch_assoc()) {
                $yr = isset($yrRow['yr']) ? (int)$yrRow['yr'] : 0;
                if ($yr > 0) {
                    $csmYearOptions[] = $yr;
                }
            }
            $yearListRes->free();
        }

        $csmWhere = [];
        $csmParams = [];
        $csmTypes = '';

        if ($csmMonthFilter >= 1 && $csmMonthFilter <= 12) {
            $csmWhere[] = 'MONTH(DATE(COALESCE(visit_date, created_at))) = ?';
            $csmParams[] = $csmMonthFilter;
            $csmTypes .= 'i';
        } else {
            $csmMonthFilter = 0;
        }

        if ($csmYearFilter >= 1900 && $csmYearFilter <= 9999) {
            $csmWhere[] = 'YEAR(DATE(COALESCE(visit_date, created_at))) = ?';
            $csmParams[] = $csmYearFilter;
            $csmTypes .= 'i';
        } else {
            $csmYearFilter = 0;
        }

        $csmSql = 'SELECT id, office_selected, office_custom_name, office_title, visit_date, age, region_name, email, suggestions, client_type_json, sqd_json, created_at
                   FROM client_satisfaction_responses';
        if (count($csmWhere) > 0) {
            $csmSql .= ' WHERE ' . implode(' AND ', $csmWhere);
        }
        $csmSql .= ' ORDER BY id DESC LIMIT 300';

        $csmStmt = $conn->prepare($csmSql);
        if ($csmStmt) {
            if (count($csmParams) > 0) {
                $csmStmt->bind_param($csmTypes, ...$csmParams);
            }
            $csmStmt->execute();
            $csmRes = $csmStmt->get_result();
            if ($csmRes) {
                while ($row = $csmRes->fetch_assoc()) {
                    $csmResponses[] = $row;
                }
                $csmRes->free();
            }
            $csmStmt->close();
        } else {
            $csmLoadError = 'Failed to load CSM responses.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Slideshow Management</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #eef1f6 100%);
            color: #1d1d1f;
            min-height: 100vh;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .header h1 { font-size: 24px; }
        .back-btn {
            text-decoration: none;
            background: #007aff;
            color: #fff;
            padding: 10px 16px;
            border-radius: 999px;
            font-weight: 600;
        }
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px;
            display: grid;
            gap: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        h2 { margin-bottom: 14px; font-size: 20px; }
        .notice {
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .notice.ok { background: #e7f7ec; color: #0a6e2f; }
        .notice.err { background: #fdebec; color: #a0182f; }
        .upload-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        input[type="file"] {
            flex: 1;
            min-width: 280px;
            border: 2px dashed #c8d3e0;
            border-radius: 10px;
            padding: 10px;
            background: #f8fbff;
        }
        .btn {
            border: 0;
            border-radius: 999px;
            padding: 10px 18px;
            font-weight: 600;
            cursor: pointer;
            background: #007aff;
            color: #fff;
        }
        .btn-delete {
            background: #d8344a;
            padding: 8px 12px;
            font-size: 13px;
        }
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }
        .item {
            border: 1px solid #e7e7e7;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .item img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            display: block;
            background: #f0f0f0;
        }
        .item-body {
            padding: 10px;
            display: grid;
            gap: 8px;
        }
        .meta {
            font-size: 12px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .empty { color: #666; font-size: 14px; }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e7e7e7;
            border-radius: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
            font-size: 13px;
        }
        th, td {
            border-bottom: 1px solid #efefef;
            text-align: left;
            padding: 10px;
            vertical-align: top;
        }
        th {
            background: #f8fbff;
            font-weight: 700;
            color: #2d3c4d;
            position: sticky;
            top: 0;
        }
        .cell-small {
            color: #666;
            font-size: 12px;
            max-width: 220px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            background: #eef5ff;
            color: #2e5ea7;
            font-size: 11px;
            font-weight: 600;
            margin: 0 4px 4px 0;
        }
        .bulk-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .bulk-note {
            color: #666;
            font-size: 12px;
        }
        .csm-filter {
            display: flex;
            gap: 8px;
            align-items: end;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .csm-filter label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        .csm-filter select {
            border: 1px solid #d8dfe8;
            border-radius: 8px;
            padding: 8px 10px;
            min-width: 140px;
            background: #fff;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>Slideshow Admin</h1>
        <div style="display:flex; gap:8px;">
            <a class="back-btn" href="analytics_report.php">Analytics Report</a>
            <a class="back-btn" href="index.php">Back to Home</a>
        </div>
    </header>

    <main class="container">
        <section class="card">
            <h2>Upload Slideshow Images</h2>
            <?php foreach ($messages as $m): ?>
                <div class="notice ok"><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $e): ?>
                <div class="notice err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endforeach; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <div class="upload-row">
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                    <button class="btn" type="submit">Upload</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Current Images</h2>
            <?php if (count($images) === 0): ?>
                <p class="empty">No images uploaded yet.</p>
            <?php else: ?>
                <div class="gallery">
                    <?php foreach ($images as $image): ?>
                        <article class="item">
                            <img src="<?= htmlspecialchars((string)$image['file_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Slideshow Image">
                            <div class="item-body">
                                <div class="meta"><?= htmlspecialchars((string)$image['file_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="meta"><?= htmlspecialchars((string)$image['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
                                <form method="post" onsubmit="return confirm('Delete this image?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$image['id'] ?>">
                                    <button class="btn btn-delete" type="submit">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Saved CSM Responses</h2>
            <?php if ($csmLoadError !== ''): ?>
                <div class="notice err"><?= htmlspecialchars($csmLoadError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (count($csmResponses) === 0): ?>
                <p class="empty">No CSM responses saved yet.</p>
            <?php else: ?>
                <form method="get" class="csm-filter">
                    <div>
                        <label for="csm_month">Month</label>
                        <select id="csm_month" name="csm_month">
                            <option value="">All Months</option>
                            <option value="1" <?= $csmMonthFilter === 1 ? 'selected' : '' ?>>January</option>
                            <option value="2" <?= $csmMonthFilter === 2 ? 'selected' : '' ?>>February</option>
                            <option value="3" <?= $csmMonthFilter === 3 ? 'selected' : '' ?>>March</option>
                            <option value="4" <?= $csmMonthFilter === 4 ? 'selected' : '' ?>>April</option>
                            <option value="5" <?= $csmMonthFilter === 5 ? 'selected' : '' ?>>May</option>
                            <option value="6" <?= $csmMonthFilter === 6 ? 'selected' : '' ?>>June</option>
                            <option value="7" <?= $csmMonthFilter === 7 ? 'selected' : '' ?>>July</option>
                            <option value="8" <?= $csmMonthFilter === 8 ? 'selected' : '' ?>>August</option>
                            <option value="9" <?= $csmMonthFilter === 9 ? 'selected' : '' ?>>September</option>
                            <option value="10" <?= $csmMonthFilter === 10 ? 'selected' : '' ?>>October</option>
                            <option value="11" <?= $csmMonthFilter === 11 ? 'selected' : '' ?>>November</option>
                            <option value="12" <?= $csmMonthFilter === 12 ? 'selected' : '' ?>>December</option>
                        </select>
                    </div>
                    <div>
                        <label for="csm_year">Year</label>
                        <select id="csm_year" name="csm_year">
                            <option value="">All Years</option>
                            <?php foreach ($csmYearOptions as $yr): ?>
                                <option value="<?= (int)$yr ?>" <?= $csmYearFilter === (int)$yr ? 'selected' : '' ?>><?= (int)$yr ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn" type="submit">Apply</button>
                    <a class="btn btn-delete" href="admin.php">Reset</a>
                </form>
                <form method="get" action="csm_print_bulk.php" target="_blank" onsubmit="return validateBulkPrintSelection();">
                    <div class="bulk-actions">
                        <button class="btn" type="submit">Print Selected</button>
                        <button class="btn btn-delete" type="button" onclick="toggleAllCsmSelection(true)">Select All</button>
                        <button class="btn btn-delete" type="button" onclick="toggleAllCsmSelection(false)">Clear</button>
                        <span class="bulk-note">Select multiple responses then click Print Selected.</span>
                    </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-csm" onclick="toggleAllCsmSelection(this.checked)"></th>
                                <th>ID</th>
                                <th>Office</th>
                                <th>Date</th>
                                <th>Client Type</th>
                                <th>Age</th>
                                <th>Region</th>
                                <th>Email</th>
                                <th>SQD</th>
                                <th>Suggestions</th>
                                <th>Saved At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($csmResponses as $row): ?>
                                <?php
                                $clientTypes = json_decode((string)$row['client_type_json'], true);
                                if (!is_array($clientTypes)) {
                                    $clientTypes = [];
                                }
                                $typeLabels = [];
                                if (!empty($clientTypes['mamamayan'])) { $typeLabels[] = 'Mamamayan'; }
                                if (!empty($clientTypes['negosyo'])) { $typeLabels[] = 'Negosyo'; }
                                if (!empty($clientTypes['gobyerno'])) { $typeLabels[] = 'Gobyerno'; }

                                $sqd = json_decode((string)$row['sqd_json'], true);
                                if (!is_array($sqd)) {
                                    $sqd = [];
                                }
                                $sqdParts = [];
                                foreach ($sqd as $k => $v) {
                                    if ($v !== null && $v !== '') {
                                        $sqdParts[] = $k . ':' . $v;
                                    }
                                }
                                $officeDisplay = trim((string)$row['office_custom_name']) !== ''
                                    ? (string)$row['office_custom_name']
                                    : ((string)$row['office_selected'] !== '' ? (string)$row['office_selected'] : (string)$row['office_title']);
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="csm-select" name="ids[]" value="<?= (int)$row['id'] ?>"></td>
                                    <td><?= (int)$row['id'] ?></td>
                                    <td><?= htmlspecialchars($officeDisplay, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$row['visit_date'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (count($typeLabels) === 0): ?>
                                            <span class="cell-small">-</span>
                                        <?php else: ?>
                                            <?php foreach ($typeLabels as $label): ?>
                                                <span class="badge"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)$row['age'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$row['region_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="cell-small"><?= htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="cell-small"><?= htmlspecialchars(implode(', ', $sqdParts), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="cell-small"><?= htmlspecialchars((string)$row['suggestions'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <a class="btn" href="csm_print.php?id=<?= (int)$row['id'] ?>" target="_blank" rel="noopener">Print</a>
                                            <form method="post" onsubmit="return confirm('Delete this CSM response?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_csm">
                                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                                <button class="btn btn-delete" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script>
        function toggleAllCsmSelection(checked) {
            var items = document.querySelectorAll('.csm-select');
            for (var i = 0; i < items.length; i++) {
                items[i].checked = !!checked;
            }
            var selectAll = document.getElementById('select-all-csm');
            if (selectAll) {
                selectAll.checked = !!checked;
            }
        }

        function validateBulkPrintSelection() {
            var selected = document.querySelectorAll('.csm-select:checked');
            if (selected.length === 0) {
                alert('Please select at least one response to print.');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
