<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int) $user['id'];

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die('Dompdf is not installed. Run: composer require dompdf/dompdf');
}

require_once $autoloadPath;

use Dompdf\Dompdf;
use Dompdf\Options;

$statsStatement = $pdo->prepare("
    SELECT
        COUNT(*) AS total_missions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_missions
    FROM user_missions
    WHERE user_id = :user_id
");

$statsStatement->execute([
    'user_id' => $userId,
]);

$stats = $statsStatement->fetch();

$totalMissions = (int) ($stats['total_missions'] ?? 0);
$completedMissions = (int) ($stats['completed_missions'] ?? 0);

if ($totalMissions === 0 || $completedMissions < $totalMissions) {
    redirect('profile.php');
}

$user = current_user($pdo);
$issueDate = date('F d, Y');
$certificateId = 'CA-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT) . '-' . date('Ymd');

$html = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body {
      font-family: DejaVu Sans, sans-serif;
      background: #0b0f1a;
      color: #e6edf6;
      padding: 40px;
    }

    .certificate {
      border: 4px solid #00f5a0;
      padding: 50px;
      text-align: center;
      min-height: 620px;
      background: #121826;
    }

    .brand {
      color: #00f5a0;
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 35px;
    }

    .label {
      color: #9aa3b2;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    h1 {
      font-size: 40px;
      margin: 18px 0;
      color: #ffffff;
    }

    h2 {
      font-size: 30px;
      margin: 18px 0;
      color: #00f5a0;
    }

    p {
      font-size: 16px;
      line-height: 1.7;
      color: #cbd5e1;
    }

    .footer {
      margin-top: 55px;
      display: table;
      width: 100%;
    }

    .footer-item {
      display: table-cell;
      width: 50%;
      text-align: center;
      font-size: 13px;
      color: #9aa3b2;
    }

    .line {
      border-top: 1px solid #5ee7ff;
      width: 180px;
      margin: 0 auto 8px;
    }
  </style>
</head>
<body>
  <div class="certificate">
    <div class="brand">CyberAware</div>

    <div class="label">Certificate of Completion</div>

    <h1>Cyber Awareness Training</h1>

    <p>This certificate is proudly presented to</p>

    <h2>' . htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') . '</h2>

    <p>
      for successfully completing all required cyber awareness missions,
      including phishing awareness, password security, malware safety,
      and social engineering response.
    </p>

    <p>
      Total XP Earned: <strong>' . (int) $user['total_xp'] . '</strong>
    </p>

    <div class="footer">
      <div class="footer-item">
        <div class="line"></div>
        Issue Date<br>
        ' . htmlspecialchars($issueDate, ENT_QUOTES, 'UTF-8') . '
      </div>

      <div class="footer-item">
        <div class="line"></div>
        Certificate ID<br>
        ' . htmlspecialchars($certificateId, ENT_QUOTES, 'UTF-8') . '
      </div>
    </div>
  </div>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$dompdf->stream('cyberaware-certificate.pdf', [
    'Attachment' => true,
]);