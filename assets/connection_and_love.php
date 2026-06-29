<?php
require_once 'config.php';

$pdo = getDBConnection();

// ── Handle form submission ────────────────────────────────────────────────
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO `connection_love_assessments` (
                user_id,
                self_awareness_q1, self_awareness_q2, self_awareness_q3,
                self_awareness_rating, self_awareness_notes,
                emotional_connection_q1, emotional_connection_q2,
                emotional_connection_rating, emotional_connection_notes,
                family_friends_q1, family_friends_q2,
                family_friends_rating, family_friends_notes,
                love_expression_q1, love_expression_q2,
                love_expression_rating, love_expression_notes,
                community_q1, community_q2,
                community_rating, community_notes,
                top_improvement_areas, next_steps
            ) VALUES (
                :user_id,
                :self_awareness_q1, :self_awareness_q2, :self_awareness_q3,
                :self_awareness_rating, :self_awareness_notes,
                :emotional_connection_q1, :emotional_connection_q2,
                :emotional_connection_rating, :emotional_connection_notes,
                :family_friends_q1, :family_friends_q2,
                :family_friends_rating, :family_friends_notes,
                :love_expression_q1, :love_expression_q2,
                :love_expression_rating, :love_expression_notes,
                :community_q1, :community_q2,
                :community_rating, :community_notes,
                :top_improvement_areas, :next_steps
            )
        ");

        $cb = fn($key) => isset($_POST[$key]) ? 1 : 0;
        $rt = fn($key) => isset($_POST[$key]) && $_POST[$key] !== '' ? (int)$_POST[$key] : null;
        $tx = fn($key) => isset($_POST[$key]) ? trim($_POST[$key]) : '';

        $userId = $_SESSION['user_id'] ?? null;

        $stmt->execute([
            ':user_id'                => $userId,
            ':self_awareness_q1'      => $cb('self_awareness_q1'),
            ':self_awareness_q2'      => $cb('self_awareness_q2'),
            ':self_awareness_q3'      => $cb('self_awareness_q3'),
            ':self_awareness_rating'  => $rt('self_awareness_rating'),
            ':self_awareness_notes'   => $tx('self_awareness_notes'),
            ':emotional_connection_q1'            => $cb('emotional_connection_q1'),
            ':emotional_connection_q2'            => $cb('emotional_connection_q2'),
            ':emotional_connection_rating'        => $rt('emotional_connection_rating'),
            ':emotional_connection_notes'         => $tx('emotional_connection_notes'),
            ':family_friends_q1'      => $cb('family_friends_q1'),
            ':family_friends_q2'      => $cb('family_friends_q2'),
            ':family_friends_rating'  => $rt('family_friends_rating'),
            ':family_friends_notes'   => $tx('family_friends_notes'),
            ':love_expression_q1'     => $cb('love_expression_q1'),
            ':love_expression_q2'     => $cb('love_expression_q2'),
            ':love_expression_rating' => $rt('love_expression_rating'),
            ':love_expression_notes'  => $tx('love_expression_notes'),
            ':community_q1'           => $cb('community_q1'),
            ':community_q2'           => $cb('community_q2'),
            ':community_rating'       => $rt('community_rating'),
            ':community_notes'        => $tx('community_notes'),
            ':top_improvement_areas'  => $tx('top_improvement_areas'),
            ':next_steps'             => $tx('next_steps'),
        ]);

        // ── Write total score to total_scores table ───────────────────────
        $totalScore =
            ($rt('self_awareness_rating')  ?? 0) +
            ($rt('emotional_connection_rating')        ?? 0) +
            ($rt('family_friends_rating')  ?? 0) +
            ($rt('love_expression_rating') ?? 0) +
            ($rt('community_rating')       ?? 0);

        $scoreStmt = $pdo->prepare("
            INSERT INTO total_scores (user_id, pillar_id, total_score)
            VALUES (:user_id, 1, :total_score)
        ");
        $scoreStmt->execute([':user_id' => $userId, ':total_score' => $totalScore]);

        header('Location: view_results_connection.php?saved=1');
        exit;
    } catch (PDOException $e) {
        $errorMessage = 'Error saving assessment. Please try again.';
    }
}

