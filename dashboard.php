<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/recommendations_engine.php';
Auth::require();

$pdo  = Database::connect();
$user = Auth::user();

// ── Resolve tenant name ───────────────────────────────────────────────────────
$tenantName = null;
if (!empty($user['tenant_id']) && (int)$user['tenant_id'] > 0) {
    $tStmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? AND is_active = true LIMIT 1");
    $tStmt->execute([$user['tenant_id']]);
    $tenantRow = $tStmt->fetch(PDO::FETCH_ASSOC);
    $tenantName = $tenantRow['name'] ?? null;
}

// ── Score interpretation helper ───────────────────────────────────────────────
function getScoreInterpretation(int $score, int $max = 50): array {
    $pct = $max > 0 ? ($score / $max * 100) : 0;
    if ($pct >= 90) return ['level' => 'Thriving',    'color' => '#4CAF50', 'description' => 'You are excelling across your connections and relationships.'];
    if ($pct >= 75) return ['level' => 'Flourishing', 'color' => '#8BC34A', 'description' => 'Your connections are strong with a few areas to nurture.'];
    if ($pct >= 55) return ['level' => 'Growing',     'color' => '#C9A84C', 'description' => 'You\'re making meaningful progress — keep building.'];
    if ($pct >= 35) return ['level' => 'Developing',  'color' => '#FF9800', 'description' => 'Several areas need attention and intentional effort.'];
    return                 ['level' => 'Needs Work',  'color' => '#F44336', 'description' => 'This is a great time to focus on rebuilding your foundations.'];
}

