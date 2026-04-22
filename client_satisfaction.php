<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/csm_catalog.php';

$csmOffices = csm_default_offices();
$csmServicesByOffice = [];
$defaultOfficeCode = 'TAGATALA';
$defaultOfficeTitle = 'PAMBAYANG TANGGAPAN NG TAGATALA';

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    if (csm_initialize_catalog($conn)) {
        $dbOffices = csm_fetch_offices($conn);
        if (count($dbOffices) > 0) {
            $csmOffices = $dbOffices;
        }
        $csmServicesByOffice = csm_fetch_services_grouped($conn);
    }
}

if (count($csmOffices) > 0) {
    $defaultOfficeCode = (string)($csmOffices[0]['code'] ?? $defaultOfficeCode);
    $defaultOfficeTitle = (string)($csmOffices[0]['title'] ?? $defaultOfficeTitle);
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Client Satisfaction Measurement (CSM) Form</title>

  <style>
    :root {
      --page-padding: 40px 60px;
      --mobile-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: "Arial", sans-serif;
      background: #f9f9f9;
      color: #000;
      margin: 0;
      padding: 16px;
      font-size: 16px;
    }

    .container {
      max-width: 950px;
      background: #fff;
      margin: 24px auto;
      padding: var(--page-padding);
      border: 1px solid #ccc;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      border-radius: 18px;
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

    header img {
      position: absolute;
      left: 20px;
      top: 5px;
      width: 70px;
      height: 70px;
      object-fit: contain;
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
    }

    p.instructions {
      text-align: justify;
      font-size: 16px;
    }

    .form-section {
      margin-top: 20px;
    }

    label {
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 12px;
    }

    .inline-group {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }

    input[type="text"],
    input[type="date"],
    input[type="number"],
    textarea,
    select {
      border: 1px solid #ccc;
      padding: 8px 12px;
      width: 100%;
      border-radius: 4px;
      box-sizing: border-box;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }

    #office-selector-container {
      max-width: 500px;
      margin: 10px auto;
    }

    #office-selector-container label {
      display: block;
      margin-bottom: 5px;
      font-weight: normal;
      text-align: left;
    }

    #otherOfficeName {
      margin-top: 5px;
    }

    #visitDate {
      width: min(220px, 100%);
    }

    #ageInput {
      width: 100px !important;
      min-width: 100px;
    }

    textarea {
      min-height: 80px;
      resize: vertical;
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
    }

    .table-responsive {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      border: 1px solid #d9d9d9;
      border-radius: 14px;
      background: #fff;
      box-shadow: inset 0 0 0 1px rgba(0,0,0,0.01);
    }

    th, td {
      border: 1px solid #ccc;
      padding: 8px;
      text-align: center;
      font-size: 15px;
      vertical-align: middle;
    }

    /* NEW: Make the entire table cell clickable for ratings */
    td.rating-cell {
        cursor: pointer;
    }

    th {
      background-color: #efefef;
    }

    .question {
      text-align: left;
      font-weight: normal;
      min-width: 280px;
    }

    .mobile-option-label {
      display: none;
    }

    .emoji {
      font-size: 28px;
      display: block;
    }

    .red { color: #e74c3c; }
    .orange { color: #e67e22; }
    .gray { color: #7f8c8d; }
    .lightgreen { color: #2ecc71; }
    .green { color: #27ae60; }
    .na { color: #95a5a6; }

    .grayscale {
      filter: grayscale(100%);
    }

    /* NEW: Style for the selected rating cell */
    .selected-rating {
      background-color: #d4edda !important; /* A light green to indicate selection */
      filter: grayscale(100%);
    }

    .submit-btn {
      display: block;
      margin: 30px auto 10px;
      padding: 10px 30px;
      background-color: #007BFF;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
    }

    .submit-btn:hover {
      background-color: #0056b3;
    }

    .submit-btn:disabled {
      background-color: #cccccc;
      cursor: not-allowed;
    }

    .footer {
      text-align: center;
      margin-top: 20px;
      font-weight: bold;
    }

    .page-break {
      page-break-before: always;
      margin-top: 50px;
    }

    #overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      color: white;
      font-size: 22px;
      text-align: center;
      z-index: 9999;
      align-items: center;
      justify-content: center;
      animation: fadeIn 0.3s ease-in-out;
    }

    #overlay p {
      background: rgba(0,0,0,0.8);
      padding: 20px 40px;
      border-radius: 10px;
    }

    /* Success Modal Card */
    #successModal {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.8);
      background: white;
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      z-index: 10000;
      width: 90%;
      max-width: 420px;
      padding: 40px 30px;
      text-align: center;
      animation: popIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
    }

    #successModal.hide {
      animation: popOut 0.4s ease-in-out forwards;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }

    @keyframes fadeOut {
      from {
        opacity: 1;
      }
      to {
        opacity: 0;
      }
    }

    @keyframes popIn {
      0% {
        transform: translate(-50%, -50%) scale(0.6);
        opacity: 0;
      }
      70% {
        transform: translate(-50%, -50%) scale(1.05);
        opacity: 1;
      }
      100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
      }
    }

    @keyframes popOut {
      0% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
      }
      100% {
        transform: translate(-50%, -50%) scale(0.6);
        opacity: 0;
      }
    }

    .success-icon {
      width: 70px;
      height: 70px;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, #27ae60, #2ecc71);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      animation: scaleIn 0.6s ease-out 0.2s both;
    }

    .success-icon::after {
      content: "✓";
      color: white;
      font-size: 40px;
      font-weight: bold;
    }

    @keyframes scaleIn {
      from {
        transform: scale(0);
      }
      to {
        transform: scale(1);
      }
    }

    #successModal h3 {
      color: #333;
      font-size: 24px;
      margin: 0 0 15px;
      font-weight: bold;
      animation: slideUp 0.5s ease-out 0.3s both;
    }

    #successModal p {
      color: #666;
      font-size: 16px;
      line-height: 1.6;
      margin: 0;
      animation: slideUp 0.5s ease-out 0.4s both;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    #successBackdrop {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 9998;
      animation: fadeIn 0.3s ease-in-out;
    }

    #successBackdrop.hide {
      animation: fadeOut 0.3s ease-in-out forwards;
    }

    @media (max-width: 640px) {
      #successModal {
        width: 85%;
        padding: 30px 20px;
        max-width: 100%;
      }

      #successModal h3 {
        font-size: 20px;
      }

      #successModal p {
        font-size: 14px;
      }

      .success-icon {
        width: 60px;
        height: 60px;
      }

      .success-icon::after {
        font-size: 32px;
      }
    }
    /* Back button */
    .back-button {
      position: fixed;
      top: 15px;
      left: 15px;
      z-index: 1000;
      padding: 10px 15px;
      background-color: #dcdada;
      color: #333;
      border: 1px solid #ccc;
      border-radius: 20px;
      text-decoration: none;
      font-weight: bold;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      transition: background-color 0.2s, transform 0.1s;
      font-size: 14px;
    }

    .back-button:hover { background-color: #e0e0e0; transform: translateY(-1px); }
    .back-button:active { transform: translateY(0); }

    @media print {
      body {
        padding: 0;
      }

      .container {
        border-radius: 0;
      }

      #office-selector-container {
        display: none !important;
      }
    }

    @media (max-width: 900px) {
      body {
        padding: 12px;
      }

      .container {
        padding: 32px 28px;
      }

      .page-break {
        margin-top: 24px;
      }
    }

    @media (max-width: 640px) {
      body {
        padding: 10px;
        font-size: 15px;
      }

      .container {
        margin: 14px auto;
        padding: 22px 16px;
        border-radius: 16px;
        box-shadow: var(--mobile-shadow);
      }

      header {
        margin-bottom: 22px;
      }

      header h2 {
        font-size: 18px;
        line-height: 1.35;
      }

      header h3 {
        font-size: 15px;
      }

      h4.section-title,
      h4:not(.section-title) {
        font-size: 17px;
        line-height: 1.35;
      }

      p.instructions {
        font-size: 14px;
        line-height: 1.6;
      }

      .inline-group {
        align-items: stretch;
        gap: 12px;
      }

      .inline-group > label {
        width: 100%;
      }

      #visitDate,
      #ageInput {
        width: 100% !important;
        min-width: 0;
      }

      input[type="text"],
      input[type="date"],
      input[type="number"],
      textarea,
      select {
        min-height: 46px;
        font-size: 16px;
      }

      .checkbox-group label {
        line-height: 1.5;
      }

      .table-responsive {
        margin-top: 16px;
        border: 0;
        box-shadow: none;
        background: transparent;
      }

      table {
        min-width: 0;
        margin-top: 0;
      }

      th, td {
        padding: 10px 8px;
        font-size: 13px;
      }

      .question {
        grid-column: 1 / -1;
        align-items: flex-start !important;
        min-width: 0;
        min-height: auto !important;
        padding: 0 0 8px;
        border: 0 !important;
        background: transparent !important;
        font-size: 15px;
        line-height: 1.5;
      }

      .emoji {
        font-size: 22px;
      }

      #emojiTable thead {
        display: none;
      }

      #emojiTable,
      #emojiTable tbody {
        display: block;
      }

      #emojiTable tr {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
        padding: 14px;
        border: 1px solid #d8d8d8;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(0,0,0,0.06);
      }

      #emojiTable td {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 8px;
        min-height: 82px;
        padding: 12px 10px;
        border: 1px solid #e2e2e2;
        border-radius: 14px;
        background: #fafafa;
      }

      .submit-btn {
        width: 100%;
        max-width: none;
        padding: 14px 20px;
      }

      .back-button {
        position: sticky;
        top: 10px;
        left: auto;
        display: inline-flex;
        margin-bottom: 8px;
      }

      #overlay {
        padding: 20px;
      }

      #overlay p {
        width: 100%;
        max-width: 320px;
        padding: 18px 20px;
        font-size: 18px;
      }

      .mobile-option-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.25;
        color: #222;
      }

      .mobile-option-label .emoji {
        font-size: 24px;
      }

      #emojiTable td input[type="radio"] {
        width: 20px;
        height: 20px;
        margin: 0;
      }

      #emojiTable td.selected-rating {
        border-color: #73c48f;
        background: #eefaf1 !important;
      }
    }
  </style>