// ── Central label map: category_field → display heading ──────────────────
// sort_order is the category sequence — same value for all questions in a category
const CATEGORY_LABELS = [
    'self_awareness' => 'Self-Awareness &amp; Self-Compassion',
    'emotional_connection' => 'Emotional Connection &amp; Intimacy',
    'family_friends' => 'Family &amp; Friends',
    'love_expression' => 'Love Expression',
    'community' => 'Community &amp; Belonging',
];

// ── Load questions from DB ────────────────────────────────────────────────
function loadQuestions(PDO $pdo): array {
    $fields       = array_keys(CATEGORY_LABELS);
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    try {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE category_field IN ({$placeholders}) ORDER BY sort_order");
        $stmt->execute($fields);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        return defaultQuestions();
    }
    if (empty($rows)) return defaultQuestions();

    $map = [];
    foreach ($rows as $r) {
        $map[$r['category_field']][$r['question_key']] = $r['question_text'];
    }
    return $map;
}

function defaultQuestions(): array {
    return array_fill_keys(array_keys(CATEGORY_LABELS), []);
}

$questions = loadQuestions($pdo);
$pageTitle = "Connection and Love";
$pageTitle  = "Connection and Love";

// ── Helper: render one checkbox question ─────────────────────────────────
function renderCheckbox(string $name, string $text): string {
    $safe = htmlspecialchars($text);
    return <<<HTML
        <label class="checkbox-label">
            <input type="checkbox" name="{$name}" value="1">
            <span>{$safe}</span>
        </label>
    HTML;
}

