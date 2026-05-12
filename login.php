<?php
require_once __DIR__ . '/includes/auth.php';

require_guest($pdo);

$errors = [];
$oldEmail = '';

if (is_post_request()) {
    $oldEmail = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (!$errors) {
        $statement = $pdo->prepare("
            SELECT id, full_name, email, password_hash, role
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $statement->execute([
            'email' => $oldEmail,
        ]);

        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            log_activity($pdo, (int) $user['id'], 'login', 'Logged in to CyberAware.');
            redirect('dashboard.php');
        }

        $errors[] = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | CyberAware</title>
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
      <i class="fa-solid fa-user-shield accent"></i> Login
    </span>

    <a class="btn btn-glow btn-sm" href="register.php">Register</a>
  </nav>

  <main class="container stage">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="panel glass">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="icon-circle">
              <i class="fa-solid fa-fingerprint"></i>
            </div>
            <div>
              <div class="fw-semibold">Secure Access</div>
              <div class="text-muted small">Enter your account details.</div>
            </div>
          </div>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <?php foreach ($errors as $error): ?>
                <div><?php echo e($error); ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="POST" action="login.php">
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

            <div class="mb-4">
              <label class="form-label text-muted small">Password</label>
              <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Your password"
                required
              >
            </div>

            <button type="submit" class="btn btn-glow w-100">Login</button>
          </form>

          <div class="text-center mt-3 text-muted small">
            New user?
            <a href="register.php" class="accent">Register here</a>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>