// ── Latest score per pillar ───────────────────────────────────────────────────
$stmtTotal = $pdo->prepare("
    SELECT ts.pillar_id, ts.total_score, ts.recorded_at
    FROM total_scores ts
    INNER JOIN (
        SELECT pillar_id, MAX(recorded_at) AS latest
        FROM total_scores
        WHERE user_id = ? AND tenant_id = ?
        GROUP BY pillar_id
    ) sub ON sub.pillar_id = ts.pillar_id AND sub.latest = ts.recorded_at
    WHERE ts.user_id = ? AND ts.tenant_id = ?
");
$stmtTotal->execute([$user['id'], $user['tenant_id'], $user['id'], $user['tenant_id']]);
$allPillarScores = [];
foreach ($stmtTotal->fetchAll() as $row) {
    $allPillarScores[$row['pillar_id']] = $row;
}
$totalScore          = array_sum(array_column($allPillarScores, 'total_score'));
$totalMaxScore       = count($allPillarScores) * 25;
$totalInterpretation = !empty($allPillarScores)
    ? getScoreInterpretation($totalScore, max($totalMaxScore, 1))
    : null;

// ── Most recent session scores ────────────────────────────────────────────────
$stmtLatest = $pdo->prepare("SELECT MAX(recorded_at) FROM total_scores WHERE user_id = ? AND tenant_id = ?");
$stmtLatest->execute([$user['id'], $user['tenant_id']]);
$latestRecordedAt = $stmtLatest->fetchColumn();

$pillarScores = [];
if ($latestRecordedAt) {
    $stmt = $pdo->prepare("
        SELECT pillar_id, total_score, recorded_at
        FROM total_scores
        WHERE user_id = ? AND tenant_id = ? AND recorded_at = ?
    ");
    $stmt->execute([$user['id'], $user['tenant_id'], $latestRecordedAt]);
    foreach ($stmt->fetchAll() as $row) {
        $pillarScores[$row['pillar_id']] = $row;
    }
}

$combinedScore = array_sum(array_column($pillarScores, 'total_score'));
$maxScore      = count($pillarScores) * 25;

// ── Total assessment count ────────────────────────────────────────────────────
$stmtCount = $pdo->prepare("
    SELECT
        (SELECT COUNT(*) FROM connection_love_assessments       WHERE user_id = ? AND tenant_id = ?) +
        (SELECT COUNT(*) FROM growth_learning_assessments       WHERE user_id = ? AND tenant_id = ?) +
        (SELECT COUNT(*) FROM contribution_assessments          WHERE user_id = ? AND tenant_id = ?) +
        (SELECT COUNT(*) FROM freedom_autonomy_assessments      WHERE user_id = ? AND tenant_id = ?) +
        (SELECT COUNT(*) FROM security_certainty_assessments    WHERE user_id = ? AND tenant_id = ?) +
        (SELECT COUNT(*) FROM nature_sustainability_assessments WHERE user_id = ? AND tenant_id = ?) +
        (SELECT COUNT(*) FROM achievement_mastery_assessments   WHERE user_id = ? AND tenant_id = ?) AS total
");
$stmtCount->execute([
    $user['id'], $user['tenant_id'],
    $user['id'], $user['tenant_id'],
    $user['id'], $user['tenant_id'],
    $user['id'], $user['tenant_id'],
    $user['id'], $user['tenant_id'],
    $user['id'], $user['tenant_id'],
    $user['id'], $user['tenant_id'],
]);
$assessmentCount = (int)$stmtCount->fetchColumn();

$interpretation = !empty($pillarScores)
    ? getScoreInterpretation($combinedScore, max($maxScore, 1))
    : null;

// ── Fetch pillars ─────────────────────────────────────────────────────────────
$stmt       = $pdo->query("SELECT * FROM pillars ORDER BY sort_order ASC");
$valueCards = $stmt->fetchAll();
$activePillars = array_sum(array_column($valueCards, 'is_active'));

// ── Score history per pillar (for insights graph) ─────────────────────────────
$stmtHistory = $pdo->prepare("
    SELECT pillar_id, total_score, recorded_at
    FROM total_scores
    WHERE user_id = ? AND tenant_id = ?
    ORDER BY pillar_id ASC, recorded_at ASC
");
$stmtHistory->execute([$user['id'], $user['tenant_id']]);
$pillarHistory = [];
foreach ($stmtHistory->fetchAll() as $row) {
    $pillarHistory[$row['pillar_id']][] = [
        'score' => (int)$row['total_score'],
        'date'  => date('d M', strtotime($row['recorded_at'])),
    ];
}

// ── Fetch latest assessment per pillar for summary modal ──────────────────────
$pillarMeta = [
    1 => ['alias' => 'cla', 'table' => 'connection_love_assessments',       'label' => 'Connection & Love'],
    2 => ['alias' => 'gla', 'table' => 'growth_learning_assessments',       'label' => 'Growth & Learning'],
    3 => ['alias' => 'ca',  'table' => 'contribution_assessments',          'label' => 'Contribution & Helping'],
    4 => ['alias' => 'faa', 'table' => 'freedom_autonomy_assessments',      'label' => 'Freedom & Autonomy'],
    5 => ['alias' => 'sca', 'table' => 'security_certainty_assessments',    'label' => 'Security & Certainty'],
    6 => ['alias' => 'nsa', 'table' => 'nature_sustainability_assessments', 'label' => 'Nature & Sustainability'],
    7 => ['alias' => 'ama', 'table' => 'achievement_mastery_assessments',   'label' => 'Achievement & Mastery'],
];
$pillarPrompts = [];
foreach ($pillarMeta as $pid => $pm) {
    $a = $pm['alias'];
    $s2 = $pdo->prepare("SELECT {$a}.*, ts.total_score FROM {$pm['table']} {$a}
        LEFT JOIN LATERAL (SELECT total_score FROM total_scores WHERE user_id = {$a}.user_id AND tenant_id = {$a}.tenant_id AND pillar_id = {$pid} ORDER BY recorded_at DESC LIMIT 1) ts ON true
        WHERE {$a}.user_id = ? AND {$a}.tenant_id = ? ORDER BY {$a}.assessment_date DESC LIMIT 1");
    $s2->execute([$user['id'], $user['tenant_id']]);
    $row = $s2->fetch();
    if (!$row) continue;
    $recMethods = [
        1 => 'getConnectionRecommendations',
        2 => 'getGrowthRecommendations',
        3 => 'getContributionRecommendations',
        4 => 'getFreedomRecommendations',
        5 => 'getSecurityRecommendations',
        6 => 'getNatureRecommendations',
        7 => 'getAchievementRecommendations',
    ];
    $recMethod = $recMethods[$pid] ?? null;
    if (!$recMethod) continue;
    $recs = RecommendationsEngine::$recMethod($row);
    if (!$recs) continue;
    $sections = [];
    foreach (['priority' => 'Focus areas', 'maintain' => 'Building areas', 'celebrate' => 'Strengths'] as $bucket => $blabel) {
        if (!empty($recs[$bucket])) {
            $sections[] = "{$blabel}:";
            foreach ($recs[$bucket] as $rec) {
                $sections[] = "  {$rec['category']} (rated {$rec['rating']}/5):";
                foreach ($rec['suggestions'] as $sug) { $sections[] = "    - {$sug}"; }
            }
        }
    }
    if (!empty($sections)) {
        $pillarPrompts[$pid] = "Personalised wellbeing recommendations for '{$pm['label']}':\n\n"
            . implode("\n", $sections)
            . "\n\nWrite exactly 2 short sentences summarising the key strengths and the most important area to focus on. Be concise. Plain text only.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Mental Wellbeing Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:#0D1117; --surface:#141B24; --surface2:#1A2332; --surface3:#1F2B3E;
            --border:rgba(255,255,255,0.07); --border2:rgba(255,255,255,0.04);
            --gold:#C9A84C; --gold-dim:rgba(201,168,76,0.12); --gold-glow:rgba(201,168,76,0.2);
            --green:#4CAF7D; --blue:#7B9ED9; --text:#F0EBE1;
            --muted:#4E5D72; --muted2:#8A9BB0; --radius:14px;
        }
        html { scroll-behavior: smooth; }
        body { background:var(--bg); color:var(--text); font-family:'DM Sans',sans-serif; min-height:100vh; overflow-x:hidden; }
        body::before { content:''; position:fixed; top:0; right:0; width:700px; height:700px; background:radial-gradient(circle at 80% 20%,rgba(201,168,76,0.05) 0%,transparent 60%); pointer-events:none; z-index:0; }

        .topnav { position:sticky; top:0; z-index:100; background:rgba(13,17,23,0.88); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); padding:0 32px; display:flex; align-items:center; justify-content:space-between; height:62px; }
        .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .nav-brand-icon { width:32px; height:32px; background:var(--gold-dim); border:1px solid rgba(201,168,76,0.25); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; color:var(--gold); }
        .nav-brand-name { font-family:'Playfair Display',serif; font-size:1rem; font-weight:600; color:var(--text); }
        .nav-brand-name span { color:var(--gold); }
        .nav-right { display:flex; align-items:center; gap:20px; }
        .nav-link { font-size:0.875rem; color:var(--text); text-decoration:none; transition:color 0.15s; }
        .nav-link:hover { color:var(--gold); }
        .nav-link.active { color:var(--gold); }
        .nav-user { display:flex; align-items:center; gap:9px; }
        .nav-avatar { width:30px; height:30px; border-radius:50%; background:var(--gold-dim); border:1px solid rgba(201,168,76,0.3); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; color:var(--gold); }
        .nav-name { font-size:0.875rem; color:var(--text); }
        .nav-org-badge { font-size:0.7rem; font-weight:500; color:var(--muted2); background:var(--surface2); border:1px solid var(--border); border-radius:99px; padding:2px 9px; letter-spacing:0.02em; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .btn-logout { font-size:0.8rem; color:var(--text); text-decoration:none; background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:5px 12px; transition:color 0.15s,border-color 0.15s; }
        .btn-logout:hover { border-color:var(--muted2); }

        .page { max-width:1120px; margin:0 auto; padding:12px 32px 80px; position:relative; z-index:1; }

        .hero { margin-bottom:12px; display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap; }
        .hero-greeting { font-size:0.7rem; font-weight:500; letter-spacing:0.12em; text-transform:uppercase; color:var(--gold); margin-bottom:1px; }
        .hero-title { font-family:'DM Sans',sans-serif; font-size:1rem; font-weight:600; line-height:1.2; letter-spacing:-0.01em; margin-bottom:0; color:var(--muted2); }
        .hero-sub { display:none; }

        /* ── Nav User Dropdown ──────────────────────────────────── */
        .nav-user-wrap { position:relative; }
        .nav-user { display:flex; align-items:center; gap:9px; background:none; border:none; cursor:pointer; padding:4px 8px; border-radius:8px; color:var(--text); font-family:inherit; transition:background 0.15s; }
        .nav-user:hover { background:var(--surface2); }
        .nav-chevron { font-size:10px; color:var(--muted2); transition:transform 0.25s; margin-left:2px; }
        .nav-user[aria-expanded="true"] .nav-chevron { transform:rotate(180deg); }

        .nav-stats-panel { display:none; position:absolute; top:calc(100% + 10px); right:0; width:340px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); box-shadow:0 16px 40px rgba(0,0,0,0.4); z-index:200; overflow:hidden; }
        .nav-stats-panel.open { display:block; animation:fadeSlideDown 0.2s ease; }
        @keyframes fadeSlideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

        .nav-stats-grid { display:grid; grid-template-columns:1fr 1fr; gap:1px; background:var(--border); }
        .nav-stat { background:var(--surface); padding:16px 18px; }
        .nav-stat-label { font-size:0.7rem; font-weight:500; letter-spacing:0.08em; text-transform:uppercase; color:var(--muted2); margin-bottom:6px; }
        .nav-stat-value { font-family:'Playfair Display',serif; font-size:1.5rem; font-weight:700; line-height:1; margin-bottom:3px; }
        .nav-stat-unit { font-size:0.85rem; color:var(--muted2); font-weight:400; }
        .nav-stat-sub { font-size:0.75rem; color:var(--muted2); }
        .nav-stats-footer { padding:10px 18px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; }
        .nav-stats-signout { font-size:0.8rem; color:var(--muted2); text-decoration:none; padding:4px 10px; border-radius:6px; border:1px solid var(--border); transition:color 0.15s,border-color 0.15s; }
        .nav-stats-signout:hover { color:var(--text); border-color:var(--muted2); }

        /* ── Stats / stat-card (kept for potential reuse) ────────── */
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; padding-top:20px; }
        .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; transition:border-color 0.2s; }
        .stat-card:hover { border-color:rgba(201,168,76,0.2); }
        .stat-label { font-size:0.75rem; font-weight:500; letter-spacing:0.08em; text-transform:uppercase; color:var(--muted2); margin-bottom:8px; }
        .stat-value { font-family:'Playfair Display',serif; font-size:2rem; font-weight:700; line-height:1; margin-bottom:4px; }
        .stat-value .unit { font-size:1rem; color:var(--muted2); font-weight:400; }
        .stat-sub { font-size:0.78rem; color:var(--muted2); }
        .stat-card.latest-score .stat-value { color:<?= $totalInterpretation ? $totalInterpretation['color'] : 'var(--text)' ?>; }

        .section { margin-bottom:20px; }
        .section-header { display:flex; align-items:flex-start; gap:12px; margin-bottom:14px; padding-bottom:14px; border-bottom:1px solid var(--border); }
        .section-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; margin-top:2px; }
        .section-meta { flex:1; }
        .section-title { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:600; margin-bottom:3px; letter-spacing:-0.01em; }
        .section-desc { font-size:0.8rem; color:var(--muted2); line-height:1.5; }

        .cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px; }
        .value-cards-grid { grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); }
        .cat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px; text-decoration:none; display:flex; flex-direction:column; gap:7px; transition:border-color 0.2s,transform 0.15s,box-shadow 0.2s; cursor:pointer; position:relative; overflow:hidden; min-height:150px; }
        .cat-card:hover { transform:translateY(-2px); box-shadow:0 8px 32px rgba(0,0,0,0.25); }
        .value-card--soon { cursor:default; pointer-events:none; }
        .value-card--soon:hover { transform:none !important; box-shadow:none !important; border-color:var(--border) !important; }
        .cat-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
        .cat-icon { font-size:1.3rem; line-height:1; }
        .cat-score-chip { font-size:0.7rem; font-weight:600; padding:2px 8px; border-radius:99px; }
        .coming-soon-badge { font-size:0.65rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase; padding:2px 8px; border-radius:99px; background:var(--surface2); color:var(--muted); border:1px solid var(--border); }
        .cat-name { font-size:0.875rem; font-weight:600; line-height:1.3; }
        .cat-desc { font-size:0.75rem; color:var(--muted2); line-height:1.45; text-transform:lowercase; }
        .cat-bar { height:3px; border-radius:99px; background:var(--surface2); overflow:hidden; }
        .cat-bar-fill { height:100%; border-radius:99px; }
        .cat-arrow { font-size:0.72rem; color:var(--muted); align-self:flex-end; transition:color 0.2s,transform 0.2s; }
        .cat-card:hover .cat-arrow { color:var(--muted2); transform:translateX(3px); }

        .cat-card-footer { display:flex; align-items:center; justify-content:space-between; margin-top:auto; padding-top:4px; }
        .cat-summary-link { font-size:0.72rem; color:var(--muted2); text-decoration:underline; text-underline-offset:3px; text-decoration-color:rgba(255,255,255,0.2); cursor:pointer; background:none; border:none; font-family:inherit; padding:0; transition:color 0.15s,text-decoration-color 0.15s; position:relative; z-index:2; }
        .cat-summary-link:hover { color:var(--text); text-decoration-color:var(--muted2); }
        .card-overlay-link { position:absolute; inset:0; z-index:1; border-radius:var(--radius); }
        .cat-arrow { font-size:0.72rem; position:relative; z-index:2; transition:color 0.15s; text-decoration:none; }
        .cat-footer-right { display:none; }
        .cat-insights-link { font-size:0.72rem; font-weight:400; background:none; border:none; cursor:pointer; font-family:inherit; padding:0; text-decoration:underline; text-underline-offset:3px; text-decoration-color:rgba(255,255,255,0.2); transition:opacity 0.15s,text-decoration-color 0.15s; position:relative; z-index:2; opacity:0.75; }
        .cat-insights-link:hover { opacity:1; text-decoration-color:currentColor; }

        /* ── Insights Modal ─────────────────────────────────────── */
        .insights-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:500; align-items:center; justify-content:center; padding:20px; }
        .insights-modal-backdrop.open { display:flex; animation:fadeIn 0.2s ease; }
        .insights-modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:420px; box-shadow:0 24px 60px rgba(0,0,0,0.5); animation:slideUp 0.25s cubic-bezier(0.16,1,0.3,1); overflow:hidden; }
        .insights-modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 20px 0; }
        .insights-modal-eyebrow { font-size:0.7rem; font-weight:500; letter-spacing:0.1em; text-transform:uppercase; color:var(--gold); }
        .insights-modal-title { font-family:'Playfair Display',serif; font-size:1rem; font-weight:600; padding:4px 20px 0; }
        .insights-modal-body { padding:14px 20px 20px; }
        .insights-chart-wrap { width:100%; height:160px; position:relative; }
        .insights-stats { display:flex; gap:8px; margin-top:14px; }
        .insights-stat { flex:1; background:var(--surface2); border-radius:10px; padding:10px 12px; }
        .insights-stat-label { font-size:0.65rem; font-weight:500; letter-spacing:0.08em; text-transform:uppercase; color:var(--muted2); margin-bottom:4px; }
        .insights-stat-value { font-family:'Playfair Display',serif; font-size:1.3rem; font-weight:700; line-height:1; }
        .insights-stat-unit { font-size:0.75rem; color:var(--muted2); font-weight:400; }

        /* ── Summary Modal ──────────────────────────────────────── */
        .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:500; align-items:center; justify-content:center; padding:20px; }
        .modal-backdrop.open { display:flex; animation:fadeIn 0.2s ease; }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        .modal { background:var(--surface); border:1px solid var(--border); border-radius:16px; width:100%; max-width:480px; box-shadow:0 24px 60px rgba(0,0,0,0.5); animation:slideUp 0.25s cubic-bezier(0.16,1,0.3,1); }
        @keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 0; }
        .modal-eyebrow { font-size:0.7rem; font-weight:500; letter-spacing:0.1em; text-transform:uppercase; color:var(--gold); }
        .modal-close { width:28px; height:28px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); color:var(--muted2); font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:color 0.15s,background 0.15s; }
        .modal-close:hover { color:var(--text); background:var(--surface3); }
        .modal-title { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:600; padding:6px 24px 0; }
        .modal-body { padding:16px 24px 24px; font-size:0.9rem; color:var(--muted2); line-height:1.75; }
        .modal-loading { display:flex; align-items:center; gap:6px; color:var(--muted); font-size:0.875rem; }
        .modal-dot { width:5px; height:5px; border-radius:50%; background:var(--gold); opacity:0.4; animation:summarypulse 1.2s ease-in-out infinite; }
        .modal-dot:nth-child(2){animation-delay:0.2s} .modal-dot:nth-child(3){animation-delay:0.4s}
        @keyframes summarypulse { 0%,100%{opacity:0.3} 50%{opacity:1} }

        /* ── Hamburger toggle ───────────────────────────────────── */
        .nav-mobile-toggle { display:none; flex-direction:column; justify-content:center; gap:5px; background:none; border:none; cursor:pointer; padding:6px 4px; color:var(--text); }
        .nav-mobile-toggle span { display:block; width:22px; height:2px; background:currentColor; border-radius:2px; transition:transform 0.25s, opacity 0.25s; }
        .nav-mobile-toggle.open span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
        .nav-mobile-toggle.open span:nth-child(2) { opacity:0; }
        .nav-mobile-toggle.open span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }

        /* ── Mobile nav drawer ──────────────────────────────────── */
        .mobile-nav-drawer { display:none; position:fixed; top:62px; left:0; right:0; bottom:0; background:rgba(13,17,23,0.98); backdrop-filter:blur(16px); z-index:99; padding:8px 0; flex-direction:column; overflow-y:auto; }
        .mobile-nav-drawer.open { display:flex; animation:fadeIn 0.18s ease; }
        .mobile-nav-item { font-size:1rem; font-weight:500; color:var(--muted2); text-decoration:none; padding:16px 24px; border-bottom:1px solid var(--border); transition:color 0.15s,background 0.15s; display:block; }
        .mobile-nav-item:hover { color:var(--text); background:var(--surface); }
        .mobile-nav-item.active { color:var(--gold); }
        .mobile-nav-signout { margin-top:auto; color:var(--muted) !important; font-size:0.9rem !important; }

        /* ── Tablet (≤900px) ────────────────────────────────────── */
        @media (max-width:900px) {
            .value-cards-grid { grid-template-columns:repeat(2,1fr); }
            .cards-grid { grid-template-columns:repeat(2,1fr); }
        }

        /* ── Mobile (≤768px) ────────────────────────────────────── */
        @media (max-width:768px) {
            .topnav { padding:0 16px; }
            .page { padding:14px 14px 60px; }

            /* hide desktop nav links, show hamburger */
            .nav-link { display:none; }
            .nav-mobile-toggle { display:flex; }
            .nav-name { display:none; }
            .nav-org-badge { display:none; }

            /* nav user dropdown: full width */
            .nav-stats-panel { width:calc(100vw - 28px); right:-4px; }
            .nav-stats-grid { grid-template-columns:1fr 1fr; }

            /* hero */
            .hero { margin-bottom:8px; }

            /* section header */
            .section-header { gap:10px; }
            .section-title { font-size:1rem; }

            /* cards: 2-col on tablet/mobile */
            .cards-grid { grid-template-columns:repeat(2,1fr); gap:8px; }
            .value-cards-grid { grid-template-columns:repeat(2,1fr); }
            .cat-card { min-height:130px; padding:12px; }

            /* modals: full-screen feel */
            .modal-backdrop { padding:0; align-items:flex-end; }
            .modal { border-radius:16px 16px 0 0; max-width:100%; animation:slideUpMobile 0.28s cubic-bezier(0.16,1,0.3,1); }
            .insights-modal-backdrop { padding:0; align-items:flex-end; }
            .insights-modal { border-radius:16px 16px 0 0; max-width:100%; animation:slideUpMobile 0.28s cubic-bezier(0.16,1,0.3,1); }
            @keyframes slideUpMobile { from{opacity:0;transform:translateY(100%)} to{opacity:1;transform:translateY(0)} }

            /* stats row */
            .stats-row { grid-template-columns:repeat(2,1fr); gap:10px; }

            /* insights stats wrap on mobile */
            .insights-stats { flex-wrap:wrap; }
            .insights-stat { flex:1 1 calc(50% - 4px); }
        }

        /* ── Small mobile (≤480px) ──────────────────────────────── */
        @media (max-width:480px) {
            .topnav { height:56px; }
            .mobile-nav-drawer { top:56px; }
            .page { padding:12px 12px 60px; }
            .cards-grid { grid-template-columns:1fr; }
            .value-cards-grid { grid-template-columns:1fr; }
            .cat-card { min-height:unset; }
            .nav-stats-panel { width:calc(100vw - 24px); right:0; }
            .section-header { flex-direction:row; }
            .modal-header { padding:16px 16px 0; }
            .modal-title { padding:4px 16px 0; }
            .modal-body { padding:12px 16px 20px; }
        }
    </style>
