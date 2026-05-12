<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int) $user['id'];

$leadersStatement = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.total_xp,
        COUNT(DISTINCT ub.badge_id) AS badge_count,
        COUNT(DISTINCT CASE WHEN um.status = 'completed' THEN um.mission_id END) AS completed_missions
    FROM users u
    LEFT JOIN user_badges ub ON ub.user_id = u.id
    LEFT JOIN user_missions um ON um.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.total_xp DESC, completed_missions DESC, badge_count DESC, u.created_at ASC
    LIMIT 50
");

$leaders = $leadersStatement->fetchAll();

$currentRank = null;
$currentUserRow = null;

foreach ($leaders as $index => $leader) {
    if ((int) $leader['id'] === $userId) {
        $currentRank = $index + 1;
        $currentUserRow = $leader;
        break;
    }
}

if (!$currentRank) {
    $allUsersStatement = $pdo->query("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.total_xp,
            COUNT(DISTINCT ub.badge_id) AS badge_count,
            COUNT(DISTINCT CASE WHEN um.status = 'completed' THEN um.mission_id END) AS completed_missions
        FROM users u
        LEFT JOIN user_badges ub ON ub.user_id = u.id
        LEFT JOIN user_missions um ON um.user_id = u.id
        WHERE u.role = 'user'
        GROUP BY u.id
        ORDER BY u.total_xp DESC, completed_missions DESC, badge_count DESC, u.created_at ASC
    ");

    $allUsers = $allUsersStatement->fetchAll();

    foreach ($allUsers as $index => $row) {
        if ((int) $row['id'] === $userId) {
            $currentRank = $index + 1;
            $currentUserRow = $row;
            break;
        }
    }
}

function leaderboard_rank_label(int $rank): string
{
    return match ($rank) {
        1 => 'Champion',
        2 => 'Elite Defender',
        3 => 'Cyber Guardian',
        default => 'Learner',
    };
}

function leaderboard_rank_icon(int $rank): string
{
    return match ($rank) {
        1 => 'fa-solid fa-crown',
        2 => 'fa-solid fa-medal',
        3 => 'fa-solid fa-award',
        default => 'fa-solid fa-user-shield',
    };
}

function leaderboard_level(int $xp): string
{
    return get_rank_name($xp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Leaderboard | CyberAware</title>
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
      <i class="fa-solid fa-trophy accent"></i> Leaderboard
    </span>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <a class="icon-button" href="learn.php" title="Learn">
        <i class="fa-solid fa-book-open"></i>
      </a>

      <a class="icon-button" href="quiz.php" title="Quiz">
        <i class="fa-solid fa-clipboard-question"></i>
      </a>

      <a class="icon-button" href="profile.php" title="Profile">
        <i class="fa-solid fa-user-astronaut"></i>
      </a>

      <a class="icon-button" href="logout.php" title="Logout">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </nav>

  <main class="container stage">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <span class="status-pill success mb-2">
          <i class="fa-solid fa-ranking-star"></i>
          Live Ranking
        </span>

        <h2 class="mb-1">Cyber Awareness Leaderboard</h2>
        <p class="text-muted mb-0">
          Rankings are based on XP, completed missions, and badge count.
        </p>
      </div>

      <a class="btn btn-glow btn-sm" href="quiz.php">
        Earn More XP
      </a>
    </div>

    <div class="panel glass mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="text-muted small">Your Rank</div>
          <div class="stat-value">
            <?php echo $currentRank ? '#' . (int) $currentRank : 'Not Ranked'; ?>
          </div>
        </div>

        <div>
          <div class="text-muted small">Your XP</div>
          <div class="stat-value">
            <?php echo (int) ($currentUserRow['total_xp'] ?? $user['total_xp']); ?>
          </div>
        </div>

        <div>
          <div class="text-muted small">Your Level</div>
          <div class="stat-value">
            <?php echo e(leaderboard_level((int) ($currentUserRow['total_xp'] ?? $user['total_xp']))); ?>
          </div>
        </div>

        <span class="rank-chip">
          <i class="fa-solid fa-crown"></i>
          Keep Training
        </span>
      </div>
    </div>

    <?php if ($leaders): ?>
      <div class="panel">
        <div class="table-responsive">
          <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th>Rank</th>
                <th>User</th>
                <th>Level</th>
                <th>XP</th>
                <th>Missions</th>
                <th>Badges</th>
                <th>Status</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($leaders as $index => $leader): ?>
                <?php
                  $rank = $index + 1;
                  $isCurrentUser = (int) $leader['id'] === $userId;
                ?>

                <tr>
                  <td>
                    <span class="status-pill <?php echo $rank <= 3 ? 'success' : 'warn'; ?>">
                      #<?php echo $rank; ?>
                    </span>
                  </td>

                  <td>
                    <div class="fw-semibold">
                      <?php echo e($leader['full_name']); ?>
                      <?php if ($isCurrentUser): ?>
                        <span class="accent small">You</span>
                      <?php endif; ?>
                    </div>

                    <div class="text-muted small">
                      <?php echo e($leader['email']); ?>
                    </div>
                  </td>

                  <td><?php echo e(leaderboard_level((int) $leader['total_xp'])); ?></td>

                  <td>
                    <strong><?php echo (int) $leader['total_xp']; ?></strong>
                  </td>

                  <td><?php echo (int) $leader['completed_missions']; ?></td>

                  <td><?php echo (int) $leader['badge_count']; ?></td>

                  <td>
                    <span class="rank-chip">
                      <i class="<?php echo e(leaderboard_rank_icon($rank)); ?>"></i>
                      <?php echo e(leaderboard_rank_label($rank)); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php else: ?>
      <div class="panel text-center">
        <div class="icon-circle mx-auto mb-3">
          <i class="fa-solid fa-users"></i>
        </div>

        <h4>No users ranked yet</h4>

        <p class="text-muted">
          Complete quizzes and missions to appear on the leaderboard.
        </p>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>