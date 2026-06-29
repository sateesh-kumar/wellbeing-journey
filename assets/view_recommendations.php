<?php
require_once 'config.php';
require_once 'recommendations_engine.php';
requireAuth();

$pdo  = getDBConnection();
$user = currentUser();

// ── Fetch latest Connection & Love assessment ─────────────────────────────────
$stmt = $pdo->prepare("
    SELECT cla.*,
           (SELECT total_score FROM total_scores
            WHERE user_id = cla.user_id AND pillar_id = 1
            ORDER BY recorded_at DESC LIMIT 1) AS total_score
    FROM connection_love_assessments cla
    WHERE cla.user_id = ?
    ORDER BY cla.assessment_date DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$latestConnection = $stmt->fetch();

// ── Fetch latest Growth & Learning assessment ─────────────────────────────────
$stmt = $pdo->prepare("
    SELECT gla.*,
           (SELECT total_score FROM total_scores
            WHERE user_id = gla.user_id AND pillar_id = 2
            ORDER BY recorded_at DESC LIMIT 1) AS total_score
    FROM growth_learning_assessments gla
    WHERE gla.user_id = ?
    ORDER BY gla.assessment_date DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$latestGrowth = $stmt->fetch();

// ââ Fetch latest Contribution & Helping Others assessment
$stmtC = $pdo->prepare("
    SELECT ca.*,
           (SELECT total_score FROM total_scores
            WHERE user_id = ca.user_id AND pillar_id = 3
            ORDER BY recorded_at DESC LIMIT 1) AS total_score
    FROM contribution_assessments ca
    WHERE ca.user_id = ?
    ORDER BY ca.assessment_date DESC
    LIMIT 1
");
$stmtC->execute([$user['id']]);
$latestContribution = $stmtC->fetch();

// ── Build recommendations ─────────────────────────────────────────────────────
$connectionRecs = $latestConnection
    ? RecommendationsEngine::getConnectionRecommendations($latestConnection)
    : null;

$growthRecs = $latestGrowth
    ? RecommendationsEngine::getGrowthRecommendations($latestGrowth)
    : null;

$contributionRecs = $latestContribution
    ? RecommendationsEngine::getContributionRecommendations($latestContribution)
    : null;

$hasConnection = $connectionRecs && (!empty($connectionRecs['priority']) || !empty($connectionRecs['maintain']) || !empty($connectionRecs['celebrate']));
$hasGrowth     = $growthRecs    && (!empty($growthRecs['priority'])    || !empty($growthRecs['maintain'])    || !empty($growthRecs['celebrate']));
$hasContribution = $contributionRecs && (!empty($contributionRecs['priority']) || !empty($contributionRecs['maintain']) || !empty($contributionRecs['celebrate']));

// ── Active pillar tab (default: first available) ──────────────────────────────
$activeTab = $_GET['pillar'] ?? ($hasConnection ? 'connection' : ($hasGrowth ? 'growth' : ($hasContribution ? 'contribution' : 'connection')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendations — Happiness Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0D1117;
            --surface:   #141B24;
            --surface2:  #1A2332;
            --border:    rgba(255,255,255,0.07);
            --border2:   rgba(255,255,255,0.04);
            --gold:      #C9A84C;
            --gold-dim:  rgba(201,168,76,0.12);
            --green:     #4CAF7D;
            --text:      #F0EBE1;
            --muted:     #4E5D72;
            --muted2:    #8A9BB0;
            --radius:    14px;
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-weight: 400;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; right: 0;
            width: 700px; height: 700px;
            background: radial-gradient(circle at 80% 20%, rgba(201,168,76,0.05) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Top nav ── */
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(13,17,23,0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            display: flex; align-items: center; justify-content: space-between;
            height: 62px;
        }
        .nav-brand {
            display: flex; align-items: center; gap: 10px; text-decoration: none;
        }
        .nav-brand-icon {
            width: 32px; height: 32px;
            background: var(--gold-dim);
            border: 1px solid rgba(201,168,76,0.25);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: var(--gold);
        }
        .nav-brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1rem; font-weight: 600; color: var(--text);
        }
        .nav-brand-name span { color: var(--gold); }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .nav-link {
            font-size: 0.875rem; color: var(--muted2);
            text-decoration: none; transition: color 0.15s;
        }
        .nav-link:hover { color: var(--text); }
        .nav-link.active { color: var(--gold); }
        .nav-user { display: flex; align-items: center; gap: 9px; }
        .nav-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--gold-dim); border: 1px solid rgba(201,168,76,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600; color: var(--gold); letter-spacing: 0.03em;
        }
        .nav-name { font-size: 0.875rem; color: var(--muted2); }
        .btn-logout {
            font-size: 0.8rem; color: var(--muted); text-decoration: none;
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: 7px; padding: 5px 12px;
            transition: color 0.15s, border-color 0.15s;
        }
        .btn-logout:hover { color: var(--text); border-color: var(--muted); }

        /* ── Page ── */
        .page {
            max-width: 860px; margin: 0 auto;
            padding: 48px 32px 80px;
            position: relative; z-index: 1;
        }

        /* ── Header ── */
        .page-header { margin-bottom: 40px; }
        .page-eyebrow {
            font-size: 0.78rem; font-weight: 500;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--gold); margin-bottom: 10px;
        }
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 700; line-height: 1.15;
            letter-spacing: -0.02em; color: var(--text); margin-bottom: 10px;
        }
        .page-sub {
            font-size: 0.925rem; color: var(--muted2); line-height: 1.7; max-width: 500px;
        }

        /* ── Pillar tabs ── */
        .pillar-tabs {
            display: flex; gap: 10px; margin-bottom: 40px; flex-wrap: wrap;
        }
        .pillar-tab {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 10px;
            font-size: 0.875rem; font-weight: 500;
            text-decoration: none; border: 1px solid var(--border);
            background: var(--surface); color: var(--muted2);
            transition: all 0.2s;
        }
        .pillar-tab:hover { color: var(--text); border-color: var(--muted); }
        .pillar-tab.active {
            background: var(--gold-dim);
            border-color: rgba(201,168,76,0.4);
            color: var(--gold);
        }
        .pillar-tab.active-green {
            background: rgba(76,175,125,0.1);
            border-color: rgba(76,175,125,0.4);
            color: var(--green);
        }
        .pillar-tab-dot {
            width: 8px; height: 8px; border-radius: 50%;
        }
        .pillar-tab.active-contribution {
            background: rgba(123,158,217,0.1);
            border-color: rgba(123,158,217,0.4);
            color: #7B9ED9;
        }
        .pillar-tab.disabled {
            opacity: 0.4; pointer-events: none; cursor: default;
        }

        /* ── No data state ── */
        .empty-state {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 56px 40px;
            text-align: center;
        }
        .empty-state-icon { font-size: 2.5rem; margin-bottom: 16px; }
        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem; font-weight: 600;
            color: var(--text); margin-bottom: 8px;
        }
        .empty-state p { font-size: 0.9rem; color: var(--muted2); line-height: 1.6; }
        .empty-state a { color: var(--gold); text-decoration: none; }
        .empty-state a:hover { text-decoration: underline; }

        /* ── Assessment context bar ── */
        .context-bar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 22px;
            display: flex; align-items: center; gap: 16px;
            margin-bottom: 32px; flex-wrap: wrap;
        }
        .context-bar-label {
            font-size: 0.75rem; font-weight: 500;
            letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--muted); flex-shrink: 0;
        }
        .context-bar-date { font-size: 0.875rem; color: var(--muted2); flex: 1; }
        .context-bar-link {
            font-size: 0.8rem; color: var(--gold);
            text-decoration: none; font-weight: 500; flex-shrink: 0;
        }
        .context-bar-link:hover { text-decoration: underline; }

        /* ── Recommendation groups ── */
        .rec-group { margin-bottom: 40px; }
        .rec-group:last-child { margin-bottom: 0; }

        .rec-group-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px; padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .rec-group-icon {
            width: 36px; height: 36px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .rec-group-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.05rem; font-weight: 600; color: var(--text);
        }
        .rec-group-count {
            margin-left: auto;
            font-size: 0.75rem; color: var(--muted);
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: 99px; padding: 2px 10px;
        }

        /* priority */
        .rec-group.priority .rec-group-icon { background: rgba(212,114,106,0.12); }
        /* maintain */
        .rec-group.maintain .rec-group-icon { background: rgba(224,140,74,0.12); }
        /* celebrate */
        .rec-group.celebrate .rec-group-icon { background: rgba(76,175,125,0.12); }

        /* ── Recommendation cards ── */
        .rec-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 22px;
            margin-bottom: 12px;
        }
        .rec-card:last-child { margin-bottom: 0; }
        .priority  .rec-card { border-left-color: #D4726A; }
        .maintain  .rec-card { border-left-color: #E08C4A; }
        .celebrate .rec-card { border-left-color: #4CAF7D; }

        .rec-card-header {
            display: flex; align-items: center;
            justify-content: space-between; gap: 12px;
            margin-bottom: 14px;
        }
        .rec-card-title {
            font-size: 0.9rem; font-weight: 600; color: var(--text);
        }
        .rating-badge {
            font-size: 0.75rem; font-weight: 600;
            padding: 3px 10px; border-radius: 99px; flex-shrink: 0;
        }
        .rating-badge.low  { background: rgba(212,114,106,0.12); color: #D4726A; border: 1px solid rgba(212,114,106,0.25); }
        .rating-badge.mid  { background: rgba(224,140,74,0.12);  color: #E08C4A; border: 1px solid rgba(224,140,74,0.25); }
        .rating-badge.high { background: rgba(76,175,125,0.12);  color: #4CAF7D; border: 1px solid rgba(76,175,125,0.25); }

        .rec-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
        .rec-list li {
            display: flex; gap: 10px;
            font-size: 0.875rem; color: var(--muted2); line-height: 1.6;
        }
        .rec-list li::before {
            content: '→'; color: var(--gold);
            font-weight: 600; flex-shrink: 0; margin-top: 1px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .topnav { padding: 0 20px; }
            .page   { padding: 32px 20px 60px; }
            .nav-name { display: none; }
        }
    </style>
</head>
<body>

<!-- ── Top Nav ── -->
<nav class="topnav">
    <a href="dashboard.php" class="nav-brand">
        <div class="nav-brand-icon">✦</div>
        <span class="nav-brand-name">Happiness <span>Audit</span></span>
    </a>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="view_recommendations.php" class="nav-link active">Recommendations</a>
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
            <span class="nav-name"><?= htmlspecialchars($user['name']) ?></span>
            <a href="logout.php" class="btn-logout">Sign out</a>
        </div>
    </div>
</nav>

<!-- ── Page ── -->
<main class="page">

    <div class="page-header">
        <p class="page-eyebrow">Personalised for you</p>
        <h1 class="page-title">Recommendations</h1>
        <p class="page-sub">Specific, actionable suggestions drawn from your most recent assessments — organised by what needs the most focus.</p>
    </div>

    <!-- Pillar tabs -->
    <div class="pillar-tabs">
        <a href="?pillar=connection"
           class="pillar-tab <?= $activeTab === 'connection' ? 'active' : '' ?> <?= !$hasConnection ? 'disabled' : '' ?>">
            <span class="pillar-tab-dot" style="background:#C9A84C"></span>
            Connection & Love
            <?php if (!$hasConnection): ?><span style="font-size:0.7rem;opacity:0.6">— no data</span><?php endif; ?>
        </a>
        <a href="?pillar=growth"
           class="pillar-tab <?= $activeTab === 'growth' ? 'active-green' : '' ?> <?= !$hasGrowth ? 'disabled' : '' ?>">
            <span class="pillar-tab-dot" style="background:#4CAF7D"></span>
            Growth & Learning
            <?php if (!$hasGrowth): ?><span style="font-size:0.7rem;opacity:0.6">— no data</span><?php endif; ?>
        </a>
        <a href="?pillar=contribution"
           class="pillar-tab <?= $activeTab === 'contribution' ? 'active-contribution' : '' ?> <?= !$hasContribution ? 'disabled' : '' ?>">
            <span class="pillar-tab-dot" style="background:#7B9ED9"></span>
            Contribution &amp; Helping
            <?php if (!$hasContribution): ?><span style="font-size:0.7rem;opacity:0.6">â no data</span><?php endif; ?>
        </a>
    </div>

    <?php
    // Decide which data set to render
    if ($activeTab === 'contribution') {
        $recs        = $contributionRecs;
        $latest      = $latestContribution;
        $hasRecs     = $hasContribution;
        $resultsLink = 'view_results_contribution.php';
        $assessLink  = 'contribution_and_helping.php';
        $pillarLabel = 'Contribution &amp; Helping Others';
    } elseif ($activeTab === 'growth') {
        $recs        = $growthRecs;
        $latest      = $latestGrowth;
        $hasRecs     = $hasGrowth;
        $resultsLink = 'view_results_growth.php';
        $assessLink  = 'growth_and_learning.php';
        $pillarLabel = 'Growth &amp; Learning';
    } else {
        $recs        = $connectionRecs;
        $latest      = $latestConnection;
        $hasRecs     = $hasConnection;
        $resultsLink = 'view_results_connection.php';
        $assessLink  = 'connection_and_love.php';
        $pillarLabel = 'Connection &amp; Love';
    }
    $maxScore = 25;
    ?>

    <?php if (!$hasRecs): ?>
        <div class="empty-state">
            <div class="empty-state-icon">💡</div>
            <h3>No recommendations yet</h3>
            <p>
                Complete a
                <a href="<?= $assessLink ?>">
                    <?= $pillarLabel ?>
                </a>
                assessment to generate your personalised recommendations.
            </p>
        </div>

    <?php else: ?>

        <!-- Context bar -->
        <div class="context-bar">
            <span class="context-bar-label">Based on</span>
            <span class="context-bar-date">
                <?= $activeTab === 'growth' ? 'Growth &amp; Learning' : 'Connection &amp; Love' ?> —
                <?= date('F j, Y', strtotime($latest['assessment_date'])) ?>
                &nbsp;·&nbsp; Score: <strong><?= $latest['total_score'] ?>/25</strong>
            </span>
            <a href="<?= $resultsLink ?>" class="context-bar-link">View full results →</a>
        </div>

        <!-- Priority -->
        <?php if (!empty($recs['priority'])): ?>
        <div class="rec-group priority">
            <div class="rec-group-header">
                <div class="rec-group-icon">🎯</div>
                <span class="rec-group-title">Focus Here First</span>
                <span class="rec-group-count"><?= count($recs['priority']) ?> area<?= count($recs['priority']) !== 1 ? 's' : '' ?></span>
            </div>
            <?php foreach ($recs['priority'] as $rec): ?>
            <div class="rec-card">
                <div class="rec-card-header">
                    <span class="rec-card-title"><?= htmlspecialchars($rec['category']) ?></span>
                    <span class="rating-badge low">Rating <?= $rec['rating'] ?>/5</span>
                </div>
                <ul class="rec-list">
                    <?php foreach ($rec['suggestions'] as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Maintain -->
        <?php if (!empty($recs['maintain'])): ?>
        <div class="rec-group maintain">
            <div class="rec-group-header">
                <div class="rec-group-icon">📈</div>
                <span class="rec-group-title">Keep Building</span>
                <span class="rec-group-count"><?= count($recs['maintain']) ?> area<?= count($recs['maintain']) !== 1 ? 's' : '' ?></span>
            </div>
            <?php foreach ($recs['maintain'] as $rec): ?>
            <div class="rec-card">
                <div class="rec-card-header">
                    <span class="rec-card-title"><?= htmlspecialchars($rec['category']) ?></span>
                    <span class="rating-badge mid">Rating <?= $rec['rating'] ?>/5</span>
                </div>
                <ul class="rec-list">
                    <?php foreach (array_slice($rec['suggestions'], 0, 2) as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Celebrate -->
        <?php if (!empty($recs['celebrate'])): ?>
        <div class="rec-group celebrate">
            <div class="rec-group-header">
                <div class="rec-group-icon">⭐</div>
                <span class="rec-group-title">Celebrate Your Strengths</span>
                <span class="rec-group-count"><?= count($recs['celebrate']) ?> area<?= count($recs['celebrate']) !== 1 ? 's' : '' ?></span>
            </div>
            <?php foreach ($recs['celebrate'] as $rec): ?>
            <div class="rec-card">
                <div class="rec-card-header">
                    <span class="rec-card-title"><?= htmlspecialchars($rec['category']) ?></span>
                    <span class="rating-badge high">Rating <?= $rec['rating'] ?>/5</span>
                </div>
                <ul class="rec-list">
                    <?php foreach (array_slice($rec['suggestions'], 0, 2) as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</main>
</body>
</html>
