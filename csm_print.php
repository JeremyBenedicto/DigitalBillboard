<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid response id.';
    exit;
}

$stmt = $conn->prepare('SELECT * FROM client_satisfaction_responses WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo 'Failed to prepare query.';
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo 'Response not found.';
    exit;
}

$payload = json_decode((string)$row['payload_json'], true);
if (!is_array($payload)) {
    $payload = [];
}

$officeSelected = (string)($payload['office']['selected'] ?? $row['office_selected'] ?? '');
$officeCustomName = (string)($payload['office']['custom_name'] ?? $row['office_custom_name'] ?? '');
$officeTitle = (string)($payload['office']['title'] ?? $row['office_title'] ?? '');
$visitDate = (string)($payload['respondent']['date'] ?? $row['visit_date'] ?? '');
$age = (string)($payload['respondent']['edad'] ?? $row['age'] ?? '');
$region = (string)($payload['respondent']['rehiyon'] ?? $row['region_name'] ?? '');
$transactionType = (string)($payload['transaction_type'] ?? $row['transaction_type'] ?? '');
$suggestions = (string)($payload['suggestions'] ?? $row['suggestions'] ?? '');
$email = (string)($payload['email'] ?? $row['email'] ?? '');

$clientType = $payload['client_type'] ?? json_decode((string)$row['client_type_json'], true);
if (!is_array($clientType)) {
    $clientType = [];
}
$gender = $payload['respondent']['kasarian'] ?? json_decode((string)$row['gender_json'], true);
if (!is_array($gender)) {
    $gender = [];
}
$cc1 = $payload['citizen_charter']['cc1'] ?? json_decode((string)$row['cc1_json'], true);
$cc2 = $payload['citizen_charter']['cc2'] ?? json_decode((string)$row['cc2_json'], true);
$cc3 = $payload['citizen_charter']['cc3'] ?? json_decode((string)$row['cc3_json'], true);
$sqd = $payload['sqd_ratings'] ?? json_decode((string)$row['sqd_json'], true);

if (!is_array($cc1)) { $cc1 = []; }
if (!is_array($cc2)) { $cc2 = []; }
if (!is_array($cc3)) { $cc3 = []; }
if (!is_array($sqd)) { $sqd = []; }

$questions = [
    "Nasiyahan ako sa serbisyo na aking natanggap sa napuntahang tanggapan.",
    "Makatwiran ang oras na aking ginugol para sa pagproseso ng aking transaksyon.",
    "Ang opisina ay sumusunod sa mga kinakailangang dokumento at mga hakbang batay sa impormasyong ibinigay.",
    "Ang mga hakbang sa pagproseso, kasama na ang pagbayad ay madali at simple lamang.",
    "Mabilis at madali akong nakahanap ng impormasyon tungkol sa aking transaksyon mula sa opisina o sa website nito.",
    "Nagbayad ako ng makatwirang halaga para sa aking transaksyon. (Kung libre, lagyan ng tsek ang N/A.)",
    "Pakiramdam ko ay patas ang opisina sa lahat, o 'walang palakasan', sa aking transaksyon.",
    "Magalang akong trinato ng mga tauhan, at (kung humingi ako ng tulong) alam ko na sila ay handang tumulong sa akin.",
    "Nakuha ko ang kinakailangan ko mula sa tanggapan ng gobyerno; kung tinanggihan man, ito ay sapat na ipinaliwanag sa akin."
];

function mark(bool $checked): string
{
    return $checked ? '&#10003;' : '&nbsp;';
}

