<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);

$selectedSlug = trim($_GET['topic'] ?? '');
$search = trim($_GET['q'] ?? '');

/*
|--------------------------------------------------------------------------
| Get topics
|--------------------------------------------------------------------------
*/
$topicsStatement = $pdo->query("
    SELECT
        t.id,
        t.title,
        t.slug,
        t.summary,
        t.level,
        t.icon,
        COUNT(DISTINCT l.id) AS lesson_count,
        COUNT(DISTINCT sm.id) AS material_count
    FROM topics t
    LEFT JOIN lessons l ON l.topic_id = t.id AND l.is_published = 1
    LEFT JOIN study_materials sm ON sm.topic_id = t.id
    GROUP BY t.id
    ORDER BY t.sort_order ASC, t.id ASC
");

$topics = $topicsStatement->fetchAll();

if (!$selectedSlug && $topics) {
    $selectedSlug = $topics[0]['slug'];
}

/*
|--------------------------------------------------------------------------
| Get selected topic
|--------------------------------------------------------------------------
*/
$topicStatement = $pdo->prepare("
    SELECT id, title, slug, summary, level, icon
    FROM topics
    WHERE slug = :slug
    LIMIT 1
");

$topicStatement->execute([
    'slug' => $selectedSlug,
]);

$selectedTopic = $topicStatement->fetch();

if (!$selectedTopic && $topics) {
    $selectedTopic = $topics[0];
}

/*
|--------------------------------------------------------------------------
| Get lessons for selected topic
|--------------------------------------------------------------------------
*/
$lessons = [];

if ($selectedTopic) {
    $lessonsStatement = $pdo->prepare("
        SELECT id, title, body, estimated_minutes, created_at
        FROM lessons
        WHERE topic_id = :topic_id
        AND is_published = 1
        ORDER BY id ASC
    ");

    $lessonsStatement->execute([
        'topic_id' => $selectedTopic['id'],
    ]);

    $lessons = $lessonsStatement->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Get study materials for selected topic
|--------------------------------------------------------------------------
*/
$materials = [];

if ($selectedTopic) {
    $materialsStatement = $pdo->prepare("
        SELECT id, title, material_type, file_path, external_url, description, created_at
        FROM study_materials
        WHERE topic_id = :topic_id OR topic_id IS NULL
        ORDER BY id DESC
        LIMIT 8
    ");

    $materialsStatement->execute([
        'topic_id' => $selectedTopic['id'],
    ]);

    $materials = $materialsStatement->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Get articles/news
|--------------------------------------------------------------------------
*/
if ($search !== '') {
    $articlesStatement = $pdo->prepare("
        SELECT id, title, source, url, summary, published_at
        FROM articles
        WHERE title LIKE :search
        OR summary LIKE :search
        OR source LIKE :search
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT 8
    ");

    $articlesStatement->execute([
        'search' => '%' . $search . '%',
    ]);
} else {
    $articlesStatement = $pdo->query("
        SELECT id, title, source, url, summary, published_at
        FROM articles
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT 8
    ");
}

$articles = $articlesStatement->fetchAll();

function short_text(string $text, int $limit = 260): string
{
    $clean = trim(strip_tags($text));

    if (mb_strlen($clean) <= $limit) {
        return $clean;
    }

    return mb_substr($clean, 0, $limit) . '...';
}

function material_icon(string $type): string
{
    return match ($type) {
        'PDF' => 'fa-solid fa-file-pdf',
        'External Link' => 'fa-solid fa-link',
        'Video' => 'fa-solid fa-circle-play',
        default => 'fa-solid fa-file-lines',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Learn | CyberAware</title>
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
      <i class="fa-solid fa-book-open accent"></i> Learn
    </span>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <a class="icon-button" href="quiz.php" title="Timed Quiz">
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
          <i class="fa-solid fa-graduation-cap"></i>
          Topic Wise Study
        </span>

        <h2 class="mb-1">Cyber Security Learning Center</h2>
        <p class="text-muted mb-0">
          Focused lessons, study materials, and cyber awareness articles in one place.
        </p>
      </div>

      <a class="btn btn-glow btn-sm" href="quiz.php">
        Start Timed Quiz
      </a>
    </div>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="panel h-100">
          <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-layer-group accent"></i>
            <span class="fw-semibold">Topics</span>
          </div>

          <div class="d-grid gap-2">
            <?php foreach ($topics as $topic): ?>
              <?php
                $isActive = $selectedTopic && $topic['slug'] === $selectedTopic['slug'];
              ?>

              <a
                class="game-tile text-decoration-none <?php echo $isActive ? 'panel glass' : ''; ?>"
                href="learn.php?topic=<?php echo urlencode($topic['slug']); ?>"
              >
                <div class="d-flex align-items-center gap-3">
                  <div class="icon-circle">
                    <i class="<?php echo e($topic['icon']); ?>"></i>
                  </div>

                  <div>
                    <div class="fw-semibold"><?php echo e($topic['title']); ?></div>
                    <div class="text-muted small">
                      <?php echo e($topic['level']); ?> •
                      <?php echo (int) $topic['lesson_count']; ?> lessons
                    </div>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <?php if ($selectedTopic): ?>
          <div class="panel glass mb-4">
            <div class="d-flex align-items-center gap-3">
              <div class="icon-circle accent">
                <i class="<?php echo e($selectedTopic['icon']); ?>"></i>
              </div>

              <div>
                <div class="text-muted small"><?php echo e($selectedTopic['level']); ?></div>
                <h3 class="mb-1"><?php echo e($selectedTopic['title']); ?></h3>
                <p class="text-muted mb-0">
                  <?php echo e($selectedTopic['summary']); ?>
                </p>
              </div>
            </div>
          </div>

          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-book accent"></i>
              <span class="fw-semibold">Lessons</span>
            </div>

            <?php if ($lessons): ?>
              <div class="d-grid gap-3">
                <?php foreach ($lessons as $lesson): ?>
                  <div class="result-box">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                      <h5 class="mb-0"><?php echo e($lesson['title']); ?></h5>

                      <span class="status-pill warn">
                        <i class="fa-solid fa-clock"></i>
                        <?php echo (int) $lesson['estimated_minutes']; ?> min
                      </span>
                    </div>

                    <p class="text-muted mb-0">
                      <?php echo e(short_text($lesson['body'], 420)); ?>
                    </p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="result-box text-muted">
                No lesson has been published for this topic yet.
              </div>
            <?php endif; ?>
          </div>

          <div class="panel mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <i class="fa-solid fa-folder-open accent"></i>
              <span class="fw-semibold">Study Materials</span>
            </div>

            <?php if ($materials): ?>
              <div class="game-grid">
                <?php foreach ($materials as $material): ?>
                  <div class="game-tile">
                    <div class="icon-circle">
                      <i class="<?php echo e(material_icon($material['material_type'])); ?>"></i>
                    </div>

                    <div class="fw-semibold"><?php echo e($material['title']); ?></div>

                    <div class="text-muted small">
                      <?php echo e($material['description'] ?: 'Study material for awareness learning.'); ?>
                    </div>

                    <span class="status-pill warn">
                      <?php echo e($material['material_type']); ?>
                    </span>

                    <?php if ($material['external_url']): ?>
                      <a
                        class="btn btn-ghost btn-sm"
                        href="<?php echo e($material['external_url']); ?>"
                        target="_blank"
                        rel="noopener"
                      >
                        Open
                      </a>
                    <?php elseif ($material['file_path']): ?>
                      <a
                        class="btn btn-ghost btn-sm"
                        href="<?php echo e($material['file_path']); ?>"
                        target="_blank"
                      >
                        View File
                      </a>
                    <?php else: ?>
                      <button class="btn btn-ghost btn-sm" disabled>
                        Coming Soon
                      </button>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="result-box text-muted">
                No PDF, article, or external study material added yet.
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="panel text-center">
            <h4>No topics found</h4>
            <p class="text-muted mb-0">
              Add topics from the database or admin panel later.
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel mt-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div class="d-flex align-items-center gap-2">
          <i class="fa-solid fa-newspaper accent"></i>
          <span class="fw-semibold">Cyber Crime Articles / News</span>
        </div>

        <form class="d-flex gap-2" method="GET" action="learn.php">
          <?php if ($selectedTopic): ?>
            <input type="hidden" name="topic" value="<?php echo e($selectedTopic['slug']); ?>">
          <?php endif; ?>

          <input
            type="text"
            name="q"
            class="form-control"
            placeholder="Search articles"
            value="<?php echo e($search); ?>"
          >

          <button class="btn btn-glow btn-sm" type="submit">
            Search
          </button>
        </form>
      </div>

      <?php if ($articles): ?>
        <div class="game-grid">
          <?php foreach ($articles as $article): ?>
            <div class="game-tile">
              <div class="icon-circle">
                <i class="fa-solid fa-newspaper"></i>
              </div>

              <div class="fw-semibold"><?php echo e($article['title']); ?></div>

              <div class="text-muted small">
                <?php echo e($article['summary'] ?: 'Cyber awareness article.'); ?>
              </div>

              <span class="status-pill warn">
                <?php echo e($article['source'] ?: 'Source'); ?>
              </span>

              <?php if ($article['published_at']): ?>
                <div class="text-muted small">
                  <?php echo e(date('d M Y', strtotime($article['published_at']))); ?>
                </div>
              <?php endif; ?>

              <?php if ($article['url']): ?>
                <a
                  class="btn btn-ghost btn-sm"
                  href="<?php echo e($article['url']); ?>"
                  target="_blank"
                  rel="noopener"
                >
                  Read Article
                </a>
              <?php else: ?>
                <button class="btn btn-ghost btn-sm" disabled>
                  No Link
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="result-box text-muted">
          No article or news added yet. Later, we can connect a cyber news API or let admin add articles manually.
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>