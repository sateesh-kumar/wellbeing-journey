<?php
require_once __DIR__ . '/bootstrap.php';
Auth::require();

$pdo    = Database::connect();
$userId = Auth::id();
$user   = Auth::user();

$PILLAR_CONFIG = [
    1 => [
        'title' => 'Connection &amp; Love', 'subtitle' => 'Track your wellbeing journey across relationships, love, and community.',
        'table' => 'connection_love_assessments',
        'categories' => [
            ['name' => 'Self-Awareness & Self-Compassion', 'field' => 'self_awareness'],
            ['name' => 'Emotional Connection & Intimacy',  'field' => 'emotional_connection'],
            ['name' => 'Family & Friend Relationships',    'field' => 'family_friends'],
            ['name' => 'Love Expression',                  'field' => 'love_expression'],
            ['name' => 'Community & Belonging',            'field' => 'community'],
        ], 'empty_icon' => '📋',
    ],
    2 => [
        'title' => 'Growth &amp; Learning', 'subtitle' => 'Track your learning mindset, habits, and growth over time.',
        'table' => 'growth_learning_assessments',
        'categories' => [
            ['name' => 'Curiosity & Mindset',    'field' => 'curiosity_mindset'],
            ['name' => 'Skill Building',          'field' => 'skill_building'],
            ['name' => 'Reflection & Learning',   'field' => 'reflection_learning'],
            ['name' => 'Growth Mindset',          'field' => 'growth_mindset'],
            ['name' => 'Purpose & Meaning',       'field' => 'purpose_meaning'],
        ], 'empty_icon' => '📋',
    ],
    3 => [
        'title' => 'Contribution &amp; Helping', 'subtitle' => 'Track how you contribute and create meaningful impact over time.',
        'table' => 'contribution_assessments',
        'categories' => [
            ['name' => 'Proactive Helping',                 'field' => 'proactive_help'],
            ['name' => 'Sharing Knowledge & Mentoring',     'field' => 'knowledge_sharing'],
            ['name' => 'Generosity & Community Engagement', 'field' => 'generosity'],
            ['name' => 'Impact-Oriented Contribution',      'field' => 'impact'],
            ['name' => 'Sustainable Helping & Resilience',  'field' => 'sustainable_service'],
        ], 'empty_icon' => '🤝',
    ],
    4 => [
        'title' => 'Freedom &amp; Autonomy', 'subtitle' => 'Track your sense of freedom, independence, and personal autonomy over time.',
        'table' => 'freedom_autonomy_assessments',
        'categories' => [
            ['name' => 'Time Freedom',       'field' => 'time_freedom'],
            ['name' => 'Decision Freedom',   'field' => 'decision_freedom'],
            ['name' => 'Lifestyle Freedom',  'field' => 'lifestyle_freedom'],
            ['name' => 'Financial Autonomy', 'field' => 'financial_autonomy'],
            ['name' => 'Value Alignment',    'field' => 'value_alignment'],
        ], 'empty_icon' => '🕊️',
    ],
    5 => [
        'title' => 'Security &amp; Certainty', 'subtitle' => 'Track your sense of financial stability, emotional safety, and ability to face uncertainty over time.',
        'table' => 'security_certainty_assessments',
        'categories' => [
            ['name' => 'Financial Security',              'field' => 'financial_security'],
            ['name' => 'Emotional & Psychological Safety','field' => 'emotional_safety'],
            ['name' => 'Health & Physical Security',      'field' => 'health_physical_security'],
            ['name' => 'Stability & Predictability',      'field' => 'stability_predictability'],
            ['name' => 'Sense of Security & Trust',       'field' => 'security_trust'],
        ], 'empty_icon' => '🛡️',
    ],
    6 => [
        'title' => 'Nature &amp; Sustainability', 'subtitle' => 'Track your connection to the natural world and sustainable living habits over time.',
        'table' => 'nature_sustainability_assessments',
        'categories' => [
            ['name' => 'Connection with Nature',               'field' => 'nature_connection'],
            ['name' => 'Sustainable Living Practices',         'field' => 'sustainable_living'],
            ['name' => 'Alignment with Natural Rhythms',       'field' => 'natural_rhythms'],
            ['name' => 'Environmental Awareness & Contribution','field' => 'environmental_awareness'],
            ['name' => 'Nature as a Source of Joy & Restoration','field' => 'nature_restoration'],
        ], 'empty_icon' => '🌿',
    ],
    7 => [
        'title' => 'Achievement &amp; Mastery', 'subtitle' => 'Track how you set goals, develop skills, build confidence, and overcome challenges over time.',
        'table' => 'achievement_mastery_assessments',
        'categories' => [
            ['name' => 'Goal Setting & Progress',              'field' => 'goal_setting'],
            ['name' => 'Skill Development & Mastery',          'field' => 'skill_development'],
            ['name' => 'Competence & Confidence',              'field' => 'competence_confidence'],
            ['name' => 'Recognition & Achievement',            'field' => 'recognitino_achievement'],
            ['name' => 'Overcoming Challenges',               'field' => 'overcoming_challenges'],
        ], 'empty_icon' => '🏆',
    ],
];

