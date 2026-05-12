<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login($pdo);
$userId = (int) $user['id'];

$quizLimit = 10;
$quizTimeSeconds = 300;
$result = null;
$reviewRows = [];

if (isset($_GET['new'])) {
    unset($_SESSION['quiz_question_ids'], $_SESSION['quiz_started_at']);
    redirect('quiz.php');
}

/*
|--------------------------------------------------------------------------
| Start a new quiz session
|--------------------------------------------------------------------------
*/
function start_quiz(PDO $pdo, int $limit): void
{
    $statement = $pdo->prepare("
        SELECT id
        FROM questions
        WHERE is_active = 1
        ORDER BY RAND()
        LIMIT {$limit}
    ");

    $statement->execute();

    $questionIds = array_map('intval', array_column($statement->fetchAll(), 'id'));

    $_SESSION['quiz_question_ids'] = $questionIds;
    $_SESSION['quiz_started_at'] = time();
}

/*
|--------------------------------------------------------------------------
| Award XP and badges after quiz
|--------------------------------------------------------------------------
*/
function award_quiz_xp(PDO $pdo, int $userId, int $earnedXp): void
{
    if ($earnedXp <= 0) {
        return;
    }

    $statement = $pdo->prepare("
        UPDATE users
        SET total_xp = total_xp + :xp
        WHERE id = :user_id
    ");

    $statement->execute([
        'xp' => $earnedXp,
        'user_id' => $userId,
    ]);

    $badgeStatement = $pdo->prepare("
        INSERT IGNORE INTO user_badges (user_id, badge_id)
        SELECT :user_id, id
        FROM badges
        WHERE required_xp > 0
        AND required_xp <= (
            SELECT total_xp
            FROM users
            WHERE id = :user_id_2
        )
    ");

    $badgeStatement->execute([
        'user_id' => $userId,
        'user_id_2' => $userId,
    ]);
}

/*
|--------------------------------------------------------------------------
| Submit quiz
|--------------------------------------------------------------------------
*/
if (is_post_request() && isset($_POST['submit_quiz'])) {
    $questionIds = $_SESSION['quiz_question_ids'] ?? [];
    $startedAt = (int) ($_SESSION['quiz_started_at'] ?? time());

    if (!$questionIds) {
        redirect('quiz.php?new=1');
    }

    $timeTaken = min(time() - $startedAt, $quizTimeSeconds);
    $answers = $_POST['answers'] ?? [];

    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

    $correctStatement = $pdo->prepare("
        SELECT
            q.id AS question_id,
            q.points,
            qo.id AS correct_option_id
        FROM questions q
        INNER JOIN question_options qo ON qo.question_id = q.id
        WHERE q.id IN ({$placeholders})
        AND qo.is_correct = 1
    ");

    $correctStatement->execute($questionIds);

    $correctMap = [];

    foreach ($correctStatement->fetchAll() as $row) {
        $correctMap[(int) $row['question_id']] = [
            'correct_option_id' => (int) $row['correct_option_id'],
            'points' => (int) $row['points'],
        ];
    }

    $correctAnswers = 0;
    $score = 0;
    $totalQuestions = count($questionIds);

    foreach ($questionIds as $questionId) {
        $selectedOptionId = isset($answers[$questionId])
            ? (int) $answers[$questionId]
            : 0;

        $correctOptionId = $correctMap[$questionId]['correct_option_id'] ?? 0;

        if ($selectedOptionId === $correctOptionId) {
            $correctAnswers++;
            $score += $correctMap[$questionId]['points'] ?? 1;
        }
    }

    $pdo->beginTransaction();

    try {
        $attemptStatement = $pdo->prepare("
            INSERT INTO quiz_attempts
              (user_id, score, total_questions, correct_answers, time_taken_seconds, completed_at)
            VALUES
              (:user_id, :score, :total_questions, :correct_answers, :time_taken_seconds, NOW())
        ");

        $attemptStatement->execute([
            'user_id' => $userId,
            'score' => $score,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'time_taken_seconds' => $timeTaken,
        ]);

        $attemptId = (int) $pdo->lastInsertId();

        $answerStatement = $pdo->prepare("
            INSERT INTO quiz_answers
              (attempt_id, question_id, selected_option_id, is_correct)
            VALUES
              (:attempt_id, :question_id, :selected_option_id, :is_correct)
        ");

        foreach ($questionIds as $questionId) {
            $selectedOptionId = isset($answers[$questionId])
                ? (int) $answers[$questionId]
                : null;

            $correctOptionId = $correctMap[$questionId]['correct_option_id'] ?? 0;
            $isCorrect = $selectedOptionId !== null && $selectedOptionId === $correctOptionId;

            $answerStatement->execute([
                'attempt_id' => $attemptId,
                'question_id' => $questionId,
                'selected_option_id' => $selectedOptionId,
                'is_correct' => $isCorrect ? 1 : 0,
            ]);
        }

        $earnedXp = $score;
        award_quiz_xp($pdo, $userId, $earnedXp);

        log_activity(
            $pdo,
            $userId,
            'quiz',
            "Completed timed quiz. Score: {$score}/{$totalQuestions}. Earned {$earnedXp} XP."
        );

        $pdo->commit();

        $result = [
            'score' => $score,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'time_taken' => $timeTaken,
            'earned_xp' => $earnedXp,
            'percentage' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0,
            'attempt_id' => $attemptId,
        ];

        unset($_SESSION['quiz_question_ids'], $_SESSION['quiz_started_at']);
    } catch (Throwable $error) {
        $pdo->rollBack();
        die('Quiz submission failed: ' . htmlspecialchars($error->getMessage()));
    }
}

/*
|--------------------------------------------------------------------------
| Load review after result
|--------------------------------------------------------------------------
*/
if ($result) {
    $reviewStatement = $pdo->prepare("
        SELECT
            q.question_text,
            selected.option_text AS selected_answer,
            correct.option_text AS correct_answer,
            qa.is_correct
        FROM quiz_answers qa
        INNER JOIN questions q ON q.id = qa.question_id
        LEFT JOIN question_options selected ON selected.id = qa.selected_option_id
        INNER JOIN question_options correct
            ON correct.question_id = q.id
            AND correct.is_correct = 1
        WHERE qa.attempt_id = :attempt_id
        ORDER BY qa.id ASC
    ");

    $reviewStatement->execute([
        'attempt_id' => $result['attempt_id'],
    ]);

    $reviewRows = $reviewStatement->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Load active quiz questions
|--------------------------------------------------------------------------
*/
if (!$result) {
    if (
        empty($_SESSION['quiz_question_ids']) ||
        empty($_SESSION['quiz_started_at'])
    ) {
        start_quiz($pdo, $quizLimit);
    }

    $questionIds = $_SESSION['quiz_question_ids'];
    $startedAt = (int) $_SESSION['quiz_started_at'];

    if (!$questionIds) {
        $questions = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

        $questionStatement = $pdo->prepare("
            SELECT id, question_text
            FROM questions
            WHERE id IN ({$placeholders})
            ORDER BY FIELD(id, {$placeholders})
        ");

        $questionStatement->execute(array_merge($questionIds, $questionIds));
        $questions = $questionStatement->fetchAll();

        $optionStatement = $pdo->prepare("
            SELECT id, question_id, option_text
            FROM question_options
            WHERE question_id IN ({$placeholders})
            ORDER BY question_id ASC, id ASC
        ");

        $optionStatement->execute($questionIds);

        $optionsByQuestion = [];

        foreach ($optionStatement->fetchAll() as $option) {
            $optionsByQuestion[(int) $option['question_id']][] = $option;
        }
    }

    $elapsed = time() - $startedAt;
    $remainingSeconds = max(0, $quizTimeSeconds - $elapsed);
}

function format_seconds(int $seconds): string
{
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;

    return sprintf('%02d:%02d', $minutes, $secs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Timed Quiz | CyberAware</title>
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
      <i class="fa-solid fa-clipboard-question accent"></i> Timed Quiz
    </span>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <a class="icon-button" href="learn.php" title="Learn">
        <i class="fa-solid fa-book-open"></i>
      </a>

      <a class="icon-button" href="profile.php" title="Profile">
        <i class="fa-solid fa-user-astronaut"></i>
      </a>
    </div>
  </nav>

  <main class="container stage">
    <?php if ($result): ?>
      <div class="panel glass text-center mb-4">
        <div class="icon-circle mx-auto mb-3">
          <i class="fa-solid fa-check"></i>
        </div>

        <h2 class="mb-1">
          Score: <?php echo (int) $result['score']; ?> / <?php echo (int) $result['total_questions']; ?>
        </h2>

        <p class="text-muted mb-2">
          Correct answers: <?php echo (int) $result['correct_answers']; ?>
          out of <?php echo (int) $result['total_questions']; ?>
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
          <span class="status-pill success">
            <?php echo (int) $result['percentage']; ?>%
          </span>

          <span class="status-pill warn">
            Time: <?php echo e(format_seconds((int) $result['time_taken'])); ?>
          </span>

          <span class="status-pill success">
            +<?php echo (int) $result['earned_xp']; ?> XP
          </span>
        </div>

        <div class="d-flex justify-content-center gap-2 mt-4">
          <a href="quiz.php?new=1" class="btn btn-ghost">
            Try Again
          </a>

          <a href="dashboard.php" class="btn btn-glow">
            Back to Hub
          </a>
        </div>
      </div>

      <div class="panel">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i class="fa-solid fa-magnifying-glass accent"></i>
          <span class="fw-semibold">Answer Review</span>
        </div>

        <div class="d-grid gap-3">
          <?php foreach ($reviewRows as $index => $row): ?>
            <div class="result-box">
              <div class="d-flex justify-content-between gap-2 mb-2">
                <div class="fw-semibold">
                  <?php echo ($index + 1); ?>.
                  <?php echo e($row['question_text']); ?>
                </div>

                <?php if ((int) $row['is_correct'] === 1): ?>
                  <span class="status-pill success">Correct</span>
                <?php else: ?>
                  <span class="status-pill locked">Wrong</span>
                <?php endif; ?>
              </div>

              <div class="text-muted small">
                Your answer:
                <?php echo e($row['selected_answer'] ?: 'Not answered'); ?>
              </div>

              <div class="text-muted small">
                Correct answer:
                <?php echo e($row['correct_answer']); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
          <span class="status-pill warn mb-2">
            <i class="fa-solid fa-stopwatch"></i>
            Countdown Active
          </span>

          <h2 class="mb-1">Quick Cyber Awareness Assessment</h2>
          <p class="text-muted mb-0">
            Answer all questions before the timer ends. The quiz will auto-submit at zero.
          </p>
        </div>

        <div class="panel glass text-center">
          <div class="text-muted small">Time Left</div>
          <div id="timer" class="stat-value">
            <?php echo e(format_seconds($remainingSeconds)); ?>
          </div>
        </div>
      </div>

      <?php if (empty($questions)): ?>
        <div class="panel text-center">
          <h4>No quiz questions found</h4>
          <p class="text-muted">
            Run the question seeder first or add questions from admin later.
          </p>
        </div>
      <?php else: ?>
        <form id="quizForm" method="POST" action="quiz.php">
          <input type="hidden" name="submit_quiz" value="1">

          <div class="d-grid gap-4">
            <?php foreach ($questions as $index => $question): ?>
              <div class="panel">
                <div class="mb-3">
                  <div class="text-muted small">
                    Question <?php echo ($index + 1); ?> of <?php echo count($questions); ?>
                  </div>

                  <div class="fw-semibold">
                    <?php echo e($question['question_text']); ?>
                  </div>
                </div>

                <div class="list-group">
                  <?php foreach ($optionsByQuestion[(int) $question['id']] ?? [] as $option): ?>
                    <label class="list-group-item">
                      <input
                        class="form-check-input me-2"
                        type="radio"
                        name="answers[<?php echo (int) $question['id']; ?>]"
                        value="<?php echo (int) $option['id']; ?>"
                      >
                      <?php echo e($option['option_text']); ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="panel mt-4 text-center">
            <button type="submit" class="btn btn-glow">
              Submit Quiz
            </button>

            <a href="quiz.php?new=1" class="btn btn-ghost">
              Restart
            </a>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <?php if (!$result): ?>
    <script>
      const timerEl = document.getElementById("timer");
      const quizForm = document.getElementById("quizForm");

      let remainingSeconds = <?php echo (int) $remainingSeconds; ?>;
      let submitted = false;

      function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;

        return String(minutes).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
      }

      function tickTimer() {
        if (!timerEl || !quizForm || submitted) {
          return;
        }

        timerEl.textContent = formatTime(remainingSeconds);

        if (remainingSeconds <= 0) {
          submitted = true;
          quizForm.submit();
          return;
        }

        remainingSeconds -= 1;
      }

      tickTimer();
      setInterval(tickTimer, 1000);
    </script>
  <?php endif; ?>
</body>
</html>