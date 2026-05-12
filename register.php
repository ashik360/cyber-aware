<?php
require_once __DIR__ . '/includes/auth.php';

require_guest($pdo);

$errors = [];
$oldName = '';
$oldEmail = '';

if (is_post_request()) {
    $oldName = trim($_POST['full_name'] ?? '');
    $oldEmail = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($oldName === '' || strlen($oldName) < 2) {
        $errors[] = 'Please enter your full name.';
    }

    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (!$errors) {
        $statement = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $statement->execute(['email' => $oldEmail]);

        if ($statement->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $statement = $pdo->prepare("
                INSERT INTO users (full_name, email, password_hash, role)
                VALUES (:full_name, :email, :password_hash, 'user')
            ");

            $statement->execute([
                'full_name' => $oldName,
                'email' => $oldEmail,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $userId = (int) $pdo->lastInsertId();

            $missionStatement = $pdo->prepare("
                INSERT INTO user_missions (user_id, mission_id, status)
                SELECT
                  :user_id,
                  id,
                  CASE
                    WHEN unlock_order = 1 THEN 'pending'
                    ELSE 'locked'
                  END
                FROM missions
            ");

            $missionStatement->execute([
                'user_id' => $userId,
            ]);

            log_activity($pdo, $userId, 'register', 'Created a new CyberAware account.');

            $pdo->commit();

            login_user([
                'id' => $userId,
            ]);

            redirect('dashboard.php');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register | CyberAware</title>
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
    <a class="icon-button" href="index.php" title="Home">
      <i class="fa-solid fa-arrow-left"></i>
    </a>

    <span class="navbar-brand fw-bold ms-2">
      <i class="fa-solid fa-user-plus accent"></i> Create Account
    </span>

    <a class="btn btn-glow btn-sm" href="login.php">Login</a>
  </nav>

  <main class="container stage">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="panel glass">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="icon-circle">
              <i class="fa-solid fa-shield"></i>
            </div>
            <div>
              <div class="fw-semibold">Join the Training</div>
              <div class="text-muted small">Create your learning profile.</div>
            </div>
          </div>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <?php foreach ($errors as $error): ?>
                <div><?php echo e($error); ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="register.php">
            <div class="mb-3">
              <label class="form-label text-muted small">Full Name</label>
              <input
                type="text"
                name="full_name"
                class="form-control"
                placeholder="Full name"
                value="<?php echo e($oldName); ?>"
                required
              >
            </div>

            <div class="mb-3">
              <label class="form-label text-muted small">Email</label>
              <input
                type="email"
                name="email"
                class="form-control"
                placeholder="you@company.com"
                value="<?php echo e($oldEmail); ?>"
                required
              >
            </div>

            <div class="mb-3">
              <label class="form-label text-muted small">Password</label>
              <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Minimum 8 characters"
                required
              >
            </div>

            <div class="mb-4">
              <label class="form-label text-muted small">Confirm Password</label>
              <input
                type="password"
                name="confirm_password"
                class="form-control"
                placeholder="Repeat password"
                required
              >
            </div>

            <button type="submit" class="btn btn-glow w-100">Register</button>
          </form>

          <div class="text-center mt-3 text-muted small">
            Already have an account?
            <a href="login.php" class="accent">Login</a>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>