$pillarId = isset($_GET['pillar']) && isset($PILLAR_CONFIG[(int)$_GET['pillar']])
    ? (int)$_GET['pillar'] : null;

if (!$pillarId) { header('Location: dashboard.php'); exit; }

$config     = $PILLAR_CONFIG[$pillarId];
$table      = $config['table'];
$categories = $config['categories'];

// Hardcoded rating columns per table (derived from schema)
$ratingColMap = [
    'connection_love_assessments'      => ['self_awareness_rating','emotional_connection_rating','family_friends_rating','love_expression_rating','community_rating'],
    'growth_learning_assessments'      => ['curiosity_mindset_rating','skill_building_rating','reflection_learning_rating','growth_mindset_rating','purpose_meaning_rating'],
    'contribution_assessments'         => ['proactive_help_rating','knowledge_sharing_rating','generosity_rating','impact_rating','sustainable_service_rating'],
    'freedom_autonomy_assessments'     => ['time_freedom_rating','decision_freedom_rating','lifestyle_freedom_rating','financial_autonomy_rating','value_alignment_rating'],
    'security_certainty_assessments'   => ['financial_security_rating','emotional_safety_rating','health_physical_security_rating','stability_predictability_rating','security_trust_rating'],
    'nature_sustainability_assessments'=> ['nature_connection_rating','sustainable_living_rating','natural_rhythms_rating','environmental_awareness_rating','nature_restoration_rating'],
    'achievement_mastery_assessments'  => ['goal_setting_rating','skill_development_rating','competence_confidence_rating','recognition_achievement_rating','overcoming_challenges_rating'],
];
$sumCols   = $ratingColMap[$table] ?? [];
$ratingSum = !empty($sumCols)
    ? implode(' + ', array_map(fn($c) => "COALESCE({$c}, 0)", $sumCols))
    : '0';

function getScoreInterpretation(int $score, int $max = 25): array {
    $pct = $max > 0 ? ($score / $max) * 100 : 0;
    if ($pct >= 90) return ['level' => 'Thriving',    'color' => '#4CAF50', 'description' => 'You are excelling — keep up the great work.'];
    if ($pct >= 75) return ['level' => 'Flourishing', 'color' => '#8BC34A', 'description' => 'Things are strong with just a few areas to nurture.'];
    if ($pct >= 55) return ['level' => 'Growing',     'color' => '#C9A84C', 'description' => 'You\'re making meaningful progress — keep building.'];
    if ($pct >= 35) return ['level' => 'Developing',  'color' => '#FF9800', 'description' => 'Several areas need attention and intentional effort.'];
    return                 ['level' => 'Needs Work',  'color' => '#F44336', 'description' => 'This is a great time to focus on rebuilding your foundations.'];
}