</head>
<body>

<nav class="topnav">
    <a href="dashboard.php" class="nav-brand">
        <div class="nav-brand-icon">✦</div>
        <span class="nav-brand-name">Mental Wellbeing <span>Audit</span></span>
    </a>
    <div class="nav-right">
        <a href="dashboard.php" class="nav-link active">Dashboard</a>
        <a href="view_recommendations.php" class="nav-link">Recommendations</a>
        <a href="journal.php" class="nav-link">Journal</a>
        <button class="nav-mobile-toggle" id="nav-mobile-toggle" onclick="toggleMobileNav(this)" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <div class="nav-user-wrap">
            <button class="nav-user" onclick="toggleNavStats(this)" aria-expanded="false" aria-controls="nav-stats-panel">
                <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
                <span class="nav-name"><?= htmlspecialchars($user['name']) ?></span>
                <span class="nav-chevron">▾</span>
            </button>
            <div class="nav-stats-panel" id="nav-stats-panel">                <div class="nav-stats-grid">
                    <div class="nav-stat">
                        <div class="nav-stat-label">Latest Score</div>
                        <?php if (!empty($allPillarScores)): ?>
                            <div class="nav-stat-value" style="color:<?= $totalInterpretation['color'] ?>"><?= $totalScore ?><span class="nav-stat-unit">/<?= $totalMaxScore ?></span></div>
                            <div class="nav-stat-sub"><?= $totalInterpretation['level'] ?></div>
                        <?php else: ?>
                            <div class="nav-stat-value" style="color:var(--muted)">—</div>
                            <div class="nav-stat-sub">No data yet</div>
                        <?php endif; ?>
                    </div>
                    <div class="nav-stat">
                        <div class="nav-stat-label">Assessments</div>
                        <div class="nav-stat-value"><?= $assessmentCount ?></div>
                        <div class="nav-stat-sub"><?= $assessmentCount === 1 ? '1 session' : "$assessmentCount sessions" ?></div>
                    </div>
                    <div class="nav-stat">
                        <div class="nav-stat-label">Member Since</div>
                        <div class="nav-stat-value" style="font-size:1.1rem"><?= date('M Y', strtotime($user['created_at'])) ?></div>
                        <div class="nav-stat-sub"><?= floor((time() - strtotime($user['created_at'])) / 86400) ?> days</div>
                    </div>
                    <div class="nav-stat">
                        <div class="nav-stat-label">Pillars</div>
                        <div class="nav-stat-value"><?= $activePillars ?></div>
                        <div class="nav-stat-sub">of <?= count($valueCards) ?> dimensions</div>
                    </div>
                </div>
                <div class="nav-stats-footer">
                    <?php if ($tenantName): ?>
                        <span style="font-size:0.75rem;color:var(--muted2);flex:1">🏢 <?= htmlspecialchars($tenantName) ?></span>
                    <?php endif; ?>
                    <a href="logout.php" class="nav-stats-signout">Sign out</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile nav drawer -->
