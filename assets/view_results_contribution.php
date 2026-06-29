<?php
require_once 'config.php';

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$pageTitle = "Contribution & Helping Others — Results";
$userId = (int)$_SESSION['user_id'];

// ── Score interpretation helper ───────────────────────────────────────────────
function getScoreInterpretation(int $score, int $max = 25): array {
    if ($max <= 0) {
        $max = 25; // Prevent division by zero
    }
    $pct = ($score / $max) * 100;

    if ($pct >= 90) {
        return [
            'level'       => 'Thriving',
            'color'       => '#4CAF50',
            'description' => 'You are excelling as a generous contributor and helper in your community.',
        ];
    }
    if ($pct >= 75) {
        return [
            'level'       => 'Flourishing',
            'color'       => '#8BC34A',
            'description' => 'Your spirit of contribution is strong with a few areas to develop further.',
        ];
    }
    if ($pct >= 55) {
        return [
            'level'       => 'Growing',
            'color'       => '#C9A84C',
            'description' => 'You\'re making meaningful progress in how you show up for others.',
        ];
    }
    if ($pct >= 35) {
        return [
            'level'       => 'Developing',
            'color'       => '#FF9800',
            'description' => 'Several areas of contribution need attention and intentional effort.',
        ];
    }
    return [
        'level'       => 'Needs Work',
        'color'       => '#F44336',
        'description' => 'This is a great time to rebuild your habits of helping and giving back.',
    ];
}

// ── Fetch selected assessment ─────────────────────────────────────────────
$selectedAssessment = null;
$errorMessage = null;