$selectedAssessment = null;
$errorMessage       = null;

$fullQuery = "
    SELECT a.*, COALESCE(ts.total_score, ({$ratingSum})) AS total_score
    FROM {$table} a
    LEFT JOIN total_scores ts
        ON ts.user_id = a.user_id AND ts.tenant_id = a.tenant_id AND ts.pillar_id = {$pillarId}
       AND ts.recorded_at = (
           SELECT MAX(t2.recorded_at) FROM total_scores t2
           WHERE t2.user_id = a.user_id AND t2.tenant_id = a.tenant_id AND t2.pillar_id = {$pillarId}
           AND t2.recorded_at <= a.assessment_date + INTERVAL '1 minute'
       )
    WHERE a.id = ? AND a.user_id = ? AND a.tenant_id = ?
";

try {
    $listStmt = $pdo->prepare("SELECT id, assessment_date, ({$ratingSum}) AS total_score FROM {$table} WHERE user_id = ? AND tenant_id = ? ORDER BY assessment_date DESC");
    $listStmt->execute([$userId, $user['tenant_id']]);
    $allAssessments = $listStmt->fetchAll();

    $lookupId = isset($_GET['id']) && is_numeric($_GET['id'])
        ? (int)$_GET['id']
        : ($allAssessments[0]['id'] ?? null);

    if ($lookupId) {
        $stmt = $pdo->prepare($fullQuery);
        $stmt->execute([$lookupId, $userId, $user['tenant_id']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['user_id'] === $userId) {
            $selectedAssessment = $row;
        } elseif (isset($_GET['id'])) {
            $errorMessage = 'Access denied.';
        }
    }
} catch (PDOException $e) {
    error_log("view_results.php pillar={$pillarId}: " . $e->getMessage());
    $errorMessage = 'Could not load assessments: ' . htmlspecialchars($e->getMessage());
}

$pageTitle = html_entity_decode($config['title']) . ' — Results';
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
        /* ── Navbar ─────────────────────────────────────────────── */
        .topnav { position:sticky; top:0; z-index:100; background:rgba(13,17,23,0.88); backdrop-filter:blur(16px); border-bottom:1px solid rgba(255,255,255,0.07); padding:0 32px; display:flex; align-items:center; justify-content:space-between; height:62px; }
        .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .nav-brand-icon { width:32px; height:32px; background:rgba(201,168,76,0.12); border:1px solid rgba(201,168,76,0.25); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#C9A84C; }
        .nav-brand-name { font-family:'Playfair Display',serif; font-size:1rem; font-weight:600; color:#F0EBE1; }
        .nav-brand-name span { color:#C9A84C; }
        .nav-right { display:flex; align-items:center; gap:20px; }
        .nav-link { font-size:0.875rem; color:#8A9BB0; text-decoration:none; transition:color 0.15s; }
        .nav-link:hover { color:#F0EBE1; }
        .nav-link.active { color:#C9A84C; }
        .nav-user { display:flex; align-items:center; gap:9px; }
        .nav-avatar { width:30px; height:30px; border-radius:50%; background:rgba(201,168,76,0.12); border:1px solid rgba(201,168,76,0.3); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; color:#C9A84C; }
        .nav-name { font-size:0.875rem; color:#F0EBE1; }
        .btn-logout { font-size:0.8rem; color:#8A9BB0; text-decoration:none; background:#1A2332; border:1px solid rgba(255,255,255,0.07); border-radius:7px; padding:5px 12px; transition:color 0.15s,border-color 0.15s; }
        .btn-logout:hover { color:#F0EBE1; border-color:#8A9BB0; }
        .alert-error { background-color:rgba(244,67,54,0.1); border:1px solid #F44336; color:#F44336; padding:1rem; border-radius:8px; margin-bottom:1.5rem; }

        /* ── Hamburger toggle ───────────────────────────────────── */
        .nav-mobile-toggle { display:none; flex-direction:column; justify-content:center; gap:5px; background:none; border:none; cursor:pointer; padding:6px 4px; color:#F0EBE1; }
        .nav-mobile-toggle span { display:block; width:22px; height:2px; background:currentColor; border-radius:2px; transition:transform 0.25s, opacity 0.25s; }
        .nav-mobile-toggle.open span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
        .nav-mobile-toggle.open span:nth-child(2) { opacity:0; }
        .nav-mobile-toggle.open span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }

        /* ── Mobile nav drawer ──────────────────────────────────── */
        .mobile-nav-drawer { display:none; position:fixed; top:62px; left:0; right:0; bottom:0; background:rgba(13,17,23,0.98); backdrop-filter:blur(16px); z-index:99; padding:8px 0; flex-direction:column; overflow-y:auto; }
        .mobile-nav-drawer.open { display:flex; animation:mnFadeIn 0.18s ease; }
        @keyframes mnFadeIn { from{opacity:0} to{opacity:1} }
        .mobile-nav-item { font-size:1rem; font-weight:500; color:#8A9BB0; text-decoration:none; padding:16px 24px; border-bottom:1px solid rgba(255,255,255,0.07); transition:color 0.15s,background 0.15s; display:block; }
        .mobile-nav-item:hover { color:#F0EBE1; background:rgba(255,255,255,0.04); }
        .mobile-nav-item.active { color:#C9A84C; }
        .mobile-nav-signout { color:rgba(255,255,255,0.35) !important; }

        /* ── Mobile history toggle button ───────────────────────── */
        .history-toggle { display:none; width:100%; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:12px 16px; color:#F0EBE1; font-family:inherit; font-size:0.875rem; font-weight:500; cursor:pointer; text-align:left; align-items:center; justify-content:space-between; margin-bottom:12px; transition:background 0.15s; }
        .history-toggle:hover { background:rgba(255,255,255,0.07); }
        .history-toggle-chevron { font-size:10px; color:#8A9BB0; transition:transform 0.25s; }
        .history-toggle.open .history-toggle-chevron { transform:rotate(180deg); }

        /* ── Tablet ─────────────────────────────────────────────── */
        @media (max-width:900px) {
            .container { padding-left:20px !important; padding-right:20px !important; }
            .results-layout { gap:16px !important; }
        }

        /* ── Mobile ─────────────────────────────────────────────── */
        @media (max-width:768px) {
            .topnav { padding:0 16px; height:56px; }
            .mobile-nav-drawer { top:56px; }
            .nav-link { display:none; }
            .nav-name { display:none; }
            .btn-logout { display:none; }
            .nav-mobile-toggle { display:flex; }
            .nav-right { gap:10px; }

            .container { padding:16px 14px 40px !important; max-width:100% !important; }
            .header { padding:16px 0 14px !important; }
            .header h1 { font-size:1.6rem !important; line-height:1.2; }
            .subtitle { font-size:0.875rem !important; }

            /* stack sidebar above main */
            .results-layout { display:flex !important; flex-direction:column !important; gap:0 !important; }

            /* history sidebar: collapsible strip */
            .history-sidebar { width:100% !important; min-width:unset !important; border-right:none !important; border-bottom:1px solid rgba(255,255,255,0.07) !important; padding-right:0 !important; margin-bottom:20px !important; padding-bottom:8px !important; }
            .history-sidebar h3 { display:none; }
            .history-toggle { display:flex; }
            .history-sidebar-body { display:none; }
            .history-sidebar-body.open { display:block; animation:mnFadeIn 0.18s ease; }
            .assessment-list { margin-top:8px !important; margin-bottom:4px !important; }
            .assessment-item { padding:10px 12px !important; border-radius:8px !important; display:flex !important; align-items:center !important; gap:10px !important; flex-wrap:wrap !important; }
            .assessment-date { font-size:0.8rem !important; flex:1 !important; }
            .assessment-score { font-size:0.8rem !important; }
            .assessment-badge { font-size:0.65rem !important; padding:2px 8px !important; }

            /* main */
            .results-main { padding-left:0 !important; }

            /* score card */
            .score-card { padding:16px !important; border-radius:12px !important; }
            .score-header { flex-direction:column !important; gap:4px !important; align-items:flex-start !important; }
            .score-display { flex-direction:column !important; align-items:flex-start !important; gap:10px !important; }
            .score-number { font-size:2.8rem !important; }
            .score-interpretation { width:100% !important; }

            /* category breakdown */
            .breakdown-section { padding:16px !important; border-radius:12px !important; margin-top:16px !important; }
            .breakdown-section h2 { font-size:1rem !important; margin-bottom:12px !important; }
            .category-grid { grid-template-columns:1fr !important; gap:10px !important; }
            .category-result { padding:12px !important; border-radius:10px !important; }
            .category-result-header { gap:8px !important; }
            .category-result-number { width:24px !important; height:24px !important; font-size:0.7rem !important; flex-shrink:0; }
            .category-result-header h3 { font-size:0.875rem !important; }
            .category-result-score { font-size:0.875rem !important; flex-shrink:0; }

            /* action plan */
            .action-plan-section { padding:16px !important; border-radius:12px !important; margin-top:16px !important; }
            .action-plan-section h2 { font-size:1rem !important; margin-bottom:12px !important; }
            .action-plan-content { flex-direction:column !important; gap:12px !important; }
            .action-plan-block { padding:12px !important; border-radius:10px !important; }
            .action-plan-block h3 { font-size:0.875rem !important; margin-bottom:6px !important; }
            .action-plan-block p { font-size:0.875rem !important; line-height:1.6 !important; }

            /* chart */
            .progress-chart-section { padding:16px !important; border-radius:12px !important; margin-top:16px !important; }
            .progress-chart-section h2 { font-size:1rem !important; margin-bottom:12px !important; }
            .chart-container { height:180px !important; }
        }

        /* ── Small mobile ───────────────────────────────────────── */
        @media (max-width:480px) {
            .container { padding:12px 12px 32px !important; }
            .header h1 { font-size:1.35rem !important; }
            .score-number { font-size:2.4rem !important; }
            .assessment-item { flex-direction:column !important; align-items:flex-start !important; gap:4px !important; }
        }
    </style></head>
<body>
    <nav class="topnav">
        <a href="dashboard.php" class="nav-brand">
            <div class="nav-brand-icon">✦</div>
            <span class="nav-brand-name">Mental Wellbeing <span>Audit</span></span>
        </a>
        <div class="nav-right">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="view_recommendations.php" class="nav-link">Recommendations</a>
            <a href="journal.php" class="nav-link">Journal</a>
            <div class="nav-user">
                <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
                <span class="nav-name"><?= htmlspecialchars($user['name']) ?></span>
                <a href="logout.php" class="btn-logout">Sign out</a>
            </div>
            <button class="nav-mobile-toggle" id="nav-mobile-toggle" onclick="toggleMobileNav(this)" aria-label="Toggle menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Mobile nav drawer -->
    <div class="mobile-nav-drawer" id="mobile-nav-drawer">
        <a href="dashboard.php" class="mobile-nav-item">Dashboard</a>
        <a href="view_recommendations.php" class="mobile-nav-item">Recommendations</a>
        <a href="journal.php" class="mobile-nav-item">Journal</a>
        <a href="logout.php" class="mobile-nav-item mobile-nav-signout">Sign out</a>
    </div>
    <div class="container">

        <header class="header">
            <h1><?= $config['title'] ?> <span class="accent">Results</span></h1>
            <p class="subtitle"><?= htmlspecialchars($config['subtitle']) ?></p>
            <a href="combined_assessment.php?pillar=<?= $pillarId ?>" class="btn btn-primary" style="margin-top:0.75rem;display:inline-block;">+ New Assessment</a>
        </header>

        <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
            <div class="alert alert-success">✓ &nbsp;Assessment saved successfully!</div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="results-layout">
            <aside class="history-sidebar">
                <h3>History</h3>
                <button class="history-toggle" id="history-toggle" onclick="toggleHistory(this)" aria-expanded="false">
                    <span>📋 Assessment History (<?= count($allAssessments) ?>)</span>
                    <span class="history-toggle-chevron">▾</span>
                </button>
                <div class="history-sidebar-body" id="history-sidebar-body">
                <?php if (empty($allAssessments)): ?>
                    <p class="empty-state">No assessments yet.<br><a href="combined_assessment.php?pillar=<?= $pillarId ?>">Take your first one →</a></p>
                <?php else: ?>
                    <div class="assessment-list">
                        <?php foreach ($allAssessments as $assessment):
                            $isActive = $selectedAssessment && (int)$selectedAssessment['id'] === (int)$assessment['id'];
                            $interp   = getScoreInterpretation((int)$assessment['total_score'], 25);
                        ?>
                            <a href="?pillar=<?= $pillarId ?>&id=<?= (int)$assessment['id'] ?>"
                               class="assessment-item <?= $isActive ? 'active' : '' ?>">
                                <div class="assessment-date"><?= htmlspecialchars(date('M d, Y', strtotime($assessment['assessment_date']))) ?></div>
                                <div class="assessment-score">Score: <strong><?= (int)$assessment['total_score'] ?>/25</strong></div>
                                <div class="assessment-badge" style="background-color:<?= htmlspecialchars($interp['color']) ?>"><?= htmlspecialchars($interp['level']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </aside>

            <main class="results-main">
                <?php if ($selectedAssessment):
                    $interp = getScoreInterpretation((int)$selectedAssessment['total_score'], 25); ?>
                    <div class="score-card">
                        <div class="score-header">
                            <h2>Overall Score</h2>
                            <div class="score-date"><time datetime="<?= htmlspecialchars($selectedAssessment['assessment_date']) ?>"><?= htmlspecialchars(date('F d, Y', strtotime($selectedAssessment['assessment_date']))) ?></time></div>
                        </div>
                        <div class="score-display">
                            <div class="score-number" style="color:<?= htmlspecialchars($interp['color']) ?>"><?= (int)$selectedAssessment['total_score'] ?><span class="score-max">/25</span></div>
                            <div class="score-interpretation">
                                <div class="score-level" style="background-color:<?= htmlspecialchars($interp['color']) ?>"><?= htmlspecialchars($interp['level']) ?></div>
                                <div class="score-description"><?= htmlspecialchars($interp['description']) ?></div>
                            </div>
                        </div>
                        <div class="score-bar">
                            <div class="score-bar-fill" style="width:<?= min(100, max(0, (int)$selectedAssessment['total_score'] / 25 * 100)) ?>%;background-color:<?= htmlspecialchars($interp['color']) ?>"></div>
                        </div>
                    </div>

                    <div class="breakdown-section">
                        <h2>Category Breakdown</h2>
                        <div class="category-grid">
                            <?php foreach ($categories as $i => $category):
                                $ratingKey = $category['field'] . '_rating';
                                if (isset($selectedAssessment[$ratingKey]) && $selectedAssessment[$ratingKey] !== null) {
                                    $rating = max(0, min(5, (int)$selectedAssessment[$ratingKey]));
                                } else {
                                    $qVals  = array_filter(array_map(fn($k) => isset($selectedAssessment[$k]) ? (int)$selectedAssessment[$k] : null, array_filter(array_keys($selectedAssessment), fn($k) => preg_match('/^' . preg_quote($category['field'], '/') . '_q\d+$/', $k))), fn($v) => $v !== null);
                                    $rating = !empty($qVals) ? max(0, min(5, (int)round(array_sum($qVals) / count($qVals)))) : 0;
                                }
                                $percentage = ($rating / 5) * 100;
                            ?>
                                <div class="category-result">
                                    <div class="category-result-header">
                                        <span class="category-result-number"><?= $i + 1 ?></span>
                                        <h3><?= htmlspecialchars($category['name']) ?></h3>
                                        <div class="category-result-score"><span class="rating-value"><?= $rating ?></span>/5</div>
                                    </div>
                                    <div class="category-result-bar"><div class="category-result-bar-fill" style="width:<?= $percentage ?>%"></div></div>
                                    <?php if (!empty($selectedAssessment[$category['field'] . '_notes'])): ?>
                                        <div class="category-result-notes"><?= nl2br(htmlspecialchars($selectedAssessment[$category['field'] . '_notes'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!empty($selectedAssessment['top_improvement_areas']) || !empty($selectedAssessment['next_steps'])): ?>
                        <div class="action-plan-section">
                            <h2>Your Action Plan</h2>
                            <div class="action-plan-content">
                                <?php if (!empty($selectedAssessment['top_improvement_areas'])): ?>
                                    <div class="action-plan-block"><h3>Top Areas to Improve</h3><p><?= nl2br(htmlspecialchars($selectedAssessment['top_improvement_areas'])) ?></p></div>
                                <?php endif; ?>
                                <?php if (!empty($selectedAssessment['next_steps'])): ?>
                                    <div class="action-plan-block"><h3>Next Steps</h3><p><?= nl2br(htmlspecialchars($selectedAssessment['next_steps'])) ?></p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (count($allAssessments) > 1): ?>
                        <div class="progress-chart-section">
                            <h2>Progress Over Time</h2>
                            <div class="chart-container"><canvas id="progressChart"></canvas></div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-results">
                        <div class="empty-icon"><?= $config['empty_icon'] ?></div>
                        <h2>No Assessment Selected</h2>
                        <p>Select an entry from the history panel, or <a href="combined_assessment.php?pillar=<?= $pillarId ?>">take a new assessment</a>.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($selectedAssessment && count($allAssessments) > 1): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const assessments = <?= json_encode(array_reverse($allAssessments), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const canvas = document.getElementById('progressChart');
            if (!canvas || !assessments.length) return;
            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: assessments.map(a => { try { return new Date(a.assessment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }); } catch (e) { return ''; } }),
                    datasets: [{ label: 'Total Score', data: assessments.map(a => parseInt(a.total_score) || 0), borderColor: '#C9A84C', backgroundColor: 'rgba(201,168,76,0.08)', tension: 0.4, fill: true, pointRadius: 5, pointBackgroundColor: '#C9A84C', pointBorderColor: '#0D1117', pointBorderWidth: 2, pointHoverRadius: 7 }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#21293A', borderColor: 'rgba(201,168,76,0.3)', borderWidth: 1, titleColor: '#F0EBE1', bodyColor: '#C9A84C', padding: 12, callbacks: { label: ctx => ' Score: ' + ctx.parsed.y + '/25' } } },
                    scales: {
                        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#4E5D72', font: { size: 12 } }, border: { color: 'rgba(255,255,255,0.07)' } },
                        y: { beginAtZero: true, min: 0, max: 25, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#4E5D72', font: { size: 12 }, stepSize: 5 }, border: { color: 'rgba(255,255,255,0.07)' } }
                    }
                }
            });
        })();
    </script>
    <?php endif; ?>

    <script>
        function toggleMobileNav(btn) {
            const drawer = document.getElementById('mobile-nav-drawer');
            const isOpen = drawer.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
            btn.setAttribute('aria-expanded', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        function toggleHistory(btn) {
            const body   = document.getElementById('history-sidebar-body');
            const isOpen = body.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
            btn.setAttribute('aria-expanded', isOpen);
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const drawer = document.getElementById('mobile-nav-drawer');
                const toggle = document.getElementById('nav-mobile-toggle');
                if (drawer) { drawer.classList.remove('open'); }
                if (toggle) { toggle.classList.remove('open'); }
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>