<div class="mobile-nav-drawer" id="mobile-nav-drawer">
    <a href="dashboard.php" class="mobile-nav-item active">Dashboard</a>
    <a href="view_recommendations.php" class="mobile-nav-item">Recommendations</a>
    <a href="journal.php" class="mobile-nav-item">Journal</a>
    <a href="logout.php" class="mobile-nav-item mobile-nav-signout">Sign out</a>
</div>

<main class="page">

    <div class="hero">
        <div>
            <p class="hero-greeting">Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?></p>
            <h1 class="hero-title"><?= htmlspecialchars($user['name']) ?></h1>
            <?php if ($tenantName): ?>
                <p style="font-size:0.78rem;color:var(--muted2);margin-top:3px">🏢 <?= htmlspecialchars($tenantName) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <section class="section">
        <div class="section-header">
            <div class="section-icon" style="background:rgba(201,168,76,0.1);color:var(--gold);border:1px solid rgba(201,168,76,0.25)">◈</div>
            <div class="section-meta">
                <h2 class="section-title">Your Life Dimensions</h2>
                <p class="section-desc">Seven core areas that shape a flourishing, meaningful life.</p>
            </div>
        </div>

        <div class="cards-grid value-cards-grid">
            <?php foreach ($valueCards as $card):
                $color    = $card['color'];
                $active   = (bool)$card['is_active'];
                $pillarId = (int)$card['id'];
                $pillarScore = $allPillarScores[$pillarId]['total_score'] ?? null;
                $rating   = ($active && $pillarScore !== null) ? $pillarScore : null;
                $pct      = $rating !== null ? ($rating / 25 * 100) : 0;
                $hasPrompt = $active && isset($pillarPrompts[$pillarId]);
                $promptJson = $hasPrompt ? htmlspecialchars(json_encode($pillarPrompts[$pillarId]), ENT_QUOTES) : '';
                $history    = $pillarHistory[$pillarId] ?? [];
                $hasInsights = $active && count($history) >= 3;
                $historyJson = $hasInsights ? htmlspecialchars(json_encode(['scores' => array_column($history, 'score'), 'dates' => array_column($history, 'date'), 'color' => $color, 'label' => $card['label']]), ENT_QUOTES) : '';
            ?>
            <div class="cat-card value-card <?= $active ? 'value-card--active' : 'value-card--soon' ?>"
               data-color="<?= $active ? $color : '' ?>">
                <?php if ($active): ?>
                    <a href="combined_assessment.php?pillar=<?= $pillarId ?>" class="card-overlay-link" aria-label="New assessment for <?= htmlspecialchars($card['label'], ENT_QUOTES) ?>"></a>
                <?php endif; ?>
                <div class="cat-card-top">
                    <span class="cat-icon" style="color:<?= $color ?>;opacity:<?= $active ? 1 : 0.35 ?>"><?= $card['icon'] ?></span>
                    <?php if ($active): ?>
                        <span class="cat-score-chip" style="background:<?= $color ?>18;color:<?= $color ?>;border:1px solid <?= $color ?>35">
                            <?= $rating !== null ? "$rating/25" : '—/25' ?>
                        </span>
                    <?php else: ?>
                        <span class="coming-soon-badge">Coming soon</span>
                    <?php endif; ?>
                </div>
                <div class="cat-name" style="opacity:<?= $active ? 1 : 0.4 ?>"><?= htmlspecialchars($card['label']) ?></div>
                <div class="cat-desc" style="opacity:<?= $active ? 1 : 0.3 ?>"><?= htmlspecialchars($card['description']) ?></div>
                <?php if ($active): ?>
                    <div class="cat-bar">
                        <div class="cat-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;opacity:<?= $rating ? 1 : 0.18 ?>"></div>
                    </div>
                    <div class="cat-card-footer">
                        <?php if ($hasPrompt): ?>
                            <button class="cat-summary-link"
                                onclick="openSummaryModal(event, '<?= htmlspecialchars($card['label'], ENT_QUOTES) ?>', <?= $promptJson ?>)"
                            >Summary</button>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <?php if ($hasInsights): ?>
                            <button class="cat-insights-link" style="color:<?= $color ?>"
                                onclick="openInsightsModal(event, <?= $historyJson ?>)"
                            >Insights</button>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <a href="combined_assessment.php?pillar=<?= $pillarId ?>" class="cat-arrow" style="color:<?= $color ?>;opacity:0.7">Assessment</a>
                    </div>
                <?php else: ?>
                    <div class="cat-bar" style="opacity:0.15"><div class="cat-bar-fill" style="width:0%;background:<?= $color ?>"></div></div>
                    <span class="cat-arrow" style="color:var(--muted);opacity:0.4;font-size:0.75rem">Not yet available</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

