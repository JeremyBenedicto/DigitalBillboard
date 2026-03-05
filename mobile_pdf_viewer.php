<?php
declare(strict_types=1);

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';
if ($file === '') {
    http_response_code(400);
    echo 'Missing file parameter.';
    exit;
}

$file = str_replace('\\', '/', $file);
if (str_contains($file, '..') || str_starts_with($file, '/')) {
    http_response_code(400);
    echo 'Invalid file path.';
    exit;
}

$baseDirReal = realpath(__DIR__);
$targetReal = realpath(__DIR__ . DIRECTORY_SEPARATOR . $file);

if ($baseDirReal === false || $targetReal === false || !is_file($targetReal)) {
    http_response_code(404);
    echo 'PDF file not found.';
    exit;
}

if (strtolower(pathinfo($targetReal, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(400);
    echo 'Only PDF files are supported.';
    exit;
}

if (!str_starts_with($targetReal, $baseDirReal)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$relative = str_replace('\\', '/', substr($targetReal, strlen($baseDirReal) + 1));
$encodedRelative = implode('/', array_map('rawurlencode', explode('/', $relative)));
$fileName = basename($relative);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PDF Viewer - <?= esc($fileName) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f3f6fb;
            color: #122033;
        }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            background: #ffffffee;
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #dce4ef;
        }
        .title {
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn {
            border: 0;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: #0f62fe;
        }
        .btn.secondary { background: #5f6c7b; }
        .frame-wrap {
            height: calc(100vh - 58px);
            background: #fff;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="title"><?= esc($fileName) ?></div>
        <div class="actions">
            <a class="btn secondary" href="admin.php">Back</a>
            <a class="btn" id="openDirect" href="<?= esc($encodedRelative) ?>" target="_blank" rel="noopener">Open PDF</a>
            <a class="btn" id="openGoogle" href="#" target="_blank" rel="noopener">Mobile View</a>
        </div>
    </header>
    <main class="frame-wrap">
        <iframe src="<?= esc($encodedRelative) ?>#toolbar=1&navpanes=0"></iframe>
    </main>
    <script>
        (function () {
            var origin = window.location.origin;
            var filePath = "<?= esc($encodedRelative) ?>";
            var fullUrl = origin + "/" + filePath;
            var google = "https://docs.google.com/gview?embedded=1&url=" + encodeURIComponent(fullUrl);
            var btn = document.getElementById("openGoogle");
            if (btn) {
                btn.href = google;
            }
        })();
    </script>
</body>
</html>
