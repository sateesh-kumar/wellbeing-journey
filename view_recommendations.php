<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/recommendations_engine.php';
Auth::require();

$pdo  = Database::connect();
$user = Auth::user();
$uid  = Auth::id();

// ── Fetch latest assessment per pillar ────────────────────────────────────────
$pillars = [
    1 => ['alias' => 'cla', 'table' => 'connection_love_assessments',       'label' => 'connection'],
    2 => ['alias' => 'gla', 'table' => 'growth_learning_assessments',       'label' => 'growth'],
    3 => ['alias' => 'ca',  'table' => 'contribution_assessments',          'label' => 'contribution'],
    4 => ['alias' => 'faa', 'table' => 'freedom_autonomy_assessments',      'label' => 'freedom'],
    5 => ['alias' => 'sca', 'table' => 'security_certainty_assessments',    'label' => 'security'],
    6 => ['alias' => 'nsa', 'table' => 'nature_sustainability_assessments', 'label' => 'nature'],
    7 => ['alias' => 'ama', 'table' => 'achievement_mastery_assessments',   'label' => 'achievement'],
];

$latest = [];
foreach ($pillars as $id => $p) {
    $a = $p['alias'];
    $stmt = $pdo->prepare("
        SELECT {$a}.*, ts.total_score
        FROM {$p['table']} {$a}
        LEFT JOIN LATERAL (
            SELECT total_score FROM total_scores
            WHERE user_id = {$a}.user_id AND tenant_id = {$a}.tenant_id AND pillar_id = {$id}
            ORDER BY recorded_at DESC LIMIT 1
        ) ts ON true
        WHERE {$a}.user_id = ? AND {$a}.tenant_id = ?
        ORDER BY {$a}.assessment_date DESC LIMIT 1
    ");
    $stmt->execute([$uid, $user['tenant_id']]);
    $latest[$p['label']] = $stmt->fetch();
}

// ── Build recommendations ─────────────────────────────────────────────────────
$recs = [
    'connection'   => $latest['connection']   ? RecommendationsEngine::getConnectionRecommendations($latest['connection'])       : null,
    'growth'       => $latest['growth']       ? RecommendationsEngine::getGrowthRecommendations($latest['growth'])               : null,
    'contribution' => $latest['contribution'] ? RecommendationsEngine::getContributionRecommendations($latest['contribution'])   : null,
    'freedom'      => $latest['freedom']      ? RecommendationsEngine::getFreedomRecommendations($latest['freedom'])             : null,
    'security'     => $latest['security']     ? RecommendationsEngine::getSecurityRecommendations($latest['security'])           : null,
    'nature'       => $latest['nature']       ? RecommendationsEngine::getNatureRecommendations($latest['nature'])               : null,
    'achievement'  => $latest['achievement']  ? RecommendationsEngine::getAchievementRecommendations($latest['achievement'])     : null,
];

$has = [];
foreach ($recs as $key => $rec) {
    $has[$key] = $rec && (!empty($rec['priority']) || !empty($rec['maintain']) || !empty($rec['celebrate']));
}

$activeTab = $_GET['pillar'] ?? array_key_first(array_filter($has)) ?? 'connection';

// Map active tab to data
$tabMap = [
    'connection'   => ['recs' => $recs['connection'],   'has' => $has['connection'],   'resultsLink' => 'view_results.php?pillar=1', 'assessLink' => 'combined_assessment.php?pillar=1', 'label' => 'Connection &amp; Love'],
    'growth'       => ['recs' => $recs['growth'],       'has' => $has['growth'],       'resultsLink' => 'view_results.php?pillar=2', 'assessLink' => 'combined_assessment.php?pillar=2', 'label' => 'Growth &amp; Learning'],
    'contribution' => ['recs' => $recs['contribution'], 'has' => $has['contribution'], 'resultsLink' => 'view_results.php?pillar=3', 'assessLink' => 'combined_assessment.php?pillar=3', 'label' => 'Contribution &amp; Helping'],
    'freedom'      => ['recs' => $recs['freedom'],      'has' => $has['freedom'],      'resultsLink' => 'view_results.php?pillar=4', 'assessLink' => 'combined_assessment.php?pillar=4', 'label' => 'Freedom &amp; Autonomy'],
    'security'     => ['recs' => $recs['security'],     'has' => $has['security'],     'resultsLink' => 'view_results.php?pillar=5', 'assessLink' => 'combined_assessment.php?pillar=5', 'label' => 'Security &amp; Certainty'],
    'nature'       => ['recs' => $recs['nature'],       'has' => $has['nature'],       'resultsLink' => 'view_results.php?pillar=6', 'assessLink' => 'combined_assessment.php?pillar=6', 'label' => 'Nature &amp; Sustainability'],
    'achievement'  => ['recs' => $recs['achievement'],  'has' => $has['achievement'],  'resultsLink' => 'view_results.php?pillar=7', 'assessLink' => 'combined_assessment.php?pillar=7', 'label' => 'Achievement &amp; Mastery'],
];

$current     = $tabMap[$activeTab] ?? $tabMap['connection'];
$activeRecs  = $current['recs'];
$hasRecs     = $current['has'];
$resultsLink = $current['resultsLink'];
$assessLink  = $current['assessLink'];
$pillarLabel = $current['label'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendations — Mental Wellness Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --bg:#0D1117; --surface:#141B24; --surface2:#1A2332;
            --border:rgba(255,255,255,0.07); --gold:#C9A84C; --gold-dim:rgba(201,168,76,0.12);
            --green:#4CAF7D; --text:#F0EBE1; --muted:#4E5D72; --muted2:#8A9BB0; --radius:14px;
        }
        html { scroll-behavior:smooth; }
        body { background:var(--bg); color:var(--text); font-family:'DM Sans',sans-serif; min-height:100vh; overflow-x:hidden; }
        body::before { content:''; position:fixed; top:0; right:0; width:700px; height:700px; background:radial-gradient(circle at 80% 20%,rgba(201,168,76,0.05) 0%,transparent 60%); pointer-events:none; z-index:0; }

        .topnav { position:sticky; top:0; z-index:100; background:rgba(13,17,23,0.88); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); padding:0 32px; display:flex; align-items:center; justify-content:space-between; height:62px; }
        .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .nav-brand-icon { width:32px; height:32px; background:var(--gold-dim); border:1px solid rgba(201,168,76,0.25); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; color:var(--gold); }
        .nav-brand-name { font-family:'Playfair Display',serif; font-size:1rem; font-weight:600; color:var(--text); }
        .nav-brand-name span { color:var(--gold); }
        .nav-right { display:flex; align-items:center; gap:20px; }
        .nav-link { font-size:0.875rem; color:var(--muted2); text-decoration:none; transition:color 0.15s; }
        .nav-link:hover { color:var(--text); }
        .nav-link.active { color:var(--gold); }
        .nav-user { display:flex; align-items:center; gap:9px; }
        .nav-avatar { width:30px; height:30px; border-radius:50%; background:var(--gold-dim); border:1px solid rgba(201,168,76,0.3); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; color:var(--gold); }
        .nav-name { font-size:0.875rem; color:var(--muted2); }
        .btn-logout { font-size:0.8rem; color:var(--muted); text-decoration:none; background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:5px 12px; transition:color 0.15s,border-color 0.15s; }
        .btn-logout:hover { color:var(--text); border-color:var(--muted); }

        .page { max-width:860px; margin:0 auto; padding:48px 32px 80px; position:relative; z-index:1; }
        .page-header { margin-bottom:40px; }
        .page-eyebrow { font-size:0.78rem; font-weight:500; letter-spacing:0.12em; text-transform:uppercase; color:var(--gold); margin-bottom:10px; }
        .page-title { font-family:'Playfair Display',serif; font-size:clamp(1.6rem,3vw,2.2rem); font-weight:700; line-height:1.15; letter-spacing:-0.02em; margin-bottom:10px; }
        .page-sub { font-size:0.925rem; color:var(--muted2); line-height:1.7; max-width:500px; }

        .pillar-tabs { display:flex; gap:10px; margin-bottom:40px; flex-wrap:wrap; }
        .pillar-tab { display:flex; align-items:center; gap:8px; padding:10px 20px; border-radius:10px; font-size:0.875rem; font-weight:500; text-decoration:none; border:1px solid var(--border); background:var(--surface); color:var(--muted2); transition:all 0.2s; }
        .pillar-tab:hover { color:var(--text); border-color:var(--muted); }
        .pillar-tab.active { background:var(--gold-dim); border-color:rgba(201,168,76,0.4); color:var(--gold); }
        .pillar-tab.active-green { background:rgba(76,175,125,0.1); border-color:rgba(76,175,125,0.4); color:var(--green); }
        .pillar-tab.active-contribution { background:rgba(123,158,217,0.1); border-color:rgba(123,158,217,0.4); color:#7B9ED9; }
        .pillar-tab.active-freedom      { background:rgba(176,126,232,0.1); border-color:rgba(176,126,232,0.4); color:#B07EE8; }
        .pillar-tab.active-security     { background:rgba(232,144,90,0.1);  border-color:rgba(232,144,90,0.4);  color:#E8905A; }
        .pillar-tab.active-nature       { background:rgba(109,191,126,0.1); border-color:rgba(109,191,126,0.4); color:#6DBF7E; }
        .pillar-tab.active-achievement  { background:rgba(232,196,90,0.1);  border-color:rgba(232,196,90,0.4);  color:#E8C45A; }
        .pillar-tab.disabled { opacity:0.4; pointer-events:none; }
        .pillar-tab-dot { width:8px; height:8px; border-radius:50%; }

        .empty-state { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:56px 40px; text-align:center; }
        .empty-state-icon { font-size:2.5rem; margin-bottom:16px; }
        .empty-state h3 { font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:600; margin-bottom:8px; }
        .empty-state p { font-size:0.9rem; color:var(--muted2); line-height:1.6; }
        .empty-state a { color:var(--gold); text-decoration:none; }

        .rec-summary { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px 28px; margin-bottom:36px; }
        .rec-summary-eyebrow { font-size:0.72rem; font-weight:500; letter-spacing:0.1em; text-transform:uppercase; color:var(--gold); margin-bottom:10px; }
        .rec-summary-body { font-size:0.9rem; color:var(--muted2); line-height:1.75; }
        .summary-loading { display:inline-flex; align-items:center; gap:6px; color:var(--muted); font-size:0.875rem; }
        .summary-dot { width:5px; height:5px; border-radius:50%; background:var(--gold); opacity:0.4; animation:summarypulse 1.2s ease-in-out infinite; }
        .summary-dot:nth-child(2) { animation-delay:0.2s; }
        .summary-dot:nth-child(3) { animation-delay:0.4s; }
        @keyframes summarypulse { 0%,80%,100% { opacity:0.2; transform:scale(0.85); } 40% { opacity:1; transform:scale(1.1); } }

        .rec-group { margin-bottom:40px; }
        .rec-group-header { display:flex; align-items:center; gap:10px; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid var(--border); }
        .rec-group-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:1rem; }
        .rec-group-title { font-family:'Playfair Display',serif; font-size:1.05rem; font-weight:600; }
        .rec-group-count { margin-left:auto; font-size:0.75rem; color:var(--muted); background:var(--surface2); border:1px solid var(--border); border-radius:99px; padding:2px 10px; }
        .rec-group.priority .rec-group-icon { background:rgba(212,114,106,0.12); }
        .rec-group.maintain .rec-group-icon { background:rgba(224,140,74,0.12); }
        .rec-group.celebrate .rec-group-icon { background:rgba(76,175,125,0.12); }

        .rec-card { background:var(--surface); border:1px solid var(--border); border-left:3px solid var(--border); border-radius:var(--radius); padding:20px 22px; margin-bottom:12px; }
        .rec-card:last-child { margin-bottom:0; }
        .priority .rec-card { border-left-color:#D4726A; }
        .maintain .rec-card { border-left-color:#E08C4A; }
        .celebrate .rec-card { border-left-color:#4CAF7D; }
        .rec-card-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .rec-card-title { font-size:0.9rem; font-weight:600; }
        .rating-badge { font-size:0.75rem; font-weight:600; padding:3px 10px; border-radius:99px; }
        .rating-badge.low { background:rgba(212,114,106,0.12); color:#D4726A; border:1px solid rgba(212,114,106,0.25); }
        .rating-badge.mid { background:rgba(224,140,74,0.12); color:#E08C4A; border:1px solid rgba(224,140,74,0.25); }
        .rating-badge.high { background:rgba(76,175,125,0.12); color:#4CAF7D; border:1px solid rgba(76,175,125,0.25); }
        .rec-list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
        .rec-list li { display:flex; gap:10px; font-size:0.875rem; color:var(--muted2); line-height:1.6; }
        .rec-list li::before { content:'→'; color:var(--gold); font-weight:600; flex-shrink:0; margin-top:1px; }

        /* ── Mobile nav ───────────────────────────────────────────────────────── */
        .nav-hamburger { display:none; flex-direction:column; justify-content:center; gap:5px; width:36px; height:36px; padding:4px; cursor:pointer; background:none; border:none; }
        .nav-hamburger span { display:block; height:2px; background:var(--muted2); border-radius:2px; transition:all 0.25s; }
        .nav-mobile-menu { display:none; position:fixed; inset:62px 0 0 0; background:rgba(13,17,23,0.97); backdrop-filter:blur(16px); z-index:99; padding:24px 20px; flex-direction:column; gap:4px; overflow-y:auto; }
        .nav-mobile-menu.open { display:flex; }
        .nav-mobile-link { font-size:1rem; color:var(--muted2); text-decoration:none; padding:14px 16px; border-radius:10px; border:1px solid transparent; transition:all 0.15s; }
        .nav-mobile-link:hover, .nav-mobile-link:active { background:var(--surface2); color:var(--text); }
        .nav-mobile-link.active { color:var(--gold); background:var(--gold-dim); border-color:rgba(201,168,76,0.2); }
        .nav-mobile-divider { height:1px; background:var(--border); margin:8px 0; }
        .nav-mobile-user { display:flex; align-items:center; gap:10px; padding:14px 16px; }
        .nav-mobile-signout { display:block; font-size:0.9rem; color:var(--muted2); text-decoration:none; padding:14px 16px; border-radius:10px; border:1px solid var(--border); text-align:center; transition:all 0.15s; margin-top:8px; }
        .nav-mobile-signout:hover { color:var(--text); border-color:var(--muted); }

        /* ── Pillar tabs: horizontal scroll on mobile ─────────────────────────── */
        .pillar-tabs-wrapper { position:relative; }
        .pillar-tabs-wrapper::after { content:''; position:absolute; right:0; top:0; bottom:0; width:32px; background:linear-gradient(to right, transparent, var(--bg)); pointer-events:none; display:none; }

        /* ── Responsive breakpoints ───────────────────────────────────────────── */
        @media (max-width:768px) {
            /* Nav */
            .topnav { padding:0 16px; }
            .nav-right { display:none; }
            .nav-hamburger { display:flex; }

            /* Page layout */
            .page { padding:28px 16px 72px; }
            .page-title { font-size:1.6rem; }
            .page-sub { font-size:0.875rem; max-width:100%; }

            /* Pillar tabs — horizontal scroll */
            .pillar-tabs-wrapper::after { display:block; }
            .pillar-tabs { flex-wrap:nowrap; overflow-x:auto; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; padding-bottom:6px; margin-bottom:28px; scrollbar-width:none; }
            .pillar-tabs::-webkit-scrollbar { display:none; }
            .pillar-tab { scroll-snap-align:start; flex-shrink:0; padding:9px 14px; font-size:0.825rem; white-space:nowrap; }

            /* Summary */
            .rec-summary { padding:18px 16px; margin-bottom:24px; }

            /* Group header */
            .rec-group { margin-bottom:28px; }
            .rec-group-header { gap:8px; padding-bottom:12px; }
            .rec-group-title { font-size:0.95rem; }

            /* Cards */
            .rec-card { padding:16px; }
            .rec-card-header { flex-direction:column; align-items:flex-start; gap:8px; margin-bottom:12px; }
            .rating-badge { align-self:flex-start; }

            /* Empty state */
            .empty-state { padding:40px 20px; }
        }

        @media (max-width:420px) {
            .page { padding:20px 12px 64px; }
            .pillar-tab { padding:8px 12px; font-size:0.8rem; }
            .rec-card { padding:14px; }
            .rec-list li { font-size:0.84rem; }
        }
    </style>
</head>
<body>

<nav class="topnav">
    <a href="dashboard.php" class="nav-brand">
        <div class="nav-brand-icon">✦</div>
        <span class="nav-brand-name">Mental Wellness <span>Audit</span></span>
    </a>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="view_recommendations.php" class="nav-link active">Recommendations</a>
        <a href="journal.php" class="nav-link">Journal</a>
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
            <span class="nav-name"><?= htmlspecialchars($user['name']) ?></span>
            <a href="logout.php" class="btn-logout">Sign out</a>
        </div>
    </div>
    <button class="nav-hamburger" id="nav-toggle" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
</nav>

<!-- Mobile navigation menu -->
<div class="nav-mobile-menu" id="nav-mobile-menu" role="navigation" aria-label="Mobile navigation">
    <a href="dashboard.php" class="nav-mobile-link">Dashboard</a>
    <a href="view_recommendations.php" class="nav-mobile-link active">Recommendations</a>
    <a href="journal.php" class="nav-mobile-link">Journal</a>
    <div class="nav-mobile-divider"></div>
    <div class="nav-mobile-user">
        <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
        <span style="font-size:0.9rem;color:var(--muted2)"><?= htmlspecialchars($user['name']) ?></span>
    </div>
    <a href="logout.php" class="nav-mobile-signout">Sign out</a>
</div>
<script>
(function(){
    const btn  = document.getElementById('nav-toggle');
    const menu = document.getElementById('nav-mobile-menu');
    btn.addEventListener('click', function(){
        const open = menu.classList.toggle('open');
        btn.setAttribute('aria-expanded', open);
        // Animate hamburger to X
        const spans = btn.querySelectorAll('span');
        if (open) {
            spans[0].style.cssText = 'transform:translateY(7px) rotate(45deg)';
            spans[1].style.cssText = 'opacity:0';
            spans[2].style.cssText = 'transform:translateY(-7px) rotate(-45deg)';
        } else {
            spans.forEach(s => s.style.cssText = '');
        }
    });
    // Close on outside click
    document.addEventListener('click', function(e){
        if (!btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.remove('open');
            btn.setAttribute('aria-expanded', false);
            btn.querySelectorAll('span').forEach(s => s.style.cssText = '');
        }
    });
})();
</script>

<main class="page">
    <div class="page-header">
        <p class="page-eyebrow">Personalised for you</p>
        <h1 class="page-title">Recommendations</h1>
        <p class="page-sub">Specific, actionable suggestions drawn from your most recent assessments.</p>
    </div>

    <div class="pillar-tabs-wrapper">
    <div class="pillar-tabs">
        <?php
        $tabDefs = [
            'connection'   => ['color' => '#C9A84C', 'active_class' => 'active',              'label' => 'Connection & Love'],
            'growth'       => ['color' => '#4CAF7D', 'active_class' => 'active-green',        'label' => 'Growth & Learning'],
            'contribution' => ['color' => '#7B9ED9', 'active_class' => 'active-contribution', 'label' => 'Contribution & Helping'],
            'freedom'      => ['color' => '#B07EE8', 'active_class' => 'active-freedom',      'label' => 'Freedom & Autonomy'],
            'security'     => ['color' => '#E8905A', 'active_class' => 'active-security',     'label' => 'Security & Certainty'],
            'nature'       => ['color' => '#6DBF7E', 'active_class' => 'active-nature',       'label' => 'Nature & Sustainability'],
            'achievement'  => ['color' => '#E8C45A', 'active_class' => 'active-achievement',  'label' => 'Achievement & Mastery'],
        ];
        foreach ($tabDefs as $key => $def): ?>
            <a href="?pillar=<?= $key ?>"
               class="pillar-tab <?= $activeTab === $key ? $def['active_class'] : '' ?> <?= !$has[$key] ? 'disabled' : '' ?>">
                <span class="pillar-tab-dot" style="background:<?= $def['color'] ?>"></span>
                <?= $def['label'] ?>
                <?php if (!$has[$key]): ?><span style="font-size:0.7rem;opacity:0.6">— no data</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div><!-- /.pillar-tabs -->
    </div><!-- /.pillar-tabs-wrapper -->

    <?php if (!$hasRecs): ?>
        <div class="empty-state">
            <div class="empty-state-icon">💡</div>
            <h3>No recommendations yet</h3>
            <p>Complete a <a href="<?= $assessLink ?>"><?= $pillarLabel ?></a> assessment to generate your personalised recommendations.</p>
        </div>

    <?php else: ?>
        <?php
        $promptSections = [];
        foreach (['priority' => 'Focus areas', 'maintain' => 'Building areas', 'celebrate' => 'Strengths'] as $bucket => $label) {
            if (!empty($activeRecs[$bucket])) {
                $promptSections[] = "{$label}:";
                foreach ($activeRecs[$bucket] as $rec) {
                    $promptSections[] = "  {$rec['category']} (rated {$rec['rating']}/5):";
                    foreach ($rec['suggestions'] as $s) { $promptSections[] = "    - {$s}"; }
                }
            }
        }
        $pillarPlain = strip_tags($pillarLabel);
        $promptText  = "The following are personalised wellbeing recommendations for a user in the '{$pillarPlain}' dimension of a Mental Wellbeing Audit:\n\n"
                     . implode("\n", $promptSections)
                     . "\n\nWrite exactly 2 short sentences summarising the key strengths and the most important area to focus on. Be concise. Plain text only.";
        $promptJson = json_encode($promptText);
        ?>
        <div class="rec-summary">
            <div class="rec-summary-eyebrow">Your summary</div>
            <div class="rec-summary-body" id="ai-summary-body">
                <span class="summary-loading">
                    <span class="summary-dot"></span><span class="summary-dot"></span><span class="summary-dot"></span>
                    Generating your summary…
                </span>
            </div>
        </div>
        <script>
        (function() {
            const prompt = <?= $promptJson ?>;
            const el = document.getElementById('ai-summary-body');
            fetch('generate_summary.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ prompt })
            })
            .then(r => r.json())
            .then(data => { el.textContent = data.summary || 'Your personalised recommendations are ready below.'; })
            .catch(() => { el.textContent = 'Your personalised recommendations are ready below.'; });
        })();
        </script>

        <?php foreach (['priority' => ['icon' => '🎯', 'title' => 'Focus Here First', 'badge' => 'low'], 'maintain' => ['icon' => '📈', 'title' => 'Keep Building', 'badge' => 'mid'], 'celebrate' => ['icon' => '⭐', 'title' => 'Celebrate Your Strengths', 'badge' => 'high']] as $bucket => $meta): ?>
        <?php if (!empty($activeRecs[$bucket])): ?>
        <div class="rec-group <?= $bucket ?>">
            <div class="rec-group-header">
                <div class="rec-group-icon"><?= $meta['icon'] ?></div>
                <span class="rec-group-title"><?= $meta['title'] ?></span>
                <span class="rec-group-count"><?= count($activeRecs[$bucket]) ?> area<?= count($activeRecs[$bucket]) !== 1 ? 's' : '' ?></span>
            </div>
            <?php foreach ($activeRecs[$bucket] as $rec): ?>
            <div class="rec-card">
                <div class="rec-card-header">
                    <span class="rec-card-title"><?= htmlspecialchars($rec['category']) ?></span>
                    <span class="rating-badge <?= $meta['badge'] ?>">Rating <?= $rec['rating'] ?>/5</span>
                </div>
                <ul class="rec-list">
                    <?php foreach (array_slice($rec['suggestions'], 0, $bucket === 'priority' ? 10 : 2) as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>
</main>
</body>
</html>