</main>

<!-- Insights Modal -->
<div class="insights-modal-backdrop" id="insights-modal" onclick="closeInsightsModal(event)">
    <div class="insights-modal" role="dialog" aria-modal="true" aria-labelledby="insights-modal-title">
        <div class="insights-modal-header">
            <span class="insights-modal-eyebrow">Progress Over Time</span>
            <button class="modal-close" onclick="closeInsightsModal()" aria-label="Close">✕</button>
        </div>
        <div class="insights-modal-title" id="insights-modal-title"></div>
        <div class="insights-modal-body">
            <div class="insights-chart-wrap">
                <canvas id="insights-chart"></canvas>
            </div>
            <div class="insights-stats" id="insights-stats"></div>
        </div>
    </div>
</div>
<div class="modal-backdrop" id="summary-modal" onclick="closeSummaryModal(event)">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-header">
            <span class="modal-eyebrow">Latest Assessment Summary</span>
            <button class="modal-close" onclick="closeSummaryModal()" aria-label="Close">✕</button>
        </div>
        <div class="modal-title" id="modal-title"></div>
        <div class="modal-body" id="modal-body">
            <div class="modal-loading">
                <span class="modal-dot"></span><span class="modal-dot"></span><span class="modal-dot"></span>
                Generating summary…
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
let _insightsChart = null;

