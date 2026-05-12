<?php
require_once __DIR__ . '/includes/auth.php';

$user = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CyberAware | Game Space</title>
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

    <?php if ($user): ?>
      <a class="btn btn-glow btn-sm" href="dashboard.php">
        Continue Training
      </a>
    <?php else: ?>
      <a class="btn btn-glow btn-sm" href="login.php">
        Login
      </a>
    <?php endif; ?>
  </nav>

  <main class="container stage">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <span class="status-pill success mb-3">
          <i class="fa-solid fa-bolt"></i> Gamified Cyber Awareness
        </span>

        <h1 class="display-5 fw-bold">Train. Detect. Defend.</h1>

        <p class="text-muted fs-5">
          A focused learning platform where users study cyber safety, complete timed quizzes,
          play awareness missions, earn XP, and track progress.
        </p>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <?php if ($user): ?>
            <a href="dashboard.php" class="btn btn-glow">Open Dashboard</a>
            <a href="logout.php" class="btn btn-ghost">Logout</a>
          <?php else: ?>
            <a href="login.php" class="btn btn-ghost">Login</a>
            <a href="register.php" class="btn btn-glow">Create Account</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="panel glass">
          <div class="hud mb-3">
            <div class="hud-bar">
              <div class="hud-bar-label">
                <span>Learning Focus</span>
                <span>Simple</span>
              </div>
              <div class="progress-track">
                <div class="progress-fill xp-fill" style="width: 90%"></div>
              </div>
            </div>

            <div class="hud-bar">
              <div class="hud-bar-label">
                <span>Security Awareness</span>
                <span>Practical</span>
              </div>
              <div class="progress-track">
                <div class="progress-fill hp-fill" style="width: 84%"></div>
              </div>
            </div>
          </div>

          <div class="game-grid">
            <div class="game-tile">
              <div class="icon-circle">
                <i class="fa-solid fa-book-open"></i>
              </div>
              <div class="fw-semibold">Study Materials</div>
              <span class="status-pill warn">Topic Wise</span>
            </div>

            <div class="game-tile">
              <div class="icon-circle">
                <i class="fa-solid fa-clock"></i>
              </div>
              <div class="fw-semibold">Timed Quiz</div>
              <span class="status-pill warn">Countdown</span>
            </div>

            <div class="game-tile">
              <div class="icon-circle">
                <i class="fa-solid fa-gamepad"></i>
              </div>
              <div class="fw-semibold">Missions</div>
              <span class="status-pill warn">Interactive</span>
            </div>

            <div class="game-tile">
              <div class="icon-circle">
                <i class="fa-solid fa-trophy"></i>
              </div>
              <div class="fw-semibold">Rewards</div>
              <span class="status-pill warn">XP & Badges</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel mt-4">
      <div class="d-flex align-items-center gap-2 mb-3">
        <i class="fa-solid fa-route accent"></i>
        <span class="fw-semibold">Focused Learning Flow</span>
      </div>

      <div class="game-grid">
        <div class="game-tile">
          <div class="icon-circle">
            <i class="fa-solid fa-user-plus"></i>
          </div>
          <div class="fw-semibold">Create Profile</div>
          <span class="status-pill success">Step 1</span>
        </div>

        <div class="game-tile">
          <div class="icon-circle">
            <i class="fa-solid fa-book"></i>
          </div>
          <div class="fw-semibold">Learn Topics</div>
          <span class="status-pill success">Step 2</span>
        </div>

        <div class="game-tile">
          <div class="icon-circle">
            <i class="fa-solid fa-clipboard-question"></i>
          </div>
          <div class="fw-semibold">Take Quiz</div>
          <span class="status-pill success">Step 3</span>
        </div>

        <div class="game-tile">
          <div class="icon-circle">
            <i class="fa-solid fa-award"></i>
          </div>
          <div class="fw-semibold">Earn Rewards</div>
          <span class="status-pill success">Step 4</span>
        </div>
      </div>
    </div>
  </main>
</body>
</html>