function sqdMark(array $sqd, int $idx, int $col): string
{
    $key = 'sqd' . $idx;
    $value = (string)($sqd[$key] ?? '');
    return $value === (string)$col ? '&#10003;' : '&nbsp;';
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>CSM Response #<?= (int)$row['id'] ?></title>
  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: #f9f9f9;
      color: #000;
      margin: 0;
      padding: 0;
      font-size: 16px;
      line-height: 1.35;
    }
    .toolbar {
      max-width: 950px;
      margin: 14px auto 0;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }
    .tool-btn {
      border: none;
      border-radius: 6px;
      padding: 10px 14px;
      background: #007BFF;
      color: #fff;
      cursor: pointer;
      text-decoration: none;
      font-size: 14px;
    }
    .tool-btn.secondary { background: #666; }

    .container {
      max-width: 950px;
      background: #fff;
      margin: 16px auto;
      padding: 36px 52px;
      border: 1px solid #ccc;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    header {
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      margin-bottom: 30px;
      line-height: 1.4;
      position: relative;
    }

    header h2 {
      font-size: 20px;
      margin: 0;
      font-weight: bold;
    }

    header h3 {
      font-size: 18px;
      margin: 0;
    }

    .divider {
      border-bottom: 1px solid #000;
      margin: 15px 0;
    }

    h4.section-title {
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
      margin-top: 30px;
      margin-bottom: 15px;
      letter-spacing: 0.2px;
    }

    p.instructions {
      text-align: justify;
      font-size: 16px;
    }

    .form-section { margin-top: 20px; }
    label { font-weight: 600; }
    .form-group { margin-bottom: 12px; }
    .inline-group {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }

    .display-input {
      display: inline-block;
      border: 1px solid #ccc;
      padding: 8px 12px;
      min-width: 140px;
      border-radius: 4px;
      background: #fff;
      line-height: 1.2;
    }

    .check-box {
      display: inline-flex;
      width: 18px;
      height: 18px;
      border: 1px solid #000;
      align-items: center;
      justify-content: center;
      margin-right: 4px;
      font-size: 14px;
      vertical-align: middle;
    }

    .checkbox-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: normal;
    }

    table {
      border-collapse: collapse;
      width: 100%;
      margin-top: 15px;
      table-layout: fixed;
    }

    th, td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: center;
      font-size: 15px;
      vertical-align: middle;
    }

    th { background-color: #efefef; }
    .question { text-align: left; font-weight: normal; width: 38%; }
    th:not(.question), td:not(.question) { width: 10.333%; }
    .emoji {
      font-size: 28px;
      display: block;
      font-family: "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif;
      line-height: 1;
    }
    .red { color: #e74c3c; }
    .orange { color: #e67e22; }
    .gray { color: #7f8c8d; }
    .lightgreen { color: #2ecc71; }
    .green { color: #27ae60; }
    .na { color: #95a5a6; }

    .mark-cell {
      font-size: 20px;
      font-weight: 700;
      line-height: 1;
    }

    .footer { text-align: center; margin-top: 20px; font-weight: bold; }
    .page-break { page-break-before: always; margin-top: 50px; }
    .meta { font-size: 12px; color: #666; text-align: right; margin-top: -18px; }

    @media print {
      @page {
        size: letter portrait;
        margin: 0.4in;
      }
      .toolbar { display: none; }
      body {
        background: #fff;
        font-size: 13px;
        line-height: 1.35;
        margin: 0;
        padding: 0;
      }
      .container {
        box-shadow: none;
        border: none;
        margin: 0;
        width: 100%;
        min-height: auto;
        max-height: none;
        padding: 0;
        overflow: visible;
      }
      #page1, #page2 { margin: 0; }
      header { margin-bottom: 12px; line-height: 1.18; }
      header h2 { font-size: 19px; }
      header h3 { font-size: 14px; }
      h4.section-title { margin-top: 10px; margin-bottom: 8px; font-size: 14px; }
      p.instructions { font-size: 12px; margin: 7px 0; }
      .form-section { margin-top: 9px; }
      .form-group { margin-bottom: 7px; }
      label { font-size: 12px; }
      .display-input {
        font-size: 12px;
        padding: 5px 7px;
        min-width: 90px;
      }
      .check-box {
        width: 14px;
        height: 14px;
        font-size: 12px;
      }
      .meta { font-size: 11px; margin-top: 0; }
      .divider { margin: 9px 0; }
      table { margin-top: 8px; table-layout: fixed; }
      th, td {
        font-size: 12px;
        padding: 4px 5px;
      }
      .question { width: 38%; }
      th:not(.question), td:not(.question) { width: 10.333%; }
      .emoji { font-size: 20px; }
      .mark-cell { font-size: 18px; }
      .footer { margin-top: 12px; font-size: 13px; }
      #page1 { page-break-after: always; break-after: page; }
      #page2 { page-break-before: always; break-before: page; }
      .page-break { page-break-before: always; break-before: page; margin-top: 0; }
      table, tr, td, th { page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button class="tool-btn" onclick="window.print()">Print</button>
    <a class="tool-btn secondary" href="admin.php">Back</a>
  </div>

  <div class="container" id="page1">
    <header>
      <div style="width:100%">
        <h3>Republic of the Philippines</h3>
        <h3>PROVINCE OF NUEVA ECIJA</h3>
        <h3>Municipality of Gabaldon</h3>
        <p><b>- oOo -</b></p>
        <h2><?= e($officeTitle !== '' ? $officeTitle : ('PAMBAYANG TANGGAPAN NG ' . ($officeCustomName !== '' ? $officeCustomName : $officeSelected))) ?></h2>
      </div>
    </header>

    <div class="divider"></div>
    <h4>TULUNGAN MO KAMI MAS MAPABUTI ANG AMING MGA PROSESO AT SERBISYO!</h4>

    <p class="instructions">
      Ang Client Satisfaction Measurement (CSM) ay naglalayong masubaybayan ang karanasan ng taumbayan hinggil sa kanilang pakikitransaksyon sa mga tanggapan ng gobyerno.
      Makatutulong ang inyong kasagutan upang mas mapabuti at lalong mapahusay ang aming serbisyo publiko.
      Ang inyong ibinahaging impormasyon ay mananatiling kumpidensyal. Maaari ring piliin na hindi sagutan ang sarbey na ito.
    </p>

    <div class="form-section">
      <div class="form-group inline-group">
        <label>Uri ng Kliyente:</label>
        <label><span class="check-box"><?= mark(!empty($clientType['mamamayan'])) ?></span> Mamamayan</label>
        <label><span class="check-box"><?= mark(!empty($clientType['negosyo'])) ?></span> Negosyo</label>
        <label><span class="check-box"><?= mark(!empty($clientType['gobyerno'])) ?></span> Gobyerno</label>
      </div>

      <div class="form-group inline-group">
        <label>Petsa:</label> <span class="display-input"><?= e($visitDate) ?></span>
        <label>Kasarian:</label>
        <label><span class="check-box"><?= mark(!empty($gender['lalaki'])) ?></span> Lalaki</label>
        <label><span class="check-box"><?= mark(!empty($gender['babae'])) ?></span> Babae</label>
        <label>Edad:</label> <span class="display-input" style="min-width:80px"><?= e($age) ?></span>
      </div>

      <div class="form-group">
        <label>Rehiyon:</label>
        <span class="display-input" style="display:block; margin-top: 5px; min-height: 40px;"><?= e($region) ?></span>
      </div>

      <div class="form-group">
        <label>Uri ng transaksyon o serbisyo:</label>
        <span class="display-input" style="display:block; margin-top: 5px; min-height: 40px;"><?= e($transactionType) ?></span>
      </div>
    </div>

    <h4 class="section-title">Citizen's Charter (CC)</h4>
    <p class="instructions">Lagyan ng tsek (?) ang angkop na sagot.</p>

    <div class="form-section">
      <label>CC1. Alin sa mga sumusunod ang naglalarawan sa iyong kaalaman sa CC?</label>
      <div class="checkbox-group">
        <label><span class="check-box"><?= mark(!empty($cc1[0])) ?></span> 1. Alam ko ang CC at nakita ko ito sa napuntahang opisina</label>
        <label><span class="check-box"><?= mark(!empty($cc1[1])) ?></span> 2. Alam ko ang CC pero hindi ko ito nakita sa napuntahang opisina</label>
        <label><span class="check-box"><?= mark(!empty($cc1[2])) ?></span> 3. Nalaman ko ang CC nang makita ko ito sa napuntahang opisina</label>
        <label><span class="check-box"><?= mark(!empty($cc1[3])) ?></span> 4. Hindi ko alam kung ano ang CC at wala akong nakita sa opisina</label>
      </div>

      <label>CC2. Kung alam ang CC, masasabi mo ba na ang CC ng opisina ay...</label>
      <div class="checkbox-group">
        <label><span class="check-box"><?= mark(!empty($cc2[0])) ?></span> 1. Madaling makita</label>
        <label><span class="check-box"><?= mark(!empty($cc2[1])) ?></span> 2. Medyo madaling makita</label>
        <label><span class="check-box"><?= mark(!empty($cc2[2])) ?></span> 3. Mahirap makita</label>
        <label><span class="check-box"><?= mark(!empty($cc2[3])) ?></span> 4. Hindi makita</label>
        <label><span class="check-box"><?= mark(!empty($cc2[4])) ?></span> 5. N/A</label>
      </div>

      <label>CC3. Kung alam ang CC, gaano nakatulong ang CC sa transaksyon mo?</label>
      <div class="checkbox-group">
        <label><span class="check-box"><?= mark(!empty($cc3[0])) ?></span> 1. Sobrang nakatulong</label>
        <label><span class="check-box"><?= mark(!empty($cc3[1])) ?></span> 2. Nakatulong naman</label>
        <label><span class="check-box"><?= mark(!empty($cc3[2])) ?></span> 3. Hindi nakatulong</label>
        <label><span class="check-box"><?= mark(!empty($cc3[3])) ?></span> 4. N/ A</label>
      </div>
    </div>
  </div>

  <div class="container page-break" id="page2">
    <h4 class="section-title">Satisfaction Questions (SQD 0-8)</h4>
    <p class="instructions">Pumili ng isang emoji na pinakaangkop sa iyong sagot.</p>

    <table>
      <thead>
        <tr>
          <th class="question">Mga Pahayag</th>
          <th><span class="emoji red">&#128544;</span>Lubos na<br>hindi sumasang-ayon</th>
          <th><span class="emoji orange">&#128577;</span>Hindi<br>sumasang-ayon</th>
          <th><span class="emoji gray">&#128528;</span>Walang<br>kinikilingan</th>
          <th><span class="emoji lightgreen">&#128578;</span>Sumasang-ayon</th>
          <th><span class="emoji green">&#128515;</span>Labis na<br>sumasang-ayon</th>
          <th><span class="emoji na">&#128683;</span>N/A</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($questions as $idx => $question): ?>
          <tr>
            <td class="question">SQD<?= (int)$idx ?>. <?= e($question) ?></td>
            <td class="mark-cell"><?= sqdMark($sqd, $idx, 1) ?></td>
            <td class="mark-cell"><?= sqdMark($sqd, $idx, 2) ?></td>
            <td class="mark-cell"><?= sqdMark($sqd, $idx, 3) ?></td>
            <td class="mark-cell"><?= sqdMark($sqd, $idx, 4) ?></td>
            <td class="mark-cell"><?= sqdMark($sqd, $idx, 5) ?></td>
            <td class="mark-cell"><?= sqdMark($sqd, $idx, 6) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="form-section">
      <label>Mga suhestiyon (opsyonal):</label>
      <span class="display-input" style="display:block; min-height: 80px;"><?= e($suggestions) ?></span>
      <label style="margin-top:8px; display:block;">Email address (opsyonal):</label>
      <span class="display-input" style="display:block;"><?= e($email) ?></span>
    </div>

    <div class="footer">MARAMING SALAMAT!</div>
  </div>
</body>
</html>
