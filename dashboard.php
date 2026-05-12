<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int) $user['id'];

/*
|--------------------------------------------------------------------------
| Make sure every user has all missions assigned
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
| Unlock the next available mission based on completed mission count
|--------------------------------------------------------------------------
*/
$unlockMissions = $pdo->prepare("
    UPDATE user_missions um
    INNER JOIN missions m ON m.id = um.mission_id
    SET um.status = 'pending'
    WHERE um.user_id = :user_id
    AND um.status = 'locked'
    AND m.unlock_order <= (
        SELECT completed_count + 1
        FROM (
            SELECT COUNT(*) AS completed_count
            FROM user_missions
            WHERE user_id = :user_id_2
            AND status = 'completed'
        ) AS completed
    )
");

$unlockMissions->execute([
    'user_id' => $userId,
    'user_id_2' => $userId,
]);

/*
|--------------------------------------------------------------------------
| Refresh user data
|--------------------------------------------------------------------------
*/
$user = current_user($pdo);
$totalXp = (int) ($user['total_xp'] ?? 0);
$rankName = get_rank_name($totalXp);
$threatLevel = get_threat_level($totalXp);

/*
|--------------------------------------------------------------------------
| Dashboard stats
|--------------------------------------------------------------------------
*/
$missionStatsStatement = $pdo->prepare("
    SELECT
        COUNT(*) AS total_missions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_missions
    FROM user_missions
    WHERE user_id = :user_id
");

$missionStatsStatement->execute([
    'user_id' => $userId,
]);

$missionStats = $missionStatsStatement->fetch();
$totalMissions = (int) ($missionStats['total_missions'] ?? 0);
$completedMissions = (int) ($missionStats['completed_missions'] ?? 0);
$progressPercent = $totalMissions > 0
    ? (int) round(($completedMissions / $totalMissions) * 100)
    : 0;

$badgeStatement = $pdo->prepare("
    SELECT COUNT(*) AS total_badges
    FROM user_badges
    WHERE user_id = :user_id
");

$badgeStatement->execute([
    'user_id' => $userId,
]);

$totalBadges = (int) ($badgeStatement->fetch()['total_badges'] ?? 0);

$quizStatement = $pdo->prepare("
    SELECT score, total_questions, correct_answers, completed_at
    FROM quiz_attempts
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 1
");

$quizStatement->execute([
    'user_id' => $userId,
]);

$lastQuiz = $quizStatement->fetch();

$lessonStatement = $pdo->query("
    SELECT COUNT(*) AS total_lessons
    FROM lessons
    WHERE is_published = 1
");

$totalLessons = (int) ($lessonStatement->fetch()['total_lessons'] ?? 0);

/*
|--------------------------------------------------------------------------
| Missions
|--------------------------------------------------------------------------
*/
$missionsStatement = $pdo->prepare("
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
    WHERE um.user_id = :user_id
    ORDER BY m.unlock_order ASC
");

$missionsStatement->execute([
    'user_id' => $userId,
]);

$missions = $missionsStatement->fetchAll();

$nextMission = null;

foreach ($missions as $mission) {
    if ($mission['status'] === 'pending') {
        $nextMission = $mission;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| Recent activity
|--------------------------------------------------------------------------
*/
$activityStatement = $pdo->prepare("
    SELECT action_text, created_at
    FROM activity_logs
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT 5
");

$activityStatement->execute([
    'user_id' => $userId,
]);

$activities = $activityStatement->fetchAll();

function mission_icon(string $slug): string
{
    return match ($slug) {
        'phishing' => 'fa-solid fa-envelope-open-text',
        'password' => 'fa-solid fa-key',
        'malware' => 'fa-solid fa-bug',
        'social' => 'fa-solid fa-user-secret',
        default => 'fa-solid fa-shield-halved',
    };
}

function status_class(string $status): string
{
    return match ($status) {
        'completed' => 'success',
        'locked' => 'locked',
        default => 'warn',
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'completed' => 'Completed',
        'locked' => 'Locked',
        default => 'Pending',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | CyberAware</title>
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
    <span class="navbar-brand fw-bold">
      <i class="fa-solid fa-shield-halved accent"></i> CyberAware
    </span>

    <div class="d-flex align-items-center gap-2">
      <?php if ($user['role'] === 'admin'): ?>
        <a class="icon-button" href="admin/index.php" title="Admin">
          <i class="fa-solid fa-screwdriver-wrench"></i>
        </a>
      <?php endif; ?>

      <a class="icon-button" href="profile.php" title="Profile">
        <i class="fa-solid fa-user-astronaut"></i>
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
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <span class="status-pill success mb-2">
          <i class="fa-solid fa-user-shield"></i>
          Welcome, <?php echo e($user['full_name']); ?>
        </span>

        <h2 class="mb-1">Control Hub</h2>
        <p class="text-muted mb-0">
          Learn, play missions, take timed quizzes, and build your cyber awareness score.
        </p>
      </div>

      <div class="panel glass d-flex align-items-center gap-3">
        <div>
          <div class="text-muted small">Next Action</div>
          <div class="fw-semibold">
            <?php echo $nextMission ? e($nextMission['title']) : 'All missions cleared'; ?>
          </div>
        </div>

        <?php if ($nextMission): ?>
          <a class="btn btn-glow btn-sm" href="mission.php?type=<?php echo e($nextMission['slug']); ?>">
            Launch
          </a>
        <?php else: ?>
          <a class="btn btn-glow btn-sm" href="leaderboard.php">
            View Rank
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="hud mt-4">
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
          <i class="fa-solid fa-layer-group"></i>
        </div>
        <div>
          <div class="stat-value"><?php echo e($rankName); ?></div>
          <div class="stat-label">Rank</div>
        </div>
      </div>

      <div class="stat-tile">
        <div class="stat-icon">
          <i class="fa-solid fa-check-circle"></i>
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

    <div class="panel mt-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small">Overall Progress</span>
        <span class="text-muted small"><?php echo $progressPercent; ?>%</span>
      </div>

      <div class="progress-track">
        <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%"></div>
      </div>
    </div>

    <div class="game-grid mt-4">
      <a class="game-tile text-decoration-none" href="learn.php">
        <div class="icon-circle">
          <i class="fa-solid fa-book-open"></i>
        </div>
        <div class="fw-semibold">Learn</div>
        <div class="text-muted small">
          <?php echo $totalLessons; ?> available lessons, articles, and materials.
        </div>
        <span class="status-pill warn">Study</span>
      </a>

      <a class="game-tile text-decoration-none" href="quiz.php">
        <div class="icon-circle">
          <i class="fa-solid fa-clipboard-question"></i>
        </div>
        <div class="fw-semibold">Timed Quiz</div>
        <div class="text-muted small">
          <?php if ($lastQuiz): ?>
            Last score: <?php echo (int) $lastQuiz['score']; ?> / <?php echo (int) $lastQuiz['total_questions']; ?>
          <?php else: ?>
            Test your awareness with a countdown.
          <?php endif; ?>
        </div>
        <span class="status-pill warn">Assessment</span>
      </a>

      <a class="game-tile text-decoration-none" href="profile.php">
        <div class="icon-circle">
          <i class="fa-solid fa-user-astronaut"></i>
        </div>
        <div class="fw-semibold">Profile & Rewards</div>
        <div class="text-muted small">
          <?php echo $totalBadges; ?> badges unlocked.
        </div>
        <span class="status-pill success">Rewards</span>
      </a>

      <a class="game-tile text-decoration-none" href="leaderboard.php">
        <div class="icon-circle">
          <i class="fa-solid fa-ranking-star"></i>
        </div>
        <div class="fw-semibold">Leaderboard</div>
        <div class="text-muted small">
          Compare score with other learners.
        </div>
        <span class="status-pill warn">Rank</span>
      </a>
    </div>

    <div class="panel mt-4">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-gamepad accent"></i>
        <span class="fw-semibold">Awareness Missions</span>
      </div>

      <div class="game-grid">
        <?php foreach ($missions as $mission): ?>
          <?php
            $status = $mission['status'];
            $isLocked = $status === 'locked';
            $missionUrl = $isLocked
                ? '#'
                : 'mission.php?type=' . urlencode($mission['slug']);
          ?>

          <div class="game-tile">
            <div class="icon-circle">
              <i class="<?php echo e(mission_icon($mission['slug'])); ?>"></i>
            </div>

            <div class="fw-semibold"><?php echo e($mission['title']); ?></div>

            <div class="text-muted small">
              <?php echo e($mission['description']); ?>
            </div>

            <span class="status-pill <?php echo e(status_class($status)); ?>">
              <?php echo e(status_label($status)); ?>
            </span>

            <?php if ($status === 'completed'): ?>
              <a class="btn btn-ghost btn-sm" href="<?php echo e($missionUrl); ?>">
                Review
              </a>
            <?php elseif ($isLocked): ?>
              <button class="btn btn-ghost btn-sm" disabled>
                Locked
              </button>
            <?php else: ?>
              <a class="btn btn-glow btn-sm" href="<?php echo e($missionUrl); ?>">
                Start
              </a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="row g-4 mt-1">
      <div class="col-lg-6">
        <div class="panel h-100">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-bell accent"></i>
            <span class="fw-semibold">Focus Reminders</span>
          </div>

          <ul class="list-group">
            <li class="list-group-item">
              Complete lessons before taking the quiz for better score.
            </li>
            <li class="list-group-item">
              Timed quizzes will save your score in the database.
            </li>
            <li class="list-group-item">
              Missions unlock one by one after completion.
            </li>
          </ul>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="panel h-100">
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
              No activity yet. Start your first lesson or mission.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>