// ── Helper: render a full rating widget ──────────────────────────────────
function renderRating(string $prefix, string $id_prefix): string {
    $html = '<div class="rating-group"><div class="rating-label">Overall Rating</div><div class="rating-buttons">';
    for ($i = 1; $i <= 5; $i++) {
        $id  = "{$id_prefix}_r{$i}";
        $req = $i === 1 ? ' required' : '';
        $html .= "<input type=\"radio\" name=\"{$prefix}_rating\" value=\"{$i}\" id=\"{$id}\"{$req}>";
        $html .= "<label for=\"{$id}\">{$i}</label>";
    }
    $html .= '</div><div class="rating-anchors"><span>Needs work</span><span>Thriving</span></div></div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .top-nav .nav-link {
            color: #C9A84C;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .top-nav .nav-link:hover { color: #F0EBE1; }
    </style>
</head>
<body>
    <div class="container">

        <nav class="top-nav">
            <a href="dashboard.php" class="nav-link">← Dashboard</a>
            <a href="logout.php" class="nav-link">Sign Out</a>
        </nav>

        <header class="header">
            <h1>Connection <span class="accent">and Love</span></h1>
            <p class="subtitle">Explore your relationships, intimacy, community, and the ways you give and receive love. Take your time — honesty leads to clarity.</p>
            <a href="view_results_connection.php" class="btn btn-secondary" style="margin-top:0.75rem;display:inline-block;">View Past Results →</a>
        </header>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Live Score Tracker -->
        <div class="progress-tracker">
            <div class="progress-info">
                <h3>Completion Points</h3>
                <div class="score-live" id="livePoints">0 <span>/ 50 pts</span></div>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-label">
                    <span id="completedCount">0 of 5 rated</span>
                    <span id="completedPct">0%</span>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="progressFill"></div>
                </div>
            </div>
        </div>

        <form id="assessmentForm" action="connection_and_love.php" method="POST">
            <?php foreach ($questions as $cat => $qs):
                $cardNum  = array_search($cat, array_keys($questions)) + 1;
                $label    = CATEGORY_LABELS[$cat] ?? ucwords(str_replace('_', ' ', $cat));
                $idPrefix = substr(str_replace('_', '', $cat), 0, 3) . $cardNum;
            ?>
            <div class="category-card" id="card-<?= $cardNum ?>">
                <div class="category-header">
                    <span class="category-number"><?= $cardNum ?></span>
                    <h2><?= $label ?></h2>
                    <span class="card-points">0 pts</span><span class="category-check">✓</span>
                </div>
                <div class="category-body">
                    <div class="checkbox-group">
                        <div class="checkbox-group-label">Check all that apply</div>
                        <?php foreach ($qs as $qKey => $qText): ?>
                            <?= renderCheckbox("{$cat}_{$qKey}", $qText) ?>
                        <?php endforeach; ?>
                    </div>
                    <?= renderRating($cat, $idPrefix) ?>
                    <div class="notes-group">
                        <div class="notes-label">Reflections</div>
                        <textarea name="<?= $cat ?>_notes" rows="2" placeholder="What's working? What could improve?"></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- ─── Action Planning ─── -->
            <div class="category-card action-planning">
                <div class="category-header">
                    <span class="category-number">✍</span>
                    <h2>Action Planning</h2>
                    <span class="category-check"></span>
                </div>
                <div class="category-body">
                    <div class="notes-group">
                        <div class="notes-label">Top 3 Areas to Improve</div>
                        <textarea name="top_improvement_areas" rows="3" placeholder="Which areas feel most urgent or impactful to address?"></textarea>
                    </div>
                    <div class="notes-group">
                        <div class="notes-label">Concrete Next Steps</div>
                        <textarea name="next_steps" rows="4" placeholder="What specific, actionable steps will you take in the next 30 days?"></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="reset" class="btn btn-secondary">Reset Form</button>
                <button type="submit" class="btn btn-primary">Complete Assessment →</button>
            </div>

        </form>
    </div>

    <script>
        const categories = [
            'self_awareness', 'emotional_connection', 'family_friends', 'love_expression', 'community'
        ];
        const TOTAL_CATEGORIES    = 5;
        const POINTS_PER_CATEGORY = 10;
        const form           = document.getElementById('assessmentForm');
        const livePoints     = document.getElementById('livePoints');
        const progressFill   = document.getElementById('progressFill');
        const completedCount = document.getElementById('completedCount');
        const completedPct   = document.getElementById('completedPct');

        function updateProgress() {
            let rated = 0;
            categories.forEach((cat, i) => {
                const selected = form.querySelector(`input[name="${cat}_rating"]:checked`);
                const card     = document.getElementById(`card-${i + 1}`);
                const badge    = card ? card.querySelector('.card-points') : null;
                if (selected) {
                    rated++;
                    card.classList.add('is-complete');
                    if (badge) { badge.textContent = '+10 pts'; badge.classList.add('earned'); }
                } else {
                    card.classList.remove('is-complete');
                    if (badge) { badge.textContent = '0 pts'; badge.classList.remove('earned'); }
                }
            });
            const totalPoints = rated * POINTS_PER_CATEGORY;
            const pct = Math.round((rated / TOTAL_CATEGORIES) * 100);
            livePoints.innerHTML       = `${totalPoints} <span>/ 50 pts</span>`;
            progressFill.style.width   = pct + '%';
            completedCount.textContent = `${rated} of ${TOTAL_CATEGORIES} rated`;
            completedPct.textContent   = pct + '%';
        }

        form.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', updateProgress));

        form.addEventListener('reset', () => {
            setTimeout(() => {
                document.querySelectorAll('.card-points').forEach(b => { b.textContent = '0 pts'; b.classList.remove('earned'); });
                document.querySelectorAll('.category-card').forEach(c => c.classList.remove('is-complete'));
                livePoints.innerHTML       = '0 <span>/ 50 pts</span>';
                progressFill.style.width   = '0%';
                completedCount.textContent = `0 of ${TOTAL_CATEGORIES} rated`;
                completedPct.textContent   = '0%';
            }, 0);
        });
    </script>
</body>
</html>