</head>
<body>
  <!-- BACK BUTTON -->
  <a href="javascript:void(0);" class="back-button" onclick="window.history.back();">&larr;</a>

  <!-- Overlay -->
  <div id="overlay"><p>Loading...</p></div>

  <!-- Success Modal -->
  <div id="successBackdrop"></div>
  <div id="successModal">
    <div class="success-icon"></div>
    <h3>Salamat!</h3>
    <p>Matagumpay na natanggap ang inyong feedback. Malaking tulong ito sa aming patuloy na pagpapabuti ng serbisyo.</p>
  </div>

  <!-- PAGE 1 -->
  <div class="container" id="page1">
    <header>
      <div style="width:100%">
        <h3>Republic of the Philippines</h3>
        <h3>PROVINCE OF NUEVA ECIJA</h3>
        <h3>Municipality of Gabaldon</h3>
        <p><b>- oOo -</b></p>
        <h2 id="officeTitle"><?= htmlspecialchars($defaultOfficeTitle, ENT_QUOTES, 'UTF-8') ?></h2>

        <!-- Office Selector -->
        <div id="office-selector-container" style="margin-top: 20px;">
          <label for="officeSelect">Piliin ang TANGGAPAN na sinerbisyuhan:</label>
          <select id="officeSelect" onchange="handleOfficeSelectionChange()">
            <?php foreach ($csmOffices as $office): ?>
              <option
                value="<?= htmlspecialchars((string)$office['code'], ENT_QUOTES, 'UTF-8') ?>"
                data-office-title="<?= htmlspecialchars((string)$office['title'], ENT_QUOTES, 'UTF-8') ?>"
                <?= (string)$office['code'] === $defaultOfficeCode ? 'selected' : '' ?>
              >
                <?= htmlspecialchars((string)$office['label'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
            <option value="OTHER">SPECIFY / OTHER</option>
          </select>
          <input type="text" id="otherOfficeName" placeholder="Ipasok ang pangalan ng Tanggapan..." style="display:none;" oninput="updateOfficeTitle(this.value)">
        </div>
        <!-- End Office Selector -->

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
        <label><input type="checkbox" id="clientTypeCitizen"> Mamamayan</label>
        <label><input type="checkbox" id="clientTypeBusiness"> Negosyo</label>
        <label><input type="checkbox" id="clientTypeGovernment"> Gobyerno</label>
      </div>

      <div class="form-group inline-group">
        <label>Petsa:</label> <input type="date" id="visitDate">
        <label>Kasarian:</label>
        <label><input type="checkbox" id="genderMale"> Lalaki</label>
        <label><input type="checkbox" id="genderFemale"> Babae</label>
        <label>Edad:</label> <input type="number" min="1" id="ageInput" style="width:80px">
      </div>

      <div class="form-group">
        <label for="rehiyonSelect">Rehiyon:</label> 
        <select id="rehiyonSelect" name="rehiyon" style="margin-top: 5px; min-height: 40px; padding: 10px 12px;">
            <option value="" disabled selected>Pumili ng Rehiyon</option>
            <option value="NCR">National Capital Region (NCR) - Metro Manila</option>
            <option value="CAR">Cordillera Administrative Region (CAR) - Northern Luzon highlands</option>
            <option value="Region I">Region I (Ilocos Region) - Northwest Luzon</option>
            <option value="Region II">Region II (Cagayan Valley) - Northeast Luzon</option>
            <option value="Region III">Region III (Central Luzon) - Central Luzon</option>
            <option value="Region IV-A">Region IV-A (CALABARZON) - Southern Tagalog</option>
            <option value="Region IV-B">Region IV-B (Mimaropa) - Mindoro, Marinduque, Romblon, Palawan</option>
            <option value="Region V">Region V (Bicol Region) - Southeast Luzon</option>
            <option value="Region VI">Region VI (Western Visayas) - Central Philippines</option>
            <option value="Region VII">Region VII (Central Visayas) - Central Philippines</option>
            <option value="Region VIII">Region VIII (Eastern Visayas) - Eastern Philippines</option>
            <option value="Region IX">Region IX (Zamboanga Peninsula) - Western Mindanao</option>
            <option value="Region X">Region X (Northern Mindanao) - Northern Mindanao</option>
            <option value="Region XI">Region XI (Davao Region) - Southern Mindanao</option>
            <option value="Region XII">Region XII (Soccsksargen) - South-Central Mindanao</option>
            <option value="Region XIII">Region XIII (Caraga) - Northeastern Mindanao</option>
            <option value="BARMM">Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="transactionType">Uri ng transaksyon o serbisyo:</label>
        <select id="transactionType" style="margin-top: 5px; min-height: 40px; padding: 10px 12px;">
          <option value="" disabled selected>Pumili ng uri ng transaksyon o serbisyo</option>
        </select>
        <input type="text" id="transactionTypeOther" placeholder="Ilagay ang ibang uri ng transaksyon o serbisyo" style="display:none; margin-top: 8px; min-height: 40px; padding: 10px 12px;">
      </div>
    </div>

    <h4 class="section-title">Citizen's Charter (CC)</h4>
    <p class="instructions">Lagyan ng tsek (&#10003;) ang angkop na sagot.</p>

    <div class="form-section">
      <label>CC1. Alin sa mga sumusunod ang naglalarawan sa iyong kaalaman sa CC?</label>
      <div class="checkbox-group">
        <label><input type="radio" name="cc1" id="cc1_1"> 1. Alam ko ang CC at nakita ko ito sa napuntahang opisina</label>
        <label><input type="radio" name="cc1" id="cc1_2"> 2. Alam ko ang CC pero hindi ko ito nakita sa napuntahang opisina</label>
        <label><input type="radio" name="cc1" id="cc1_3"> 3. Nalaman ko ang CC nang makita ko ito sa napuntahang opisina</label>
        <label><input type="radio" name="cc1" id="cc1_4"> 4. Hindi ko alam kung ano ang CC at wala akong nakita sa opisina</label>
      </div>

      <label>CC2. Kung alam ang CC, masasabi mo ba na ang CC ng opisina ay...</label>
      <div class="checkbox-group">
        <label><input type="radio" name="cc2" id="cc2_1"> 1. Madaling makita</label>
        <label><input type="radio" name="cc2" id="cc2_2"> 2. Medyo madaling makita</label>
        <label><input type="radio" name="cc2" id="cc2_3"> 3. Mahirap makita</label>
        <label><input type="radio" name="cc2" id="cc2_4"> 4. Hindi makita</label>
        <label><input type="radio" name="cc2" id="cc2_5"> 5. N/A</label>
      </div>

      <label>CC3. Kung alam ang CC, gaano nakatulong ang CC sa transaksyon mo?</label>
      <div class="checkbox-group">
        <label><input type="radio" name="cc3" id="cc3_1"> 1. Sobrang nakatulong</label>
        <label><input type="radio" name="cc3" id="cc3_2"> 2. Nakatulong naman</label>
        <label><input type="radio" name="cc3" id="cc3_3"> 3. Hindi nakatulong</label>
        <label><input type="radio" name="cc3" id="cc3_4"> 4. N/ A</label>
      </div>
    </div>
  </div>

  <!-- PAGE 2 -->
  <div class="container page-break" id="page2">
    <h4 class="section-title">Satisfaction Questions (SQD 0-8)</h4>
    <p class="instructions">Pumili ng isang emoji na pinakaangkop sa iyong sagot.</p>

    <div class="table-responsive">
      <table id="emojiTable">
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
        <tbody></tbody>
      </table>
    </div>

    <div class="form-section">
      <label>Mga suhestiyon (opsyonal):</label>
      <textarea id="suggestionsInput"></textarea>
      <label>Email address (opsyonal):</label>
      <input type="text" id="emailInput">
    </div>

    <button class="submit-btn" id="submitButton" onclick="submitResponse()">Isumite</button>

    <div class="footer">MARAMING SALAMAT!</div>
  </div>
  <script>
    const officeServices = <?= json_encode($csmServicesByOffice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const defaultOfficeCode = <?= json_encode($defaultOfficeCode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // Update office title
    function updateOfficeTitle(value) {
        const officeTitleElement = document.getElementById('officeTitle');
        const officeSelect = document.getElementById('officeSelect');
        const otherOfficeInput = document.getElementById('otherOfficeName');
        const isOtherSelected = officeSelect && officeSelect.value === 'OTHER';

        if (isOtherSelected && value !== 'OTHER') {
            otherOfficeInput.style.display = 'block';
            officeTitleElement.textContent = value.trim() ? `PAMBAYANG TANGGAPAN NG ${value.trim().toUpperCase()}` : 'PAMBAYANG TANGGAPAN NG IBANG TANGGAPAN';
            return;
        }

        if (value === 'OTHER') {
            otherOfficeInput.style.display = 'block';
            officeTitleElement.textContent = 'PAMBAYANG TANGGAPAN NG IBANG TANGGAPAN';
        } else {
            const selectedOption = officeSelect ? officeSelect.options[officeSelect.selectedIndex] : null;
            const officeTitle = selectedOption ? selectedOption.getAttribute('data-office-title') : '';
            otherOfficeInput.style.display = 'none';
            otherOfficeInput.value = '';
            officeTitleElement.textContent = officeTitle && officeTitle.trim() ? officeTitle.trim() : `PAMBAYANG TANGGAPAN NG ${value}`;
        }
    }

    function populateTransactionTypes(officeCode, selectedValue = '') {
      const transactionTypeSelect = document.getElementById('transactionType');
      const transactionTypeOther = document.getElementById('transactionTypeOther');
      if (!transactionTypeSelect) {
        return;
      }

      const services = Array.isArray(officeServices[officeCode]) ? officeServices[officeCode] : [];
      transactionTypeSelect.innerHTML = '';

      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = services.length > 0
        ? 'Pumili ng uri ng transaksyon o serbisyo'
        : 'Walang naka-set na serbisyo para sa tanggapang ito';
      placeholder.disabled = true;
      placeholder.selected = selectedValue === '';
      transactionTypeSelect.appendChild(placeholder);

      services.forEach((service) => {
        const option = document.createElement('option');
        option.value = service.service_name;
        option.textContent = service.service_name;
        if (selectedValue !== '' && selectedValue === service.service_name) {
          option.selected = true;
          placeholder.selected = false;
        }
        transactionTypeSelect.appendChild(option);
      });

      const otherOption = document.createElement('option');
      otherOption.value = '__OTHER__';
      otherOption.textContent = 'Iba pa';
      if (selectedValue !== '' && !services.some((service) => service.service_name === selectedValue)) {
        otherOption.selected = true;
        placeholder.selected = false;
        if (transactionTypeOther) {
          transactionTypeOther.value = selectedValue;
        }
      }
      transactionTypeSelect.appendChild(otherOption);

      toggleTransactionTypeOther();
    }

    function toggleTransactionTypeOther() {
      const officeSelect = document.getElementById('officeSelect');
      const transactionTypeSelect = document.getElementById('transactionType');
      const transactionTypeOther = document.getElementById('transactionTypeOther');
      if (!transactionTypeSelect || !transactionTypeOther) {
        return;
      }

      const isOtherSelected = transactionTypeSelect.value === '__OTHER__' || (officeSelect && officeSelect.value === 'OTHER');
      transactionTypeOther.style.display = isOtherSelected ? 'block' : 'none';
      if (!isOtherSelected) {
        transactionTypeOther.value = '';
      }
    }

    function handleOfficeSelectionChange() {
      const officeSelect = document.getElementById('officeSelect');
      if (!officeSelect) {
        return;
      }

      updateOfficeTitle(officeSelect.value);
      populateTransactionTypes(officeSelect.value);
    }

    // Get office name for filename
    function getCurrentOfficeName() {
        const officeSelect = document.getElementById('officeSelect');
        const otherOfficeInput = document.getElementById('otherOfficeName');

        if (officeSelect.value === 'OTHER' && otherOfficeInput.value.trim()) {
            return otherOfficeInput.value.trim().replace(/[^a-zA-Z0-9_-]/g, '_');
        }

        return officeSelect.value;
    }

    function collectFormData() {
      function isChecked(id) {
        const el = document.getElementById(id);
        return !!(el && el.checked);
      }

      function getTransactionTypeValue() {
        const officeSelect = document.getElementById('officeSelect');
        const transactionTypeSelect = document.getElementById('transactionType');
        const transactionTypeOther = document.getElementById('transactionTypeOther');

        if (!transactionTypeSelect) {
          return '';
        }

        if (officeSelect && officeSelect.value === 'OTHER') {
          return transactionTypeOther ? transactionTypeOther.value.trim() : '';
        }

        if (transactionTypeSelect.value === '__OTHER__') {
          return transactionTypeOther ? transactionTypeOther.value.trim() : '';
        }

        return transactionTypeSelect.value.trim();
      }

      const officeSelect = document.getElementById('officeSelect');
      const officeTitle = document.getElementById('officeTitle');
      const otherOfficeName = document.getElementById('otherOfficeName');
      const visitDate = document.getElementById('visitDate');
      const ageInput = document.getElementById('ageInput');
      const regionSelect = document.getElementById('rehiyonSelect');
      const suggestionsInput = document.getElementById('suggestionsInput');
      const emailInput = document.getElementById('emailInput');

      const sqdRatings = {};
      for (let i = 0; i <= 8; i++) {
        const checked = document.querySelector(`input[name="sqd${i}"]:checked`);
        sqdRatings[`sqd${i}`] = checked ? checked.value : null;
      }

      return {
        office: {
          selected: officeSelect ? officeSelect.value : null,
          custom_name: otherOfficeName ? otherOfficeName.value.trim() : '',
          title: officeTitle ? officeTitle.textContent.trim() : ''
        },
        client_type: {
          mamamayan: isChecked('clientTypeCitizen'),
          negosyo: isChecked('clientTypeBusiness'),
          gobyerno: isChecked('clientTypeGovernment')
        },
        respondent: {
          date: visitDate ? visitDate.value : null,
          kasarian: {
            lalaki: isChecked('genderMale'),
            babae: isChecked('genderFemale')
          },
          edad: ageInput && ageInput.value !== '' ? Number(ageInput.value) : null,
          rehiyon: regionSelect ? regionSelect.value : null
        },
        transaction_type: getTransactionTypeValue(),
        citizen_charter: {
          cc1: [
            isChecked('cc1_1'),
            isChecked('cc1_2'),
            isChecked('cc1_3'),
            isChecked('cc1_4')
          ],
          cc2: [
            isChecked('cc2_1'),
            isChecked('cc2_2'),
            isChecked('cc2_3'),
            isChecked('cc2_4'),
            isChecked('cc2_5')
          ],
          cc3: [
            isChecked('cc3_1'),
            isChecked('cc3_2'),
            isChecked('cc3_3'),
            isChecked('cc3_4')
          ]
        },
        sqd_ratings: sqdRatings,
        suggestions: suggestionsInput ? suggestionsInput.value.trim() : '',
        email: emailInput ? emailInput.value.trim() : ''
      };
    }

    async function saveResponseToDatabase(payload) {
      const response = await fetch('save_client_satisfaction.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        throw new Error('Failed to save response.');
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.message || 'Failed to save response.');
      }
      return result;
    }

    function resetCsmForm() {
      const officeSelect = document.getElementById('officeSelect');
      const otherOfficeName = document.getElementById('otherOfficeName');
      const visitDate = document.getElementById('visitDate');
      const ageInput = document.getElementById('ageInput');
      const regionSelect = document.getElementById('rehiyonSelect');
      const transactionType = document.getElementById('transactionType');
      const transactionTypeOther = document.getElementById('transactionTypeOther');
      const suggestionsInput = document.getElementById('suggestionsInput');
      const emailInput = document.getElementById('emailInput');

      if (officeSelect) {
        officeSelect.value = defaultOfficeCode;
        handleOfficeSelectionChange();
      }
      if (otherOfficeName) {
        otherOfficeName.value = '';
        otherOfficeName.style.display = 'none';
      }

      if (visitDate) visitDate.value = '';
      if (ageInput) ageInput.value = '';
      if (regionSelect) regionSelect.selectedIndex = 0;
      if (transactionType) transactionType.selectedIndex = 0;
      if (transactionTypeOther) {
        transactionTypeOther.value = '';
        transactionTypeOther.style.display = 'none';
      }
      if (suggestionsInput) suggestionsInput.value = '';
      if (emailInput) emailInput.value = '';

      const checks = document.querySelectorAll('input[type=\"checkbox\"], input[type=\"radio\"]');
      checks.forEach((input) => {
        input.checked = false;
      });

      const ratingCells = document.querySelectorAll('#emojiTable td.selected-rating');
      ratingCells.forEach((cell) => {
        cell.classList.remove('selected-rating');
      });
    }

    document.addEventListener("DOMContentLoaded", () => {
      const transactionTypeSelect = document.getElementById('transactionType');
      const officeSelect = document.getElementById('officeSelect');

      if (officeSelect) {
        handleOfficeSelectionChange();
      }

      if (transactionTypeSelect) {
        transactionTypeSelect.addEventListener('change', toggleTransactionTypeOther);
      }

      const questions = [
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
      const ratingOptions = [
        { value: 1, label: 'Lubos na hindi sumasang-ayon', emoji: '😠' },
        { value: 2, label: 'Hindi sumasang-ayon', emoji: '🙁' },
        { value: 3, label: 'Walang kinikilingan', emoji: '😐' },
        { value: 4, label: 'Sumasang-ayon', emoji: '🙂' },
        { value: 5, label: 'Labis na sumasang-ayon', emoji: '😃' },
        { value: 6, label: 'N/A', emoji: '🚫' }
      ];

      const tbody = document.querySelector("#emojiTable tbody");
      questions.forEach((q, i) => {
        const row = document.createElement("tr");
        row.innerHTML = `
          <td class="question">SQD${i}. ${q}</td>
          ${ratingOptions.map((option) => `
            <td data-label="${option.label}">
              <span class="mobile-option-label">
                <span class="emoji">${option.emoji}</span>
                <span>${option.label}</span>
              </span>
              <input type="radio" name="sqd${i}" value="${option.value}" aria-label="${option.label}">
            </td>
          `).join("")}
        `;
        tbody.appendChild(row);
      });

      // --- NEW: Make entire table cell clickable for ratings ---
      const ratingCells = document.querySelectorAll("#emojiTable td");
      ratingCells.forEach(cell => {
          const radio = cell.querySelector('input[type="radio"]');
          if (radio) {
              cell.classList.add('rating-cell'); // Add class for styling and cursor
              cell.addEventListener('click', () => {
                  // Remove 'selected' from other cells in the same row
                  const rowCells = cell.parentElement.querySelectorAll('td');
                  rowCells.forEach(c => c.classList.remove('selected-rating'));
                  
                  // Select the radio button and add class to the clicked cell
                  radio.checked = true;
                  cell.classList.add('selected-rating');
              });
          }
      });
      // --- END NEW ---

    });

    async function submitResponse() {
      const overlay = document.getElementById("overlay");
      const overlayMessage = overlay.querySelector('p');
      const button = document.getElementById("submitButton");
      const successModal = document.getElementById("successModal");
      const successBackdrop = document.getElementById("successBackdrop");
      let isSaved = false;

      overlayMessage.textContent = "Sine-save ang iyong sagot... Paki-hintay.";
      overlay.style.display = "flex";
      button.disabled = true; // prevent double clicks

      try {
        const formPayload = collectFormData();
        await saveResponseToDatabase(formPayload);
        isSaved = true;
        
        // Hide loading overlay and show success modal
        overlay.style.display = "none";
        successBackdrop.style.display = "block";
        successModal.style.display = "block";
        
      } catch (error) {
        console.error("Submission failed:", error);
        overlayMessage.textContent = "Pumalya ang pagsumite. Pakisubukang muli.";
      } finally {
        setTimeout(() => {
          if (isSaved) {
            // Hide success modal with animation
            successModal.classList.add('hide');
            successBackdrop.classList.add('hide');
            
            setTimeout(() => {
              resetCsmForm();
              successModal.classList.remove('hide');
              successBackdrop.style.display = "none";
              button.textContent = "Isumite";
              overlayMessage.textContent = "Loading..."; // reset
            }, 400);
          } else {
            overlay.style.display = "none";
            button.disabled = false;
            button.textContent = "Isumite";
            overlayMessage.textContent = "Loading..."; // reset
          }
        }, 3500);
      }
    }
  </script>
</body>
</html>