try {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT ca.*,
               COALESCE(ts.total_score,
                   (COALESCE(ca.proactive_rating,0) + COALESCE(ca.knowledge_rating,0) +
                    COALESCE(ca.generosity_rating,0) + COALESCE(ca.impact_rating,0) +
                    COALESCE(ca.sustainable_rating,0))
               ) AS total_score
        FROM contribution_assessments ca
        LEFT JOIN total_scores ts
            ON ts.user_id = ca.user_id
           AND ts.pillar_id = 3
           AND ts.recorded_at = (
               SELECT MAX(t2.recorded_at) FROM total_scores t2
               WHERE t2.user_id = ca.user_id AND t2.pillar_id = 3
               AND t2.recorded_at <= ca.assessment_date + INTERVAL 1 MINUTE
           )
        WHERE ca.id = ? AND ca.user_id = ?");
        $stmt->execute([(int)$_GET['id'], $userId]);
        $selectedAssessment = $stmt->fetch();

        // Verify assessment belongs to user
        if ($selectedAssessment && (int)$selectedAssessment['user_id'] !== $userId) {
            $selectedAssessment = null;
            $errorMessage = "Access denied.";
        }
    }

    // ── Fetch all assessments for sidebar ────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT id, assessment_date,
               (COALESCE(proactive_rating,0) + COALESCE(knowledge_rating,0) +
                COALESCE(generosity_rating,0) + COALESCE(impact_rating,0) +
                COALESCE(sustainable_rating,0)) AS total_score
        FROM contribution_assessments
        WHERE user_id = ?
        ORDER BY assessment_date DESC
    ");
    $stmt->execute([$userId]);
    $allAssessments = $stmt->fetchAll();

    // ── If no assessment selected, default to most recent ────────────────────
    if (!$selectedAssessment && !empty($allAssessments) && !$errorMessage) {
        $stmt = $pdo->prepare("SELECT ca.*,
               COALESCE(ts.total_score,
                   (COALESCE(ca.proactive_rating,0) + COALESCE(ca.knowledge_rating,0) +
                    COALESCE(ca.generosity_rating,0) + COALESCE(ca.impact_rating,0) +
                    COALESCE(ca.sustainable_rating,0))
               ) AS total_score
        FROM contribution_assessments ca
        LEFT JOIN total_scores ts
            ON ts.user_id = ca.user_id
           AND ts.pillar_id = 3
           AND ts.recorded_at = (
               SELECT MAX(t2.recorded_at) FROM total_scores t2
               WHERE t2.user_id = ca.user_id AND t2.pillar_id = 3
               AND t2.recorded_at <= ca.assessment_date + INTERVAL 1 MINUTE
           )
        WHERE ca.id = ? AND ca.user_id = ?");
        $stmt->execute([$allAssessments[0]['id'], $userId]);
        $selectedAssessment = $stmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Database error in view_results_contribution.php: " . $e->getMessage());
    $errorMessage = "An error occurred while loading your assessments.";
}

$categories = [
    ['name' => 'Proactive Helping',                 'field' => 'proactive'],
    ['name' => 'Sharing Knowledge & Mentoring',     'field' => 'knowledge'],
    ['name' => 'Generosity & Community Engagement', 'field' => 'generosity'],
    ['name' => 'Impact-Oriented Contribution',      'field' => 'impact'],
    ['name' => 'Sustainable Helping & Resilience',  'field' => 'sustainable'],
];
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
        .top-nav .nav-link:hover {
            color: #F0EBE1;
        }

        .alert-error {
            background-color: rgba(244, 67, 54, 0.1);
            border: 1px solid #F44336;
            color: #F44336;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">

        <nav class="top-nav">
            <a href="dashboard.php" class="nav-link" aria-label="Back to Dashboard">← Dashboard</a>
            <a href="view_recommendations.php" class="nav-link" aria-label="View Recommendations">💡 Recommendations</a>
            <a href="logout.php" class="nav-link" aria-label="Sign Out">Sign Out</a>
        </nav>

        <header class="header">
            <h1>Contribution &amp; Helping Others <span class="accent">Results</span></h1>
            <p class="subtitle">Track how you contribute, help, and create meaningful impact over time.</p>
            <a href="contribution_and_helping_others.php" class="btn btn-primary" style="margin-top:0.75rem;display:inline-block;">+ New Assessment</a>
        </header>

        <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
            <div class="alert alert-success" role="alert">✓ &nbsp;Assessment saved successfully!</div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert-error" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="results-layout">

            <!-- ── History Sidebar ── -->
            <aside class="history-sidebar" role="complementary" aria-label="Assessment History">
                <h3>History</h3>
                <?php if (empty($allAssessments)): ?>
                    <p class="empty-state">No assessments yet.<br><a href="contribution_and_helping_others.php">Take your first one →</a></p>
                <?php else: ?>
                    <div class="assessment-list">
                        <?php foreach ($allAssessments as $assessment): ?>
                            <?php
                                $isActive = $selectedAssessment && (int)$selectedAssessment['id'] === (int)$assessment['id'];
                                $interpretation = getScoreInterpretation((int)$assessment['total_score'], 25);
                            ?>
                            <a href="?id=<?= (int)$assessment['id'] ?>"
                               class="assessment-item <?= $isActive ? 'active' : '' ?>"
                               aria-label="View assessment from <?= htmlspecialchars(date('F d, Y', strtotime($assessment['assessment_date']))) ?>"
                               <?= $isActive ? 'aria-current="page"' : '' ?>>
                                <div class="assessment-date">
                                    <?= htmlspecialchars(date('M d, Y', strtotime($assessment['assessment_date']))) ?>
                                </div>
                                <div class="assessment-score">
                                    Score: <strong><?= (int)$assessment['total_score'] ?>/25</strong>
                                </div>
                                <div class="assessment-badge" style="background-color: <?= htmlspecialchars($interpretation['color']) ?>">
                                    <?= htmlspecialchars($interpretation['level']) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>

            <!-- ── Main Content ── -->
            <main class="results-main" role="main">
                <?php if ($selectedAssessment): ?>
                    <?php $interpretation = getScoreInterpretation((int)$selectedAssessment['total_score'], 25); ?>

                    <!-- Overall Score -->
                    <div class="score-card">
                        <div class="score-header">
                            <h2>Overall Score</h2>
                            <div class="score-date">
                                <time datetime="<?= htmlspecialchars($selectedAssessment['assessment_date']) ?>">
                                    <?= htmlspecialchars(date('F d, Y', strtotime($selectedAssessment['assessment_date']))) ?>
                                </time>
                            </div>
                        </div>
                        <div class="score-display">
                            <div class="score-number" style="color: <?= htmlspecialchars($interpretation['color']) ?>">
                                <?= (int)$selectedAssessment['total_score'] ?><span class="score-max">/25</span>
                            </div>
                            <div class="score-interpretation">
                                <div class="score-level" style="background-color: <?= htmlspecialchars($interpretation['color']) ?>">
                                    <?= htmlspecialchars($interpretation['level']) ?>
                                </div>
                                <div class="score-description">
                                    <?= htmlspecialchars($interpretation['description']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="score-bar" role="progressbar" aria-valuenow="<?= (int)$selectedAssessment['total_score'] ?>" aria-valuemin="0" aria-valuemax="25" aria-label="Overall score">
                            <div class="score-bar-fill"
                                 style="width: <?= min(100, max(0, ((int)$selectedAssessment['total_score'] / 25 * 100))) ?>%;
                                        background-color: <?= htmlspecialchars($interpretation['color']) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Category Breakdown -->
                    <div class="breakdown-section">
                        <h2>Category Breakdown</h2>
                        <div class="category-grid">
                            <?php foreach ($categories as $i => $category): ?>
                                <?php
                                    $rating = isset($selectedAssessment[$category['field'] . '_rating'])
                                        ? max(0, min(5, (int)$selectedAssessment[$category['field'] . '_rating']))
                                        : 0;
                                    $percentage = ($rating / 5) * 100;
                                ?>
                                <div class="category-result">
                                    <div class="category-result-header">
                                        <span class="category-result-number"><?= $i + 1 ?></span>
                                        <h3><?= htmlspecialchars($category['name']) ?></h3>
                                        <div class="category-result-score">
                                            <span class="rating-value"><?= $rating ?></span>/5
                                        </div>
                                    </div>
                                    <div class="category-result-bar" role="progressbar" aria-valuenow="<?= $rating ?>" aria-valuemin="0" aria-valuemax="5" aria-label="<?= htmlspecialchars($category['name']) ?> rating">
                                        <div class="category-result-bar-fill" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                    <?php if (!empty($selectedAssessment[$category['field'] . '_notes'])): ?>
                                        <div class="category-result-notes">
                                            <?= nl2br(htmlspecialchars($selectedAssessment[$category['field'] . '_notes'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Action Plan -->
                    <?php if (!empty($selectedAssessment['top_improvement_areas']) || !empty($selectedAssessment['next_steps'])): ?>
                        <div class="action-plan-section">
                            <h2>Your Action Plan</h2>
                            <div class="action-plan-content">
                                <?php if (!empty($selectedAssessment['top_improvement_areas'])): ?>
                                    <div class="action-plan-block">
                                        <h3>Top Areas to Improve</h3>
                                        <p><?= nl2br(htmlspecialchars($selectedAssessment['top_improvement_areas'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($selectedAssessment['next_steps'])): ?>
                                    <div class="action-plan-block">
                                        <h3>Next Steps</h3>
                                        <p><?= nl2br(htmlspecialchars($selectedAssessment['next_steps'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Progress Over Time -->
                    <?php if (count($allAssessments) > 1): ?>
                        <div class="progress-chart-section">
                            <h2>Progress Over Time</h2>
                            <div class="chart-container">
                                <canvas id="progressChart" role="img" aria-label="Progress chart showing score trends over time"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-results">
                        <div class="empty-icon" role="img" aria-label="Empty clipboard">🤝</div>
                        <h2>No Assessment Selected</h2>
                        <p>Select an entry from the history panel, or <a href="contribution_and_helping_others.php">take a new assessment</a>.</p>
                    </div>
                <?php endif; ?>
            </main>

        </div>
    </div>

    <?php if ($selectedAssessment && count($allAssessments) > 1): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            'use strict';

            const assessments = <?= json_encode(array_reverse($allAssessments), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const canvas = document.getElementById('progressChart');

            if (!canvas || !assessments || assessments.length === 0) {
                console.error('Chart initialization failed: missing canvas or data');
                return;
            }

            const ctx = canvas.getContext('2d');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: assessments.map(a => {
                        try {
                            return new Date(a.assessment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                        } catch (e) {
                            return 'Invalid Date';
                        }
                    }),
                    datasets: [{
                        label: 'Total Score',
                        data: assessments.map(a => parseInt(a.total_score) || 0),
                        borderColor: '#C9A84C',
                        backgroundColor: 'rgba(201,168,76,0.08)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: '#C9A84C',
                        pointBorderColor: '#0D1117',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#21293A',
                            borderColor: 'rgba(201,168,76,0.3)',
                            borderWidth: 1,
                            titleColor: '#F0EBE1',
                            bodyColor: '#C9A84C',
                            padding: 12,
                            callbacks: {
                                label: function(ctx) {
                                    return ' Score: ' + ctx.parsed.y + '/25';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: { color: '#4E5D72', font: { size: 12 } },
                            border: { color: 'rgba(255,255,255,0.07)' }
                        },
                        y: {
                            beginAtZero: true,
                            min: 0,
                            max: 25,
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: {
                                color: '#4E5D72',
                                font: { size: 12 },
                                stepSize: 5
                            },
                            border: { color: 'rgba(255,255,255,0.07)' }
                        }
                    }
                }
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