function openInsightsModal(e, data) {
    e.preventDefault();
    e.stopPropagation();

    const backdrop = document.getElementById('insights-modal');
    document.getElementById('insights-modal-title').textContent = data.label;
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Stats
    const scores = data.scores;
    const first  = scores[0];
    const latest = scores[scores.length - 1];
    const best   = Math.max(...scores);
    const trend  = latest - first;
    const trendColor = trend >= 0 ? '#4CAF7D' : '#F44336';
    const trendSign  = trend >= 0 ? '+' : '';
    document.getElementById('insights-stats').innerHTML = `
        <div class="insights-stat">
            <div class="insights-stat-label">Latest</div>
            <div class="insights-stat-value" style="color:${data.color}">${latest}<span class="insights-stat-unit">/25</span></div>
        </div>
        <div class="insights-stat">
            <div class="insights-stat-label">Best</div>
            <div class="insights-stat-value" style="color:${data.color}">${best}<span class="insights-stat-unit">/25</span></div>
        </div>
        <div class="insights-stat">
            <div class="insights-stat-label">Trend</div>
            <div class="insights-stat-value" style="color:${trendColor}">${trendSign}${trend}<span class="insights-stat-unit"> pts</span></div>
        </div>
        <div class="insights-stat">
            <div class="insights-stat-label">Sessions</div>
            <div class="insights-stat-value">${scores.length}</div>
        </div>
    `;

    // Chart
    if (_insightsChart) { _insightsChart.destroy(); _insightsChart = null; }
    const ctx = document.getElementById('insights-chart').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 160);
    grad.addColorStop(0, data.color + '33');
    grad.addColorStop(1, data.color + '00');

    _insightsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.dates,
            datasets: [{
                data: scores,
                borderColor: data.color,
                borderWidth: 2,
                pointBackgroundColor: data.color,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                backgroundColor: grad,
                tension: 0.35,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A2332',
                    borderColor: 'rgba(255,255,255,0.07)',
                    borderWidth: 1,
                    titleColor: '#8A9BB0',
                    bodyColor: '#F0EBE1',
                    callbacks: { label: ctx => ` ${ctx.parsed.y}/25` }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#4E5D72', font: { size: 10 } }
                },
                y: {
                    min: 0, max: 25,
                    grid: { color: 'rgba(255,255,255,0.04)' },
                    ticks: { color: '#4E5D72', font: { size: 10 }, stepSize: 5 }
                }
            }
        }
    });
}

