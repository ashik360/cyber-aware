<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

define('BASE_URL', '/cyber-aware');

function app_url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function app_redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $statement = $pdo->prepare("
        SELECT id, full_name, email, role, avatar, total_xp, created_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $statement->execute([
        'id' => $_SESSION['user_id'],
    ]);

    $user = $statement->fetch();

    return $user ?: null;
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo);

    if (!$user) {
        app_redirect('login.php');
    }

    return $user;
}

function require_guest(PDO $pdo): void
{
    if (current_user($pdo)) {
        app_redirect('dashboard.php');
    }
}

function require_admin(PDO $pdo): array
{
    $user = require_login($pdo);

    if ($user['role'] !== 'admin') {
        app_redirect('dashboard.php');
    }

    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}