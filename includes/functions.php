<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function is_post_request(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function get_rank_name(int $xp): string
{
    if ($xp >= 80) {
        return 'Advanced';
    }

    if ($xp >= 40) {
        return 'Intermediate';
    }

    return 'Beginner';
}

function get_threat_level(int $xp): string
{
    if ($xp >= 60) {
        return 'Low';
    }

    if ($xp >= 30) {
        return 'Medium';
    }

    return 'High';
}

function log_activity(PDO $pdo, ?int $userId, string $type, string $text): void
{
    $statement = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action_type, action_text)
        VALUES (:user_id, :action_type, :action_text)
    ");

    $statement->execute([
        'user_id' => $userId,
        'action_type' => $type,
        'action_text' => $text,
    ]);
}