function closeInsightsModal(e) {
    if (e && e.target !== document.getElementById('insights-modal')) return;
    document.getElementById('insights-modal').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('insights-modal').classList.remove('open');
        document.getElementById('summary-modal').classList.remove('open');
        const drawer = document.getElementById('mobile-nav-drawer');
        const toggle = document.getElementById('nav-mobile-toggle');
        drawer.classList.remove('open');
        toggle.classList.remove('open');
        document.body.style.overflow = '';
    }
});</script>

<script>
let _summaryCache = {};
function openSummaryModal(e, label, prompt) {
    e.preventDefault();
    e.stopPropagation();
    const modal = document.getElementById('summary-modal');
    const body  = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    title.textContent = label;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    if (_summaryCache[label]) {
        body.textContent = _summaryCache[label];
        return;
    }

    body.innerHTML = '<div class="modal-loading"><span class="modal-dot"></span><span class="modal-dot"></span><span class="modal-dot"></span> Generating summary…</div>';

    fetch('generate_summary.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prompt })
    })
    .then(r => r.json())
    .then(data => {
        const text = data.summary || 'Your personalised summary is ready on the Recommendations page.';
        _summaryCache[label] = text;
        body.textContent = text;
    })
    .catch(() => { body.textContent = 'Could not load summary. Please try again.'; });
}

