<?php
declare(strict_types=1);

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';
$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

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
            z-index: 20;
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
            max-width: 38vw;
        }
        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
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
            cursor: pointer;
        }
        .btn.secondary { background: #5f6c7b; }
        .btn.small { padding: 6px 10px; font-size: 11px; }
        .viewer-wrap {
            height: calc(100vh - 58px);
            overflow: auto;
            padding: 10px;
        }
        #pdf-canvas {
            display: block;
            width: min(100%, 1400px);
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
        }
        .status {
            text-align: center;
            color: #4b5563;
            font-size: 13px;
            margin: 8px 0;
        }
        .hidden { display: none !important; }
        .error {
            max-width: 760px;
            margin: 12px auto;
            background: #fff2f2;
            border: 1px solid #f7caca;
            color: #8d1f1f;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
        }
        <?php if ($embed): ?>
        .topbar {
            position: static;
            padding: 8px;
            border-bottom: 0;
            background: transparent;
            backdrop-filter: none;
        }
        .title, .file-actions { display: none !important; }
        .viewer-wrap { height: 100vh; padding: 0; }
        #pdf-canvas { width: 100%; box-shadow: none; }
        <?php endif; ?>
    </style>
</head>
<body>
    <header class="topbar">
        <div class="title"><?= esc($fileName) ?></div>
        <div class="actions">
            <button class="btn small" id="prevBtn" type="button">Prev</button>
            <span id="pageInfo" class="status" style="margin:0;">Page 0 / 0</span>
            <button class="btn small" id="nextBtn" type="button">Next</button>
            <button class="btn small" id="zoomOutBtn" type="button">-</button>
            <button class="btn small" id="zoomInBtn" type="button">+</button>
            <div class="file-actions" style="display:flex; gap:8px;">
                <a class="btn secondary" href="admin.php">Back</a>
                <a class="btn" href="<?= esc($encodedRelative) ?>" target="_blank" rel="noopener">Open PDF</a>
                <a class="btn" id="openGoogle" href="#" target="_blank" rel="noopener">Mobile View</a>
            </div>
        </div>
    </header>

    <main class="viewer-wrap">
        <div id="statusText" class="status">Loading PDF...</div>
        <div id="errorBox" class="error hidden"></div>
        <canvas id="pdf-canvas"></canvas>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        (function () {
            var filePath = "<?= esc($encodedRelative) ?>";
            var fullUrl = window.location.origin + "/" + filePath;
            var google = "https://docs.google.com/gview?embedded=1&url=" + encodeURIComponent(fullUrl);
            var googleBtn = document.getElementById("openGoogle");
            if (googleBtn) googleBtn.href = google;

            if (!window.pdfjsLib) {
                var e = document.getElementById('errorBox');
                e.textContent = 'PDF engine failed to load. Use Open PDF or Mobile View.';
                e.classList.remove('hidden');
                return;
            }

            pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

            var canvas = document.getElementById('pdf-canvas');
            var ctx = canvas.getContext('2d');
            var statusText = document.getElementById('statusText');
            var pageInfo = document.getElementById('pageInfo');
            var errorBox = document.getElementById('errorBox');
            var prevBtn = document.getElementById('prevBtn');
            var nextBtn = document.getElementById('nextBtn');
            var zoomInBtn = document.getElementById('zoomInBtn');
            var zoomOutBtn = document.getElementById('zoomOutBtn');

            var pdfDoc = null;
            var pageNum = 1;
            var scale = 1.35;
            var isRendering = false;

            function updateControls() {
                pageInfo.textContent = 'Page ' + pageNum + ' / ' + (pdfDoc ? pdfDoc.numPages : 0);
                prevBtn.disabled = pageNum <= 1;
                nextBtn.disabled = !pdfDoc || pageNum >= pdfDoc.numPages;
            }

            function renderPage(num) {
                if (!pdfDoc || isRendering) return;
                isRendering = true;
                statusText.textContent = 'Rendering page ' + num + '...';

                pdfDoc.getPage(num).then(function (page) {
                    var viewport = page.getViewport({ scale: scale });
                    var outputScale = window.devicePixelRatio || 1;
                    canvas.width = Math.floor(viewport.width * outputScale);
                    canvas.height = Math.floor(viewport.height * outputScale);
                    canvas.style.width = Math.floor(viewport.width) + 'px';
                    canvas.style.height = Math.floor(viewport.height) + 'px';

                    var renderContext = {
                        canvasContext: ctx,
                        viewport: viewport,
                        transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null
                    };

                    return page.render(renderContext).promise;
                }).then(function () {
                    statusText.textContent = '';
                    isRendering = false;
                    updateControls();
                }).catch(function (err) {
                    isRendering = false;
                    errorBox.textContent = 'Unable to render PDF. ' + (err && err.message ? err.message : '');
                    errorBox.classList.remove('hidden');
                });
            }

            prevBtn.addEventListener('click', function () {
                if (pageNum <= 1) return;
                pageNum -= 1;
                renderPage(pageNum);
            });

            nextBtn.addEventListener('click', function () {
                if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
                pageNum += 1;
                renderPage(pageNum);
            });

            zoomInBtn.addEventListener('click', function () {
                scale = Math.min(scale + 0.15, 2.5);
                renderPage(pageNum);
            });

            zoomOutBtn.addEventListener('click', function () {
                scale = Math.max(scale - 0.15, 0.7);
                renderPage(pageNum);
            });

            pdfjsLib.getDocument(filePath).promise.then(function (doc) {
                pdfDoc = doc;
                pageNum = 1;
                updateControls();
                renderPage(pageNum);
            }).catch(function (err) {
                errorBox.textContent = 'Unable to open PDF. ' + (err && err.message ? err.message : '');
                errorBox.classList.remove('hidden');
                statusText.textContent = '';
            });
        })();
    </script>
</body>
</html>
