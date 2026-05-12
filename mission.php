<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int) $user['id'];

$type = trim($_GET['type'] ?? 'phishing');

$missionConfigs = [
    'phishing' => [
        'title' => 'Phishing Trap',
        'icon' => 'fa-solid fa-envelope-open-text',
        'mode' => 'clues',
        'timed' => true,
        'duration' => 90,
        'instruction' => 'Identify all suspicious signs inside the email.',
        'scenario_title' => 'Suspicious Email',
        'scenario_html' => '
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <div class="text-muted small">From</div>
                <div>security@paypa1-support.com</div>
              </div>
              <span class="status-pill warn">
                <i class="fa-solid fa-triangle-exclamation"></i> External
              </span>
            </div>

            <div class="text-muted small">Subject</div>
            <div class="fw-semibold mb-3">Verify your account immediately</div>

            <div class="panel glass">
              <p>Hello,</p>
              <p>We detected unusual activity. Verify now to avoid suspension.</p>
              <p><span class="danger">Click here to verify</span></p>
              <p>Failure to act within 24 hours will lock your account.</p>
              <p>Thanks, PayPal Security Team</p>
            </div>
        ',
        'clues' => [
            ['id' => 'lookalike-domain', 'label' => 'Lookalike sender domain', 'correct' => true],
            ['id' => 'urgent-threat', 'label' => 'Urgent threat language', 'correct' => true],
            ['id' => 'suspicious-link', 'label' => 'Suspicious verification link', 'correct' => true],
            ['id' => 'generic-greeting', 'label' => 'Generic greeting', 'correct' => true],
            ['id' => 'short-email', 'label' => 'Short email', 'correct' => false],
        ],
    ],

    'malware' => [
        'title' => 'Malware Radar',
        'icon' => 'fa-solid fa-bug',
        'mode' => 'clues',
        'timed' => true,
        'duration' => 90,
        'instruction' => 'Scan the fake website and select unsafe indicators.',
        'scenario_title' => 'Unsafe Website Scan',
        'scenario_html' => '
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="text-muted small">URL</div>
              <span class="status-pill locked">Not Secure</span>
            </div>

            <div class="fw-semibold mb-3">
              http://secure-bank-login.verify-now.net
            </div>

            <div class="panel glass">
              <h5 class="danger">Account compromised</h5>
              <p>Immediate action required.</p>
              <button type="button" class="btn btn-danger">Download Security Update</button>
              <p class="text-muted mt-3 mb-0">Sponsored Ads - Popups Enabled</p>
            </div>
        ',
        'clues' => [
            ['id' => 'long-url', 'label' => 'Random subdomain and long URL', 'correct' => true],
            ['id' => 'http-warning', 'label' => 'HTTP and not secure warning', 'correct' => true],
            ['id' => 'scare-message', 'label' => 'Scare tactic message', 'correct' => true],
            ['id' => 'fake-download', 'label' => 'Fake download button', 'correct' => true],
            ['id' => 'ads-popups', 'label' => 'Excessive ads and popups', 'correct' => true],
            ['id' => 'clear-cta', 'label' => 'Clear call-to-action button', 'correct' => false],
        ],
    ],

    'password' => [
        'title' => 'Password Forge',
        'icon' => 'fa-solid fa-key',
        'mode' => 'password',
        'timed' => false,
        'duration' => null,
        'instruction' => 'Create a strong password that passes all five rules. The password will not be saved.',
    ],

    'social' => [
        'title' => 'Social Shield',
        'icon' => 'fa-solid fa-user-secret',
        'mode' => 'choice',
        'timed' => false,
        'duration' => null,
        'instruction' => 'Choose the safest response to the social engineering attempt.',
        'scenario_title' => 'Incoming Call',
        'scenario_html' => '
            <div class="panel glass">
              <p class="mb-2">
                <strong>Caller:</strong> "This is IT. We need your login now."
              </p>
              <p class="text-muted mb-0">
                The caller sounds urgent and asks for credentials.
              </p>
            </div>
        ',
        'choices' => [
            ['id' => 'share', 'label' => 'Share credentials to avoid disruption', 'correct' => false],
            ['id' => 'ask-id', 'label' => 'Ask for ID and continue the call', 'correct' => false],
            ['id' => 'report', 'label' => 'End the call and report to security', 'correct' => true],
            ['id' => 'ignore', 'label' => 'Ignore and do nothing', 'correct' => false],
        ],
    ],
];

if (!isset($missionConfigs[$type])) {
    redirect('dashboard.php');
}