function closeSummaryModal(e) {
    if (e && e.target !== document.getElementById('summary-modal')) return;
    document.getElementById('summary-modal').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('summary-modal').classList.remove('open');
        document.body.style.overflow = '';
    }
});

function toggleMobileNav(btn) {
    const drawer = document.getElementById('mobile-nav-drawer');
    const isOpen = drawer.classList.toggle('open');
    btn.classList.toggle('open', isOpen);
    btn.setAttribute('aria-expanded', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
    // close stats panel if open
    document.getElementById('nav-stats-panel').classList.remove('open');
}

function toggleNavStats(btn) {
    const panel = document.getElementById('nav-stats-panel');
    const isOpen = panel.classList.toggle('open');
    btn.setAttribute('aria-expanded', isOpen);
}

document.addEventListener('click', function(e) {
    const wrap = document.querySelector('.nav-user-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('nav-stats-panel').classList.remove('open');
        document.querySelector('.nav-user').setAttribute('aria-expanded', 'false');
    }
});

document.querySelectorAll('.value-card').forEach(card => {
    const color = card.dataset.color;
    if (!color) return;
    card.addEventListener('mouseenter', () => {
        card.style.borderColor = color + '55';
        card.style.boxShadow   = `0 8px 32px rgba(0,0,0,0.3), 0 0 0 1px ${color}22`;
    });
    card.addEventListener('mouseleave', () => {
        card.style.borderColor = '';
        card.style.boxShadow   = '';
    });
});
</script>
</body>
</html>
