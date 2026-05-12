<?php
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin($pdo);

$allowedTabs = ['overview', 'users', 'lessons', 'quiz', 'materials', 'articles'];
$tab = $_GET['tab'] ?? 'overview';

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

function go_admin(string $tab): void
{
    redirect('index.php?tab=' . urlencode($tab));
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/*
|--------------------------------------------------------------------------
| Handle Admin Actions
|--------------------------------------------------------------------------
*/
if (is_post_request()) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_user_role') {
            $targetUserId = (int) ($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? 'user';

            if (!in_array($role, ['user', 'admin'], true)) {
                throw new RuntimeException('Invalid role selected.');
            }

            $statement = $pdo->prepare("
                UPDATE users
                SET role = :role
                WHERE id = :id
            ");

            $statement->execute([
                'role' => $role,
                'id' => $targetUserId,
            ]);

            set_flash('success', 'User role updated successfully.');
            go_admin('users');
        }

        if ($action === 'delete_user') {
            $targetUserId = (int) ($_POST['user_id'] ?? 0);

            if ($targetUserId === (int) $admin['id']) {
                throw new RuntimeException('You cannot delete your own admin account.');
            }

            $statement = $pdo->prepare("
                DELETE FROM users
                WHERE id = :id
            ");

            $statement->execute([
                'id' => $targetUserId,
            ]);

            set_flash('success', 'User deleted successfully.');
            go_admin('users');
        }

        if ($action === 'add_lesson') {
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $estimatedMinutes = max(1, (int) ($_POST['estimated_minutes'] ?? 5));
            $isPublished = isset($_POST['is_published']) ? 1 : 0;

            if ($topicId <= 0 || $title === '' || $body === '') {
                throw new RuntimeException('Please fill all required lesson fields.');
            }

            $statement = $pdo->prepare("
                INSERT INTO lessons
                  (topic_id, title, body, estimated_minutes, is_published)
                VALUES
                  (:topic_id, :title, :body, :estimated_minutes, :is_published)
            ");

            $statement->execute([
                'topic_id' => $topicId,
                'title' => $title,
                'body' => $body,
                'estimated_minutes' => $estimatedMinutes,
                'is_published' => $isPublished,
            ]);

            log_activity($pdo, (int) $admin['id'], 'admin', 'Added lesson: ' . $title);

            set_flash('success', 'Lesson added successfully.');
            go_admin('lessons');
        }

        if ($action === 'delete_lesson') {
            $lessonId = (int) ($_POST['lesson_id'] ?? 0);

            $statement = $pdo->prepare("
                DELETE FROM lessons
                WHERE id = :id
            ");

            $statement->execute([
                'id' => $lessonId,
            ]);

            set_flash('success', 'Lesson deleted successfully.');
            go_admin('lessons');
        }

        if ($action === 'add_question') {
            $topicId = (int) ($_POST['topic_id'] ?? 0);
            $questionText = trim($_POST['question_text'] ?? '');
            $difficulty = $_POST['difficulty'] ?? 'Easy';
            $options = $_POST['options'] ?? [];
            $correctIndex = (int) ($_POST['correct_option'] ?? -1);

            if (!in_array($difficulty, ['Easy', 'Medium', 'Hard'], true)) {
                $difficulty = 'Easy';
            }

            if ($topicId <= 0 || $questionText === '') {
                throw new RuntimeException('Please enter a topic and question.');
            }

            if (!is_array($options) || count($options) < 4) {
                throw new RuntimeException('Please provide 4 options.');
            }

            $cleanOptions = [];

            foreach ($options as $option) {
                $cleanOptions[] = trim((string) $option);
            }

            foreach ($cleanOptions as $option) {
                if ($option === '') {
                    throw new RuntimeException('All 4 options are required.');
                }
            }

            if ($correctIndex < 0 || $correctIndex > 3) {
                throw new RuntimeException('Please select the correct option.');
            }

            $pdo->beginTransaction();

            $questionStatement = $pdo->prepare("
                INSERT INTO questions
                  (topic_id, question_text, difficulty, points, is_active)
                VALUES
                  (:topic_id, :question_text, :difficulty, 1, 1)
            ");

            $questionStatement->execute([
                'topic_id' => $topicId,
                'question_text' => $questionText,
                'difficulty' => $difficulty,
            ]);

            $questionId = (int) $pdo->lastInsertId();

            $optionStatement = $pdo->prepare("
                INSERT INTO question_options
                  (question_id, option_text, is_correct)
                VALUES
                  (:question_id, :option_text, :is_correct)
            ");

            foreach ($cleanOptions as $index => $optionText) {
                $optionStatement->execute([
                    'question_id' => $questionId,
                    'option_text' => $optionText,
                    'is_correct' => $index === $correctIndex ? 1 : 0,
                ]);
            }

            $pdo->commit();

            log_activity($pdo, (int) $admin['id'], 'admin', 'Added a quiz question.');

            set_flash('success', 'Quiz question added successfully.');
            go_admin('quiz');
        }

        if ($action === 'delete_question') {
            $questionId = (int) ($_POST['question_id'] ?? 0);

            $statement = $pdo->prepare("
                DELETE FROM questions
                WHERE id = :id
            ");

            $statement->execute([
                'id' => $questionId,
            ]);

            set_flash('success', 'Question deleted successfully.');
            go_admin('quiz');
        }

        if ($action === 'add_material') {
    $topicIdRaw = $_POST['topic_id'] ?? '';
    $topicId = $topicIdRaw === '' ? null : (int) $topicIdRaw;
    $title = trim($_POST['title'] ?? '');
    $materialType = $_POST['material_type'] ?? 'Article';
    $externalUrl = trim($_POST['external_url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $filePath = null;

    if (!in_array($materialType, ['Article', 'PDF', 'External Link', 'Video'], true)) {
        $materialType = 'Article';
    }

    if ($title === '') {
        throw new RuntimeException('Material title is required.');
    }

    if (!empty($_FILES['file_upload']['name'])) {
        $upload = $_FILES['file_upload'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        $maxSize = 10 * 1024 * 1024;

        if ($upload['size'] > $maxSize) {
            throw new RuntimeException('File size must be under 10MB.');
        }

        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            throw new RuntimeException('Only PDF files are allowed for now.');
        }

        $safeName = 'material_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $uploadDir = __DIR__ . '/../assets/uploads/materials/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $destination = $uploadDir . $safeName;

        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('Could not save uploaded PDF.');
        }

        $filePath = 'assets/uploads/materials/' . $safeName;
        $materialType = 'PDF';
    }

    $statement = $pdo->prepare("
        INSERT INTO study_materials
          (topic_id, title, material_type, file_path, external_url, description)
        VALUES
          (:topic_id, :title, :material_type, :file_path, :external_url, :description)
    ");

    $statement->execute([
        'topic_id' => $topicId,
        'title' => $title,
        'material_type' => $materialType,
        'file_path' => $filePath,
        'external_url' => $externalUrl ?: null,
        'description' => $description ?: null,
    ]);

    set_flash('success', 'Study material added successfully.');
    go_admin('materials');
}

        if ($action === 'delete_material') {
            $materialId = (int) ($_POST['material_id'] ?? 0);

            $statement = $pdo->prepare("
                DELETE FROM study_materials
                WHERE id = :id
            ");

            $statement->execute([
                'id' => $materialId,
            ]);

            set_flash('success', 'Study material deleted successfully.');
            go_admin('materials');
        }

        if ($action === 'add_article') {
            $title = trim($_POST['title'] ?? '');
            $source = trim($_POST['source'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $summary = trim($_POST['summary'] ?? '');
            $publishedAt = trim($_POST['published_at'] ?? '');

            if ($title === '') {
                throw new RuntimeException('Article title is required.');
            }

            $statement = $pdo->prepare("
                INSERT INTO articles
                  (title, source, url, summary, published_at)
                VALUES
                  (:title, :source, :url, :summary, :published_at)
            ");

            $statement->execute([
                'title' => $title,
                'source' => $source ?: null,
                'url' => $url ?: null,
                'summary' => $summary ?: null,
                'published_at' => $publishedAt ?: null,
            ]);

            set_flash('success', 'Article added successfully.');
            go_admin('articles');
        }

        if ($action === 'delete_article') {
            $articleId = (int) ($_POST['article_id'] ?? 0);

            $statement = $pdo->prepare("
                DELETE FROM articles
                WHERE id = :id
            ");

            $statement->execute([
                'id' => $articleId,
            ]);

            set_flash('success', 'Article deleted successfully.');
            go_admin('articles');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        set_flash('error', $exception->getMessage());
        go_admin($tab);
    }
}

/*
|--------------------------------------------------------------------------
| Load Data
|--------------------------------------------------------------------------
*/
$topics = $pdo->query("
    SELECT id, title
    FROM topics
    ORDER BY sort_order ASC, title ASC
")->fetchAll();

$stats = [
    'users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'lessons' => (int) $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn(),
    'questions' => (int) $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
    'attempts' => (int) $pdo->query("SELECT COUNT(*) FROM quiz_attempts")->fetchColumn(),
];

$avgScore = $pdo->query("
    SELECT AVG(score) AS avg_score
    FROM quiz_attempts
")->fetch();

$averageScore = $avgScore && $avgScore['avg_score'] !== null
    ? round((float) $avgScore['avg_score'], 1)
    : 0;

$users = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.role,
        u.total_xp,
        u.created_at,
        COUNT(DISTINCT ub.badge_id) AS badge_count,
        COUNT(DISTINCT CASE WHEN um.status = 'completed' THEN um.mission_id END) AS completed_missions
    FROM users u
    LEFT JOIN user_badges ub ON ub.user_id = u.id
    LEFT JOIN user_missions um ON um.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
")->fetchAll();

$lessons = $pdo->query("
    SELECT
        l.id,
        l.title,
        l.estimated_minutes,
        l.is_published,
        l.created_at,
        t.title AS topic_title
    FROM lessons l
    INNER JOIN topics t ON t.id = l.topic_id
    ORDER BY l.id DESC
    LIMIT 80
")->fetchAll();

$questions = $pdo->query("
    SELECT
        q.id,
        q.question_text,
        q.difficulty,
        q.is_active,
        t.title AS topic_title,
        COUNT(qo.id) AS option_count
    FROM questions q
    LEFT JOIN topics t ON t.id = q.topic_id
    LEFT JOIN question_options qo ON qo.question_id = q.id
    GROUP BY q.id
    ORDER BY q.id DESC
    LIMIT 80
")->fetchAll();

$materials = $pdo->query("
    SELECT
        sm.id,
        sm.title,
        sm.material_type,
        sm.file_path,
        sm.external_url,
        sm.created_at,
        t.title AS topic_title
    FROM study_materials sm
    LEFT JOIN topics t ON t.id = sm.topic_id
    ORDER BY sm.id DESC
    LIMIT 80
")->fetchAll();

$articles = $pdo->query("
    SELECT
        id,
        title,
        source,
        url,
        published_at,
        created_at
    FROM articles
    ORDER BY id DESC
    LIMIT 80
")->fetchAll();

$recentAttempts = $pdo->query("
    SELECT
        qa.score,
        qa.total_questions,
        qa.correct_answers,
        qa.completed_at,
        u.full_name
    FROM quiz_attempts qa
    INNER JOIN users u ON u.id = qa.user_id
    ORDER BY qa.id DESC
    LIMIT 8
")->fetchAll();

function admin_tab_url(string $tab): string
{
    return 'index.php?tab=' . urlencode($tab);
}

function admin_tab_class(string $current, string $target): string
{
    return $current === $target ? 'btn btn-glow btn-sm' : 'btn btn-ghost btn-sm';
}

function admin_status_pill(bool $active): string
{
    return $active
        ? '<span class="status-pill success">Published</span>'
        : '<span class="status-pill warn">Draft</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin | CyberAware</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
  >
  <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body class="scanlines">
  <nav class="navbar topbar px-3">
    <a class="icon-button" href="../dashboard.php" title="User Hub">
      <i class="fa-solid fa-house"></i>
    </a>

    <span class="navbar-brand fw-bold ms-2">
      <i class="fa-solid fa-screwdriver-wrench accent"></i> Admin Control
    </span>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <a class="icon-button" href="../profile.php" title="Profile">
        <i class="fa-solid fa-user-astronaut"></i>
      </a>

      <a class="icon-button" href="../logout.php" title="Logout">
        <i class="fa-solid fa-right-from-bracket"></i>
      </a>
    </div>
  </nav>

  <main class="container stage">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
      <div>
        <span class="status-pill success mb-2">
          <i class="fa-solid fa-user-shield"></i>
          Admin Mode
        </span>

        <h2 class="mb-1">CyberAware Admin Panel</h2>
        <p class="text-muted mb-0">
          Manage users, lessons, quiz questions, materials, articles, and analytics.
        </p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <?php echo e($success); ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <?php echo e($error); ?>
      </div>
    <?php endif; ?>

    <div class="panel mb-4">
      <div class="d-flex flex-wrap gap-2">
        <a class="<?php echo e(admin_tab_class($tab, 'overview')); ?>" href="<?php echo e(admin_tab_url('overview')); ?>">
          Overview
        </a>

        <a class="<?php echo e(admin_tab_class($tab, 'users')); ?>" href="<?php echo e(admin_tab_url('users')); ?>">
          Users
        </a>

        <a class="<?php echo e(admin_tab_class($tab, 'lessons')); ?>" href="<?php echo e(admin_tab_url('lessons')); ?>">
          Lessons
        </a>

        <a class="<?php echo e(admin_tab_class($tab, 'quiz')); ?>" href="<?php echo e(admin_tab_url('quiz')); ?>">
          Quiz
        </a>

        <a class="<?php echo e(admin_tab_class($tab, 'materials')); ?>" href="<?php echo e(admin_tab_url('materials')); ?>">
          Materials
        </a>

        <a class="<?php echo e(admin_tab_class($tab, 'articles')); ?>" href="<?php echo e(admin_tab_url('articles')); ?>">
          Articles
        </a>
      </div>
    </div>

    <?php if ($tab === 'overview'): ?>
      <div class="hud mb-4">
        <div class="stat-tile">
          <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
          </div>
          <div>
            <div class="stat-value"><?php echo $stats['users']; ?></div>
            <div class="stat-label">Learners</div>
          </div>
        </div>

        <div class="stat-tile">
          <div class="stat-icon">
            <i class="fa-solid fa-book"></i>
          </div>
          <div>
            <div class="stat-value"><?php echo $stats['lessons']; ?></div>
            <div class="stat-label">Lessons</div>
          </div>
        </div>

        <div class="stat-tile">
          <div class="stat-icon">
            <i class="fa-solid fa-clipboard-question"></i>
          </div>
          <div>
            <div class="stat-value"><?php echo $stats['questions']; ?></div>
            <div class="stat-label">Questions</div>
          </div>
        </div>

        <div class="stat-tile">
          <div class="stat-icon">
            <i class="fa-solid fa-chart-line"></i>
          </div>
          <div>
            <div class="stat-value"><?php echo $averageScore; ?></div>
            <div class="stat-label">Avg Quiz Score</div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-6">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-clock-rotate-left accent"></i>
              <span class="fw-semibold">Recent Quiz Attempts</span>
            </div>

            <?php if ($recentAttempts): ?>
              <ul class="list-group">
                <?php foreach ($recentAttempts as $attempt): ?>
                  <li class="list-group-item">
                    <div class="fw-semibold"><?php echo e($attempt['full_name']); ?></div>
                    <div class="text-muted small">
                      Score:
                      <?php echo (int) $attempt['score']; ?> /
                      <?php echo (int) $attempt['total_questions']; ?>
                      |
                      Correct:
                      <?php echo (int) $attempt['correct_answers']; ?>
                    </div>
                    <small class="text-muted">
                      <?php echo e(date('d M Y, h:i A', strtotime($attempt['completed_at']))); ?>
                    </small>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <div class="result-box text-muted">
                No quiz attempts yet.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-bullseye accent"></i>
              <span class="fw-semibold">Admin Focus</span>
            </div>

            <ul class="list-group">
              <li class="list-group-item">Add topic-wise cyber security lessons.</li>
              <li class="list-group-item">Keep at least 50 quiz questions active.</li>
              <li class="list-group-item">Add open-source PDF or external study links.</li>
              <li class="list-group-item">Review leaderboard and user progress.</li>
            </ul>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'users'): ?>
      <div class="panel">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i class="fa-solid fa-user-gear accent"></i>
          <span class="fw-semibold">Manage Users</span>
        </div>

        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>User</th>
                <th>Role</th>
                <th>XP</th>
                <th>Missions</th>
                <th>Badges</th>
                <th>Joined</th>
                <th>Action</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($users as $row): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo e($row['full_name']); ?></div>
                    <div class="text-muted small"><?php echo e($row['email']); ?></div>
                  </td>

                  <td>
                    <form method="POST" class="d-flex gap-2">
                      <input type="hidden" name="action" value="update_user_role">
                      <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">

                      <select name="role" class="form-control form-control-sm">
                        <option value="user" <?php echo $row['role'] === 'user' ? 'selected' : ''; ?>>user</option>
                        <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                      </select>

                      <button class="btn btn-glow btn-sm" type="submit">
                        Save
                      </button>
                    </form>
                  </td>

                  <td><?php echo (int) $row['total_xp']; ?></td>
                  <td><?php echo (int) $row['completed_missions']; ?></td>
                  <td><?php echo (int) $row['badge_count']; ?></td>
                  <td><?php echo e(date('d M Y', strtotime($row['created_at']))); ?></td>

                  <td>
                    <?php if ((int) $row['id'] !== (int) $admin['id']): ?>
                      <form method="POST" onsubmit="return confirm('Delete this user?');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                        <button class="btn btn-ghost btn-sm" type="submit">
                          Delete
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="status-pill success">You</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'lessons'): ?>
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-plus accent"></i>
              <span class="fw-semibold">Add Lesson</span>
            </div>

            <form method="POST">
              <input type="hidden" name="action" value="add_lesson">

              <div class="mb-3">
                <label class="form-label text-muted small">Topic</label>
                <select name="topic_id" class="form-control" required>
                  <?php foreach ($topics as $topic): ?>
                    <option value="<?php echo (int) $topic['id']; ?>">
                      <?php echo e($topic['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Lesson Title</label>
                <input type="text" name="title" class="form-control" required>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Lesson Body</label>
                <textarea name="body" rows="7" class="form-control" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Estimated Minutes</label>
                <input type="number" name="estimated_minutes" class="form-control" value="5" min="1">
              </div>

              <div class="form-check mb-3">
                <input type="checkbox" name="is_published" class="form-check-input" id="isPublished" checked>
                <label class="form-check-label" for="isPublished">
                  Publish now
                </label>
              </div>

              <button class="btn btn-glow w-100" type="submit">
                Add Lesson
              </button>
            </form>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-book-open accent"></i>
              <span class="fw-semibold">Lesson Library</span>
            </div>

            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Lesson</th>
                    <th>Topic</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>

                <tbody>
                  <?php foreach ($lessons as $lesson): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?php echo e($lesson['title']); ?></div>
                        <div class="text-muted small">
                          <?php echo (int) $lesson['estimated_minutes']; ?> min
                        </div>
                      </td>

                      <td><?php echo e($lesson['topic_title']); ?></td>

                      <td>
                        <?php echo admin_status_pill((bool) $lesson['is_published']); ?>
                      </td>

                      <td>
                        <form method="POST" onsubmit="return confirm('Delete this lesson?');">
                          <input type="hidden" name="action" value="delete_lesson">
                          <input type="hidden" name="lesson_id" value="<?php echo (int) $lesson['id']; ?>">
                          <button class="btn btn-ghost btn-sm" type="submit">
                            Delete
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'quiz'): ?>
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-plus accent"></i>
              <span class="fw-semibold">Add Quiz Question</span>
            </div>

            <form method="POST">
              <input type="hidden" name="action" value="add_question">

              <div class="mb-3">
                <label class="form-label text-muted small">Topic</label>
                <select name="topic_id" class="form-control" required>
                  <?php foreach ($topics as $topic): ?>
                    <option value="<?php echo (int) $topic['id']; ?>">
                      <?php echo e($topic['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Difficulty</label>
                <select name="difficulty" class="form-control">
                  <option value="Easy">Easy</option>
                  <option value="Medium">Medium</option>
                  <option value="Hard">Hard</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Question</label>
                <textarea name="question_text" rows="4" class="form-control" required></textarea>
              </div>

              <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="mb-2">
                  <label class="form-label text-muted small">
                    Option <?php echo $i + 1; ?>
                  </label>

                  <div class="d-flex gap-2">
                    <input type="text" name="options[]" class="form-control" required>

                    <label class="status-pill warn">
                      <input type="radio" name="correct_option" value="<?php echo $i; ?>" required>
                      Correct
                    </label>
                  </div>
                </div>
              <?php endfor; ?>

              <button class="btn btn-glow w-100 mt-3" type="submit">
                Add Question
              </button>
            </form>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-clipboard-list accent"></i>
              <span class="fw-semibold">Quiz Bank</span>
            </div>

            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Question</th>
                    <th>Topic</th>
                    <th>Difficulty</th>
                    <th>Options</th>
                    <th>Action</th>
                  </tr>
                </thead>

                <tbody>
                  <?php foreach ($questions as $question): ?>
                    <tr>
                      <td style="min-width: 260px;">
                        <?php echo e(mb_substr($question['question_text'], 0, 100)); ?>
                      </td>

                      <td><?php echo e($question['topic_title'] ?? 'No Topic'); ?></td>
                      <td><?php echo e($question['difficulty']); ?></td>
                      <td><?php echo (int) $question['option_count']; ?></td>

                      <td>
                        <form method="POST" onsubmit="return confirm('Delete this question?');">
                          <input type="hidden" name="action" value="delete_question">
                          <input type="hidden" name="question_id" value="<?php echo (int) $question['id']; ?>">
                          <button class="btn btn-ghost btn-sm" type="submit">
                            Delete
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'materials'): ?>
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-plus accent"></i>
              <span class="fw-semibold">Add Study Material</span>
            </div>

            <form method="POST" enctype="multipart/form-data">
              <input type="hidden" name="action" value="add_material">

              <div class="mb-3">
                <label class="form-label text-muted small">Topic</label>
                <select name="topic_id" class="form-control">
                  <option value="">General</option>
                  <?php foreach ($topics as $topic): ?>
                    <option value="<?php echo (int) $topic['id']; ?>">
                      <?php echo e($topic['title']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Title</label>
                <input type="text" name="title" class="form-control" required>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Material Type</label>
                <select name="material_type" class="form-control">
                  <option value="Article">Article</option>
                  <option value="PDF">PDF</option>
                  <option value="External Link">External Link</option>
                  <option value="Video">Video</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">External URL or PDF Link</label>
                <input type="url" name="external_url" class="form-control" placeholder="https://example.com/file.pdf">
                    <div class="mt-3">
  <label class="form-label text-muted small">Upload PDF</label>
  <input type="file" name="file_upload" class="form-control" accept="application/pdf">
  <div class="text-muted small mt-1">
    Upload PDF only. Maximum size: 10MB.
  </div>
</div>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Description</label>
                <textarea name="description" rows="4" class="form-control"></textarea>
              </div>

              <button class="btn btn-glow w-100" type="submit">
                Add Material
              </button>
            </form>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-folder-open accent"></i>
              <span class="fw-semibold">Study Materials</span>
            </div>

            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Topic</th>
                    <th>Type</th>
                    <th>Action</th>
                  </tr>
                </thead>

                <tbody>
                  <?php foreach ($materials as $material): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?php echo e($material['title']); ?></div>
                        <?php if ($material['file_path']): ?>
  <a class="accent small" href="../<?php echo e($material['file_path']); ?>" target="_blank" rel="noopener">
    View PDF
  </a>
<?php elseif ($material['external_url']): ?>
  <a class="accent small" href="<?php echo e($material['external_url']); ?>" target="_blank" rel="noopener">
    Open Link
  </a>
<?php endif; ?>
                      </td>

                      <td><?php echo e($material['topic_title'] ?? 'General'); ?></td>
                      <td><?php echo e($material['material_type']); ?></td>

                      <td>
                        <form method="POST" onsubmit="return confirm('Delete this material?');">
                          <input type="hidden" name="action" value="delete_material">
                          <input type="hidden" name="material_id" value="<?php echo (int) $material['id']; ?>">
                          <button class="btn btn-ghost btn-sm" type="submit">
                            Delete
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tab === 'articles'): ?>
      <div class="row g-4">
        <div class="col-lg-5">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-plus accent"></i>
              <a class="btn btn-ghost btn-sm mb-3" href="../fetch_cyber_news.php">
  <i class="fa-solid fa-rotate"></i>
  Fetch Latest CISA KEV Updates
</a>
            </div>

            <form method="POST">
              <input type="hidden" name="action" value="add_article">

              <div class="mb-3">
                <label class="form-label text-muted small">Title</label>
                <input type="text" name="title" class="form-control" required>
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Source</label>
                <input type="text" name="source" class="form-control" placeholder="Example: CISA, BleepingComputer">
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">URL</label>
                <input type="url" name="url" class="form-control" placeholder="https://example.com/article">
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Published Date</label>
                <input type="datetime-local" name="published_at" class="form-control">
              </div>

              <div class="mb-3">
                <label class="form-label text-muted small">Summary</label>
                <textarea name="summary" rows="5" class="form-control"></textarea>
              </div>

              <button class="btn btn-glow w-100" type="submit">
                Add Article
              </button>
            </form>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="panel h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-newspaper accent"></i>
              <span class="fw-semibold">Article Library</span>
            </div>

            <div class="table-responsive">
              <table class="table table-dark table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Source</th>
                    <th>Published</th>
                    <th>Action</th>
                  </tr>
                </thead>

                <tbody>
                  <?php foreach ($articles as $article): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?php echo e($article['title']); ?></div>
                        <?php if ($article['url']): ?>
                          <a class="accent small" href="<?php echo e($article['url']); ?>" target="_blank" rel="noopener">
                            Read
                          </a>
                        <?php endif; ?>
                      </td>

                      <td><?php echo e($article['source'] ?? 'Manual'); ?></td>

                      <td>
                        <?php
                          $dateValue = $article['published_at'] ?: $article['created_at'];
                          echo e(date('d M Y', strtotime($dateValue)));
                        ?>
                      </td>

                      <td>
                        <form method="POST" onsubmit="return confirm('Delete this article?');">
                          <input type="hidden" name="action" value="delete_article">
                          <input type="hidden" name="article_id" value="<?php echo (int) $article['id']; ?>">
                          <button class="btn btn-ghost btn-sm" type="submit">
                            Delete
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>