$config = $missionConfigs[$type];

/*
|--------------------------------------------------------------------------
| Make sure the user has mission rows
|--------------------------------------------------------------------------
*/
$assignMissions = $pdo->prepare("
    INSERT IGNORE INTO user_missions (user_id, mission_id, status)
    SELECT
        :user_id,
        id,
        CASE
            WHEN unlock_order = 1 THEN 'pending'
            ELSE 'locked'
        END
    FROM missions
");

$assignMissions->execute([
    'user_id' => $userId,
]);

/*
|--------------------------------------------------------------------------
| Load mission
|--------------------------------------------------------------------------
*/
$missionStatement = $pdo->prepare("
    SELECT
        m.id,
        m.slug,
        m.title,
        m.description,
        m.xp_reward,
        m.unlock_order,
        um.status,
        um.score
    FROM missions m
    INNER JOIN user_missions um ON um.mission_id = m.id
    WHERE m.slug = :slug
    AND um.user_id = :user_id
    LIMIT 1
");

$missionStatement->execute([
    'slug' => $type,
    'user_id' => $userId,
]);

$mission = $missionStatement->fetch();

if (!$mission) {
    redirect('dashboard.php');
}

$isLocked = $mission['status'] === 'locked';
$isCompleted = $mission['status'] === 'completed';

$sessionKey = 'mission_started_' . $type;

if (!$isCompleted && !$isLocked && $config['timed'] && empty($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = time();
}

$remainingSeconds = null;

if ($config['timed']) {
    $startedAt = (int) ($_SESSION[$sessionKey] ?? time());
    $elapsed = time() - $startedAt;
    $remainingSeconds = max(0, (int) $config['duration'] - $elapsed);
}

$feedback = null;
$feedbackType = 'warn';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function evaluate_clues(array $config, array $selectedIds): array
{
    $correctIds = [];
    $wrongIds = [];

    foreach ($config['clues'] as $clue) {
        if ($clue['correct']) {
            $correctIds[] = $clue['id'];
        } else {
            $wrongIds[] = $clue['id'];
        }
    }

    $correctSelected = count(array_intersect($correctIds, $selectedIds));
    $wrongSelected = count(array_intersect($wrongIds, $selectedIds));

    $baseScore = (int) round(($correctSelected / count($correctIds)) * 10);
    $penalty = $wrongSelected * 2;
    $score = max(0, min(10, $baseScore - $penalty));

    $passed = $score >= 7;

    return [
        'passed' => $passed,
        'score' => $score,
        'correct_selected' => $correctSelected,
        'total_correct' => count($correctIds),
        'wrong_selected' => $wrongSelected,
    ];
}

function evaluate_password(string $password): array
{
    $rules = [
        '10+ characters' => strlen($password) >= 10,
        'Uppercase letter' => preg_match('/[A-Z]/', $password) === 1,
        'Lowercase letter' => preg_match('/[a-z]/', $password) === 1,
        'Number' => preg_match('/[0-9]/', $password) === 1,
        'Symbol' => preg_match('/[^A-Za-z0-9]/', $password) === 1,
    ];

    $passedRules = count(array_filter($rules));
    $score = $passedRules * 2;

    return [
        'passed' => $score === 10,
        'score' => $score,
        'rules' => $rules,
        'passed_rules' => $passedRules,
    ];
}

function evaluate_choice(array $config, string $selectedId): array
{
    foreach ($config['choices'] as $choice) {
        if ($choice['id'] === $selectedId && $choice['correct']) {
            return [
                'passed' => true,
                'score' => 10,
            ];
        }
    }

    return [
        'passed' => false,
        'score' => 0,
    ];
}

function complete_mission(PDO $pdo, int $userId, array $mission, int $score): bool
{
    if ($mission['status'] === 'completed') {
        return false;
    }

    $missionId = (int) $mission['id'];
    $earnedXp = min((int) $mission['xp_reward'], $score);

    $pdo->beginTransaction();

    try {
        $updateMission = $pdo->prepare("
            UPDATE user_missions
            SET status = 'completed',
                score = :score,
                completed_at = NOW()
            WHERE user_id = :user_id
            AND mission_id = :mission_id
        ");

        $updateMission->execute([
            'score' => $score,
            'user_id' => $userId,
            'mission_id' => $missionId,
        ]);

        $updateUser = $pdo->prepare("
            UPDATE users
            SET total_xp = total_xp + :earned_xp
            WHERE id = :user_id
        ");

        $updateUser->execute([
            'earned_xp' => $earnedXp,
            'user_id' => $userId,
        ]);

        $badgeStatement = $pdo->prepare("
            INSERT IGNORE INTO user_badges (user_id, badge_id)
            SELECT :user_id, id
            FROM badges
            WHERE required_mission_slug = :mission_slug
            OR (
                required_xp > 0
                AND required_xp <= (
                    SELECT total_xp
                    FROM users
                    WHERE id = :user_id_2
                )
            )
        ");

        $badgeStatement->execute([
            'user_id' => $userId,
            'mission_slug' => $mission['slug'],
            'user_id_2' => $userId,
        ]);

        $unlockNext = $pdo->prepare("
            UPDATE user_missions um
            INNER JOIN missions m ON m.id = um.mission_id
            SET um.status = 'pending'
            WHERE um.user_id = :user_id
            AND um.status = 'locked'
            AND m.unlock_order <= :next_order
        ");

        $unlockNext->execute([
            'user_id' => $userId,
            'next_order' => ((int) $mission['unlock_order']) + 1,
        ]);

        log_activity(
            $pdo,
            $userId,
            'mission',
            'Completed mission: ' . $mission['title'] . '. Earned ' . $earnedXp . ' XP.'
        );

        $pdo->commit();

        return true;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

/*
|--------------------------------------------------------------------------
| Handle submission
|--------------------------------------------------------------------------
*/
if (is_post_request() && !$isLocked) {
    if ($isCompleted) {
        $feedback = 'This mission is already completed. You can review it anytime.';
        $feedbackType = 'success';
    } else {
        $timedOut = false;

        if ($config['timed']) {
            $startedAt = (int) ($_SESSION[$sessionKey] ?? time());
            $elapsed = time() - $startedAt;
            $timedOut = $elapsed > ((int) $config['duration'] + 3);
        }

        if ($timedOut) {
            $feedback = 'Time is up. Review the clues and try again.';
            $feedbackType = 'warn';
            unset($_SESSION[$sessionKey]);
        } else {
            $evaluation = [
                'passed' => false,
                'score' => 0,
            ];

            if ($config['mode'] === 'clues') {
                $selectedIds = $_POST['clues'] ?? [];
                $selectedIds = is_array($selectedIds) ? $selectedIds : [];

                $evaluation = evaluate_clues($config, $selectedIds);

                if ($evaluation['passed']) {
                    $feedback = 'Good work. You found ' .
                        $evaluation['correct_selected'] . ' of ' .
                        $evaluation['total_correct'] .
                        ' correct signs. Score: ' . $evaluation['score'] . '/10.';
                } else {
                    $feedback = 'Not quite. You found ' .
                        $evaluation['correct_selected'] . ' of ' .
                        $evaluation['total_correct'] .
                        ' correct signs. Try again.';
                }
            }

            if ($config['mode'] === 'password') {
                $password = $_POST['password_value'] ?? '';
                $evaluation = evaluate_password($password);

                if ($evaluation['passed']) {
                    $feedback = 'Strong password created. Score: 10/10. Your password was not saved.';
                } else {
                    $feedback = 'Password is not strong enough yet. You passed ' .
                        $evaluation['passed_rules'] .
                        ' of 5 rules.';
                }
            }

            if ($config['mode'] === 'choice') {
                $selectedChoice = trim($_POST['choice'] ?? '');
                $evaluation = evaluate_choice($config, $selectedChoice);

                if ($evaluation['passed']) {
                    $feedback = 'Correct decision. Never share credentials. Report suspicious requests.';
                } else {
                    $feedback = 'Unsafe choice. The safest action is to end the call and report to security.';
                }
            }

            if ($evaluation['passed']) {
                complete_mission($pdo, $userId, $mission, (int) $evaluation['score']);
                $feedback .= ' XP and badge progress saved.';
                $feedbackType = 'success';
                unset($_SESSION[$sessionKey]);

                $mission['status'] = 'completed';
                $mission['score'] = (int) $evaluation['score'];
                $isCompleted = true;
            } else {
                $feedbackType = 'locked';

                if ($config['timed']) {
                    $_SESSION[$sessionKey] = time();
                    $remainingSeconds = (int) $config['duration'];
                }
            }
        }
    }
}

function status_class_for_feedback(string $type): string
{
    return match ($type) {
        'success' => 'success',
        'locked' => 'locked',
        default => 'warn',
    };
}

function format_mission_time(?int $seconds): string
{
    if ($seconds === null) {
        return '';
    }

    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;

    return sprintf('%02d:%02d', $minutes, $secs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo e($config['title']); ?> | CyberAware</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="assets/css/main.css">
</head>

<body class="scanlines">
  <nav class="navbar topbar px-3">
    <a class="icon-button" href="dashboard.php" title="Back to Hub">
      <i class="fa-solid fa-arrow-left"></i>
    </a>

    <span class="navbar-brand fw-bold ms-2">
      <i class="<?php echo e($config['icon']); ?> accent"></i>
      <?php echo e($config['title']); ?>
    </span>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <?php if ($config['timed'] && !$isCompleted && !$isLocked): ?>
        <span class="status-pill warn">
          <i class="fa-solid fa-stopwatch"></i>
          <span id="missionTimer">
            <?php echo e(format_mission_time($remainingSeconds)); ?>
          </span>
        </span>
      <?php endif; ?>

      <a class="icon-button" href="profile.php" title="Profile">
        <i class="fa-solid fa-user-astronaut"></i>
      </a>
    </div>
  </nav>

  <main class="container stage">
    <?php if ($isLocked): ?>
      <div class="panel glass text-center">
        <div class="icon-circle mx-auto mb-3">
          <i class="fa-solid fa-lock"></i>
        </div>

        <h3 class="mb-2">Mission Locked</h3>

        <p class="text-muted">
          Complete earlier missions to unlock <?php echo e($config['title']); ?>.
        </p>

        <a class="btn btn-glow" href="dashboard.php">
          Back to Hub
        </a>
      </div>
    <?php else: ?>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <span class="status-pill <?php echo $isCompleted ? 'success' : 'warn'; ?> mb-2">
            <?php echo $isCompleted ? 'Completed' : 'Active Mission'; ?>
          </span>

          <h2 class="mb-1"><?php echo e($config['title']); ?></h2>

          <p class="text-muted mb-0">
            <?php echo e($config['instruction']); ?>
          </p>
        </div>

        <div class="panel glass text-center">
          <div class="text-muted small">Reward</div>
          <div class="stat-value">
            <?php echo (int) $mission['xp_reward']; ?> XP
          </div>
        </div>
      </div>

      <?php if ($feedback): ?>
        <div class="result-box mb-4">
          <span class="status-pill <?php echo e(status_class_for_feedback($feedbackType)); ?> mb-2">
            Result
          </span>

          <div>
            <?php echo e($feedback); ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($isCompleted): ?>
        <div class="panel glass text-center mb-4">
          <div class="icon-circle mx-auto mb-3">
            <i class="fa-solid fa-check"></i>
          </div>

          <h4 class="mb-1">Mission Completed</h4>

          <p class="text-muted mb-0">
            Saved score: <?php echo (int) $mission['score']; ?> / 10
          </p>

          <div class="d-flex justify-content-center gap-2 mt-3">
            <a class="btn btn-ghost" href="dashboard.php">
              Back to Hub
            </a>

            <a class="btn btn-glow" href="profile.php">
              View Rewards
            </a>
          </div>
        </div>
      <?php endif; ?>

      <form id="missionForm" method="POST" action="mission.php?type=<?php echo e(urlencode($type)); ?>">
        <?php if (in_array($config['mode'], ['clues', 'choice'], true)): ?>
          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-display accent"></i>
              <span class="fw-semibold"><?php echo e($config['scenario_title']); ?></span>
            </div>

            <?php echo $config['scenario_html']; ?>
          </div>
        <?php endif; ?>

        <?php if ($config['mode'] === 'clues'): ?>
          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-magnifying-glass warning"></i>
              <span class="fw-semibold">Select suspicious clues</span>
            </div>

            <div class="clue-list">
              <?php foreach ($config['clues'] as $clue): ?>
                <div class="form-check">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    name="clues[]"
                    value="<?php echo e($clue['id']); ?>"
                    id="clue-<?php echo e($clue['id']); ?>"
                    <?php echo $isCompleted ? 'disabled' : ''; ?>
                  >

                  <label class="form-check-label" for="clue-<?php echo e($clue['id']); ?>">
                    <?php echo e($clue['label']); ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($config['mode'] === 'password'): ?>
          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-key accent"></i>
              <span class="fw-semibold">Create a strong password</span>
            </div>

            <input
              type="password"
              id="passwordInput"
              name="password_value"
              class="form-control form-control-lg"
              placeholder="Type a secure password"
              autocomplete="off"
              <?php echo $isCompleted ? 'disabled' : ''; ?>
            >

            <div class="mt-3">
              <span class="text-muted small">Strength</span>
              <span id="strengthText" class="fw-semibold ms-2"></span>
            </div>

            <div class="progress-track mt-2">
              <div id="strengthBar" class="progress-fill" style="width: 0%"></div>
            </div>
          </div>

          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-shield warning"></i>
              <span class="fw-semibold">Rules</span>
            </div>

            <ul class="list-group">
              <li id="rule-length" class="list-group-item d-flex justify-content-between text-muted">
                10+ characters <span>Missing</span>
              </li>
              <li id="rule-upper" class="list-group-item d-flex justify-content-between text-muted">
                Uppercase <span>Missing</span>
              </li>
              <li id="rule-lower" class="list-group-item d-flex justify-content-between text-muted">
                Lowercase <span>Missing</span>
              </li>
              <li id="rule-number" class="list-group-item d-flex justify-content-between text-muted">
                Number <span>Missing</span>
              </li>
              <li id="rule-symbol" class="list-group-item d-flex justify-content-between text-muted">
                Symbol <span>Missing</span>
              </li>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($config['mode'] === 'choice'): ?>
          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-headset warning"></i>
              <span class="fw-semibold">Your Response</span>
            </div>

            <div class="list-group">
              <?php foreach ($config['choices'] as $choice): ?>
                <label class="list-group-item">
                  <input
                    class="form-check-input me-2"
                    type="radio"
                    name="choice"
                    value="<?php echo e($choice['id']); ?>"
                    <?php echo $isCompleted ? 'disabled' : ''; ?>
                  >

                  <?php echo e($choice['label']); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$isCompleted): ?>
          <div class="panel text-center">
            <button type="submit" class="btn btn-glow">
              Submit Mission
            </button>

            <a href="dashboard.php" class="btn btn-ghost">
              Back to Hub
            </a>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </main>

  <?php if ($config['mode'] === 'password' && !$isCompleted): ?>
    <script>
      const passwordInput = document.getElementById("passwordInput");
      const strengthText = document.getElementById("strengthText");
      const strengthBar = document.getElementById("strengthBar");

      const rules = [
        { id: "rule-length", test: value => value.length >= 10 },
        { id: "rule-upper", test: value => /[A-Z]/.test(value) },
        { id: "rule-lower", test: value => /[a-z]/.test(value) },
        { id: "rule-number", test: value => /[0-9]/.test(value) },
        { id: "rule-symbol", test: value => /[^A-Za-z0-9]/.test(value) }
      ];

      function updateRules(value) {
        let score = 0;

        rules.forEach(rule => {
          const item = document.getElementById(rule.id);
          const passed = rule.test(value);

          if (!item) return;

          item.classList.toggle("text-success", passed);
          item.classList.toggle("text-muted", !passed);
          item.querySelector("span").textContent = passed ? "Met" : "Missing";

          if (passed) score += 1;
        });

        return score;
      }

      function updateStrength(score) {
        if (!strengthText || !strengthBar) return;

        if (score <= 2) {
          strengthText.textContent = "Weak";
          strengthText.style.color = "#ff5d5d";
          strengthBar.style.width = "30%";
        } else if (score <= 4) {
          strengthText.textContent = "Medium";
          strengthText.style.color = "#ffd166";
          strengthBar.style.width = "65%";
        } else {
          strengthText.textContent = "Strong";
          strengthText.style.color = "#00f5a0";
          strengthBar.style.width = "100%";
        }
      }

      if (passwordInput) {
        passwordInput.addEventListener("input", () => {
          const score = updateRules(passwordInput.value);
          updateStrength(score);
        });
      }
    </script>
  <?php endif; ?>

  <?php if ($config['timed'] && !$isCompleted && !$isLocked): ?>
    <script>
      const timerEl = document.getElementById("missionTimer");
      const missionForm = document.getElementById("missionForm");

      let remainingSeconds = <?php echo (int) $remainingSeconds; ?>;
      let submitted = false;

      function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;

        return String(minutes).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
      }

      function tickTimer() {
        if (!timerEl || !missionForm || submitted) {
          return;
        }

        timerEl.textContent = formatTime(remainingSeconds);

        if (remainingSeconds <= 0) {
          submitted = true;
          missionForm.submit();
          return;
        }

        remainingSeconds -= 1;
      }

      tickTimer();
      setInterval(tickTimer, 1000);
    </script>
  <?php endif; ?>
</body>
</html>