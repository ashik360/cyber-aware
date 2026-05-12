<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Refresh user
|--------------------------------------------------------------------------
*/
$user = current_user($pdo);
$totalXp = (int) ($user['total_xp'] ?? 0);
$rankName = get_rank_name($totalXp);
$threatLevel = get_threat_level($totalXp);

/*
|--------------------------------------------------------------------------
| Mission stats
|--------------------------------------------------------------------------
*/
$missionStatsStatement = $pdo->prepare("
    SELECT
        COUNT(*) AS total_missions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_missions,
        COALESCE(SUM(score), 0) AS mission_score
    FROM user_missions
    WHERE user_id = :user_id
");

$missionStatsStatement->execute([
    'user_id' => $userId,
]);

$missionStats = $missionStatsStatement->fetch();

$totalMissions = (int) ($missionStats['total_missions'] ?? 0);
$completedMissions = (int) ($missionStats['completed_missions'] ?? 0);
$missionScore = (int) ($missionStats['mission_score'] ?? 0);

$progressPercent = $totalMissions > 0
    ? (int) round(($completedMissions / $totalMissions) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| Badges
|--------------------------------------------------------------------------
*/
$badgesStatement = $pdo->prepare("
    SELECT
        b.name,
        b.description,
        b.icon,
        ub.earned_at
    FROM user_badges ub
    INNER JOIN badges b ON b.id = ub.badge_id
    WHERE ub.user_id = :user_id
    ORDER BY ub.earned_at DESC
");

$badgesStatement->execute([
    'user_id' => $userId,
]);

$badges = $badgesStatement->fetchAll();

$totalBadges = count($badges);

/*
|--------------------------------------------------------------------------
| Missions
|--------------------------------------------------------------------------
*/
$missionsStatement = $pdo->prepare("
    SELECT
        m.title,
        m.slug,
        m.xp_reward,
        um.status,
        um.score,
        um.completed_at
    FROM missions m
    INNER JOIN user_missions um ON um.mission_id = m.id
    WHERE um.user_id = :user_id
    ORDER BY m.unlock_order ASC
");

$missionsStatement->execute([
    'user_id' => $userId,
]);

$missions = $missionsStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Quiz history
|--------------------------------------------------------------------------
*/
$quizStatement = $pdo->prepare("
    SELECT
        score,
        total_questions,
        correct_answers,
        time_taken_seconds,
        completed_at
    FROM quiz_attempts
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 5
");

$quizStatement->execute([
    'user_id' => $userId,
]);

$quizAttempts = $quizStatement->fetchAll();

/*
|--------------------------------------------------------------------------
| Recent activity
|--------------------------------------------------------------------------
*/
$activityStatement = $pdo->prepare("
    SELECT
        action_type,
        action_text,
        created_at
    FROM activity_logs
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 8
");

$activityStatement->execute([
    'user_id' => $userId,
]);

$activities = $activityStatement->fetchAll();

$certificateUnlocked = $totalMissions > 0 && $completedMissions === $totalMissions;

function profile_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= mb_substr($part, 0, 1);
        }

        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    return mb_strtoupper($letters ?: 'U');
}

function mission_icon_profile(string $slug): string
{
    return match ($slug) {
        'phishing' => 'fa-solid fa-envelope-open-text',
        'password' => 'fa-solid fa-key',
        'malware' => 'fa-solid fa-bug',
        'social' => 'fa-solid fa-user-secret',
        default => 'fa-solid fa-shield-halved',
    };
}

function profile_status_class(string $status): string
{
    return match ($status) {
        'completed' => 'success',
        'locked' => 'locked',
        default => 'warn',
    };
}

function profile_status_label(string $status): string
{
    return match ($status) {
        'completed' => 'Completed',
        'locked' => 'Locked',
        default => 'Pending',
    };
}

function format_profile_seconds(?int $seconds): string
{
    if ($seconds === null) {
        return 'N/A';
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
  <title>Profile | CyberAware</title>
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
      <i class="fa-solid fa-user-astronaut accent"></i> Profile
    </span>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <a class="icon-button" href="learn.php" title="Learn">
        <i class="fa-solid fa-book-open"></i>
      </a>

      <a class="icon-button" href="quiz.php" title="Quiz">
        <i class="fa-solid fa-clipboard-question"></i>
      </a>

      <a class="icon-button" href="leaderboard.php" title="Leaderboard">
        <i class="fa-solid fa-trophy"></i>
      </a>

      <a class="icon-button" href="logout.php" title="Logout">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </nav>

  <main class="container stage">
    <div class="panel glass mb-4">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="icon-circle accent">
          <span class="fw-bold"><?php echo e(profile_initials($user['full_name'])); ?></span>
        </div>

        <div>
          <h3 class="mb-1"><?php echo e($user['full_name']); ?></h3>
          <div class="text-muted small"><?php echo e($user['email']); ?></div>
        </div>

        <div class="ms-auto d-flex flex-wrap gap-2">
          <span class="rank-chip">
            <i class="fa-solid fa-crown"></i>
            <?php echo e($rankName); ?> Rank
          </span>

          <span class="status-pill <?php echo $certificateUnlocked ? 'success' : 'warn'; ?>">
            <i class="fa-solid fa-certificate"></i>
            <?php echo $certificateUnlocked ? 'Certificate Ready' : 'Certificate Locked'; ?>
          </span>
        </div>
      </div>
    </div>

    <div class="hud mb-4">
      <div class="stat-tile">
        <div class="stat-icon">
          <i class="fa-solid fa-bolt"></i>
        </div>
        <div>
          <div class="stat-value"><?php echo $totalXp; ?></div>
          <div class="stat-label">Total XP</div>
        </div>
      </div>

      <div class="stat-tile">
        <div class="stat-icon">
          <i class="fa-solid fa-award"></i>
        </div>
        <div>
          <div class="stat-value"><?php echo $totalBadges; ?></div>
          <div class="stat-label">Badges</div>
        </div>
      </div>

      <div class="stat-tile">
        <div class="stat-icon">
          <i class="fa-solid fa-flag-checkered"></i>
        </div>
        <div>
          <div class="stat-value"><?php echo $completedMissions; ?> / <?php echo $totalMissions; ?></div>
          <div class="stat-label">Missions</div>
        </div>
      </div>

      <div class="stat-tile">
        <div class="stat-icon">
          <i class="fa-solid fa-shield"></i>
        </div>
        <div>
          <div class="stat-value"><?php echo e($threatLevel); ?></div>
          <div class="stat-label">Threat Level</div>
        </div>
      </div>
    </div>

    <div class="panel mb-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">Profile Completion</span>
        <span class="text-muted small"><?php echo $progressPercent; ?>%</span>
      </div>

      <div class="progress-track">
        <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%"></div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="panel mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-gamepad accent"></i>
            <span class="fw-semibold">Mission Progress</span>
          </div>

          <div class="game-grid">
            <?php foreach ($missions as $mission): ?>
              <div class="game-tile">
                <div class="icon-circle">
                  <i class="<?php echo e(mission_icon_profile($mission['slug'])); ?>"></i>
                </div>

                <div class="fw-semibold"><?php echo e($mission['title']); ?></div>

                <div class="text-muted small">
                  Score: <?php echo (int) $mission['score']; ?> / 10
                </div>

                <span class="status-pill <?php echo e(profile_status_class($mission['status'])); ?>">
                  <?php echo e(profile_status_label($mission['status'])); ?>
                </span>

                <?php if ($mission['status'] === 'completed'): ?>
                  <a class="btn btn-ghost btn-sm" href="mission.php?type=<?php echo e($mission['slug']); ?>">
                    Review
                  </a>
                <?php elseif ($mission['status'] === 'pending'): ?>
                  <a class="btn btn-glow btn-sm" href="mission.php?type=<?php echo e($mission['slug']); ?>">
                    Continue
                  </a>
                <?php else: ?>
                  <button class="btn btn-ghost btn-sm" disabled>
                    Locked
                  </button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-award accent"></i>
            <span class="fw-semibold">Badges</span>
          </div>

          <?php if ($badges): ?>
            <div class="game-grid">
              <?php foreach ($badges as $badge): ?>
                <div class="game-tile">
                  <div class="icon-circle accent">
                    <i class="<?php echo e($badge['icon']); ?>"></i>
                  </div>

                  <div class="fw-semibold"><?php echo e($badge['name']); ?></div>

                  <div class="text-muted small">
                    <?php echo e($badge['description']); ?>
                  </div>

                  <span class="status-pill success">
                    Earned
                  </span>

                  <div class="text-muted small">
                    <?php echo e(date('d M Y', strtotime($badge['earned_at']))); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="result-box text-muted">
              No badges unlocked yet. Complete missions or quizzes to earn rewards.
            </div>
          <?php endif; ?>
        </div>

        <div class="panel mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-certificate accent"></i>
            <span class="fw-semibold">Certificate</span>
          </div>

          <?php if ($certificateUnlocked): ?>
            <div class="panel glass text-center">
              <div class="icon-circle mx-auto mb-3">
                <i class="fa-solid fa-file-lines"></i>
              </div>

              <h4 class="mb-1">Cyber Awareness Completion Certificate</h4>

              <p class="text-muted">
                You have completed all awareness missions. PDF download can be added later.
              </p>

              <a class="btn btn-glow" href="certificate.php">
  Download PDF Certificate
</a>
            </div>
          <?php else: ?>
            <div class="result-box text-muted">
              Complete all missions to unlock the certificate.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="panel mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-chart-line accent"></i>
            <span class="fw-semibold">Quiz History</span>
          </div>

          <?php if ($quizAttempts): ?>
            <div class="d-grid gap-3">
              <?php foreach ($quizAttempts as $attempt): ?>
                <div class="result-box">
                  <div class="fw-semibold">
                    Score:
                    <?php echo (int) $attempt['score']; ?> /
                    <?php echo (int) $attempt['total_questions']; ?>
                  </div>

                  <div class="text-muted small">
                    Correct:
                    <?php echo (int) $attempt['correct_answers']; ?>
                  </div>

                  <div class="text-muted small">
                    Time:
                    <?php echo e(format_profile_seconds((int) $attempt['time_taken_seconds'])); ?>
                  </div>

                  <div class="text-muted small">
                    <?php echo e(date('d M Y, h:i A', strtotime($attempt['completed_at']))); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="result-box text-muted">
              No quiz attempts yet.
            </div>
          <?php endif; ?>
        </div>

        <div class="panel mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-bell accent"></i>
            <span class="fw-semibold">Notifications</span>
          </div>

          <ul class="list-group">
            <?php if ($completedMissions < $totalMissions): ?>
              <li class="list-group-item">
                <strong class="warning">Reminder</strong>
                <p class="mb-1">
                  Complete remaining missions to unlock your certificate.
                </p>
              </li>
            <?php else: ?>
              <li class="list-group-item">
                <strong class="accent">Certificate Ready</strong>
                <p class="mb-1">
                  You have completed all missions.
                </p>
              </li>
            <?php endif; ?>

            <?php if ($totalXp < 40): ?>
              <li class="list-group-item">
                <strong class="accent">XP Tip</strong>
                <p class="mb-1">
                  Take the timed quiz to earn more XP.
                </p>
              </li>
            <?php endif; ?>

            <?php if ($totalBadges === 0): ?>
              <li class="list-group-item">
                <strong class="warning">Reward Tip</strong>
                <p class="mb-1">
                  Complete your first mission to earn a badge.
                </p>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <div class="panel">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-clock-rotate-left accent"></i>
            <span class="fw-semibold">Recent Activity</span>
          </div>

          <?php if ($activities): ?>
            <ul class="list-group">
              <?php foreach ($activities as $activity): ?>
                <li class="list-group-item">
                  <div><?php echo e($activity['action_text']); ?></div>
                  <small class="text-muted">
                    <?php echo e(date('d M Y, h:i A', strtotime($activity['created_at']))); ?>
                  </small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="result-box text-muted">
              No activity yet.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>