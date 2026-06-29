<?php
require_once __DIR__ . '/bootstrap.php';
Auth::require();

$pdo  = Database::connect();
$user = Auth::user();
$uid  = Auth::id();

// ── Pillar + category definitions ────────────────────────────────────────────
$pillarDefs = [
    1 => [
        'label' => 'Connection & Love',
        'color' => '#C9A84C',
        'icon'  => '♡',
        'categories' => [
            'self_awareness'       => 'Self Awareness',
            'emotional_connection' => 'Emotional Connection',
            'family_friends'       => 'Family & Friends',
            'love_expression'      => 'Love Expression',
            'community'            => 'Community',
        ],
    ],
    2 => [
        'label' => 'Growth & Learning',
        'color' => '#4CAF7D',
        'icon'  => '✦',
        'categories' => [
            'curiosity_mindset'   => 'Curiosity & Mindset',
            'skill_building'      => 'Skill Building',
            'reflection_learning' => 'Reflection & Learning',
            'growth_mindset'      => 'Growth Mindset',
            'purpose_meaning'     => 'Purpose & Meaning',
        ],
    ],
    3 => [
        'label' => 'Contribution & Helping',
        'color' => '#7B9ED9',
        'icon'  => '◈',
        'categories' => [
            'proactive_help'      => 'Proactive Helping',
            'knowledge_sharing'   => 'Knowledge Sharing',
            'generosity'          => 'Generosity',
            'impact'              => 'Impact',
            'sustainable_service' => 'Sustainable Service',
        ],
    ],
    4 => [
        'label' => 'Freedom & Autonomy',
        'color' => '#B07EE8',
        'icon'  => '◎',
        'categories' => [
            'time_freedom'       => 'Time Freedom',
            'decision_freedom'   => 'Decision Freedom',
            'lifestyle_freedom'  => 'Lifestyle Freedom',
            'financial_autonomy' => 'Financial Autonomy',
            'value_alignment'    => 'Value Alignment',
        ],
    ],
    5 => [
        'label' => 'Security & Certainty',
        'color' => '#E8905A',
        'icon'  => '🛡',
        'categories' => [
            'financial_security'       => 'Financial Security',
            'emotional_safety'         => 'Emotional Safety',
            'health_physical_security' => 'Health & Physical Security',
            'stability_predictability' => 'Stability & Predictability',
            'security_trust'           => 'Security & Trust',
        ],
    ],
    6 => [
        'label' => 'Nature & Sustainability',
        'color' => '#6DBF7E',
        'icon'  => '◉',
        'categories' => [
            'nature_connection'       => 'Nature Connection',
            'sustainable_living'      => 'Sustainable Living',
            'natural_rhythms'         => 'Natural Rhythms',
            'environmental_awareness' => 'Environmental Awareness',
            'nature_restoration'      => 'Nature & Restoration',
        ],
    ],
    7 => [
        'label' => 'Achievement & Mastery',
        'color' => '#E8C45A',
        'icon'  => '◆',
        'categories' => [
            'goal_setting'          => 'Goal Setting',
            'skill_development'     => 'Skill Development',
            'competence_confidence' => 'Competence & Confidence',
            'overcoming_challenges' => 'Overcoming Challenges',
            'balance_achievement'   => 'Balance in Achievement',
        ],
    ],
];

$guidedPrompts = [
    'self_awareness'       => 'How well do you feel you know your own emotions and patterns today?',
    'emotional_connection' => 'Describe a moment recently where you felt truly emotionally connected to someone.',
    'family_friends'       => 'How are your closest relationships feeling right now? What is one thing you appreciate?',
    'love_expression'      => 'How have you expressed love or care to someone recently? How did it feel?',
    'community'            => 'Where do you feel a sense of belonging in your community?',
    'curiosity_mindset'   => 'What have you been genuinely curious about lately? What new idea or topic has captured your attention?',
    'skill_building'      => 'What skill are you currently working to develop? What does progress feel like right now?',
    'reflection_learning' => 'What is one thing you have learned recently — from an experience, a mistake, or a conversation?',
    'growth_mindset'      => 'Where have you recently chosen growth over comfort? How did it feel to push through?',
    'purpose_meaning'     => 'What feels most meaningful and purposeful to you right now? How are your actions connected to that?',
    'proactive_help'      => 'Where have you taken initiative or stepped in to help someone recently, without being asked?',
    'knowledge_sharing'   => 'What knowledge, skill, or insight have you shared with others lately? How did it feel?',
    'generosity'          => 'Describe a recent act of generosity — your time, energy, or resources. How did giving feel?',
    'impact'              => 'Where do you feel you are making a real difference, however small? What evidence do you see?',
    'sustainable_service' => 'Are you giving in a way that also replenishes you? Reflect on your balance of giving and self-care.',
    'time_freedom'         => 'How much control do you feel over how you spend your time?',
    'decision_freedom'     => 'Describe a recent decision you made that felt truly your own.',
    'lifestyle_freedom'    => 'Are you living in a way that aligns with who you want to be?',
    'financial_autonomy'   => 'How does your financial situation support or limit your freedom right now?',
    'value_alignment'      => 'Are your daily actions reflecting your deepest values? Where is the gap?',
    'financial_security'       => 'How secure do you feel about your finances right now? What would help?',
    'emotional_safety'         => 'Do you feel emotionally safe in your closest relationships? Reflect on why.',
    'health_physical_security' => 'How are you feeling physically? What gives you a sense of bodily security?',
    'stability_predictability' => 'How stable does your daily life feel right now? What unsettles you most?',
    'security_trust'           => 'Where do you feel most secure and grounded — and where do you feel most uncertain?',
    'nature_connection'       => 'When did you last feel truly connected to nature? How did it affect you?',
    'sustainable_living'      => 'What sustainable habits are you proud of, and where do you want to improve?',
    'natural_rhythms'         => 'How well are your routines aligned with natural cycles of rest and activity?',
    'environmental_awareness' => 'How do you feel about your personal impact on the environment right now?',
    'nature_restoration'      => 'How does spending time in nature restore your energy and mood?',
    'goal_setting'          => 'What goal are you currently working toward, and how is your progress feeling?',
    'skill_development'     => 'What skill are you developing right now? What does growth feel like?',
    'competence_confidence' => 'Where do you feel most capable and confident in your life right now?',
    'overcoming_challenges' => 'What challenge are you facing, and what have you learned from it so far?',
    'balance_achievement'   => 'Are you pursuing your goals in a way that still leaves room for rest and joy?',
];

$activePillarId = (int)($_GET['pillar'] ?? 1);
if (!isset($pillarDefs[$activePillarId])) { $activePillarId = 1; }
$activeCategory = $_GET['category'] ?? array_key_first($pillarDefs[$activePillarId]['categories']);
if (!isset($pillarDefs[$activePillarId]['categories'][$activeCategory])) {
    $activeCategory = array_key_first($pillarDefs[$activePillarId]['categories']);
}

$activePillar   = $pillarDefs[$activePillarId];
$activeColor    = $activePillar['color'];
$activeCatLabel = $activePillar['categories'][$activeCategory];
$activePrompt   = $guidedPrompts[$activeCategory] ?? '';

// Fetch past entries
$stmt = $pdo->prepare("
    SELECT id, mood, entry_text, created_at
    FROM journal_entries
    WHERE user_id = ? AND tenant_id = ? AND pillar_id = ? AND category = ?
    ORDER BY created_at DESC LIMIT 20
");
$stmt->execute([$uid, $user['tenant_id'], $activePillarId, $activeCategory]);
$pastEntries = $stmt->fetchAll();

// Entry counts per pillar
$countStmt = $pdo->prepare("SELECT pillar_id, COUNT(*) as cnt FROM journal_entries WHERE user_id = ? AND tenant_id = ? GROUP BY pillar_id");
$countStmt->execute([$uid, $user['tenant_id']]);
$pillarCounts = [];
foreach ($countStmt->fetchAll() as $row) { $pillarCounts[$row['pillar_id']] = $row['cnt']; }

$moodEmojis = [1 => '😔', 2 => '😕', 3 => '😐', 4 => '🙂', 5 => '😄'];
$moodLabels = [1 => 'Struggling', 2 => 'Low', 3 => 'Neutral', 4 => 'Good', 5 => 'Great'];
$moodSvgs = [
    1 => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#E05252" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#E05252"/><circle cx="26" cy="16" r="2" fill="#E05252"/><path d="M13 27c1.5-3 12.5-3 14 0" stroke="#E05252" stroke-width="2" stroke-linecap="round"/><path d="M13 12l3 2M27 12l-3 2" stroke="#E05252" stroke-width="1.5" stroke-linecap="round"/></svg>',
    2 => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#E08A52" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#E08A52"/><circle cx="26" cy="16" r="2" fill="#E08A52"/><path d="M13 26c1.5-2 12.5-2 14 0" stroke="#E08A52" stroke-width="2" stroke-linecap="round"/></svg>',
    3 => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#C9A84C" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#C9A84C"/><circle cx="26" cy="16" r="2" fill="#C9A84C"/><path d="M14 26h12" stroke="#C9A84C" stroke-width="2" stroke-linecap="round"/></svg>',
    4 => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#7DC97D" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#7DC97D"/><circle cx="26" cy="16" r="2" fill="#7DC97D"/><path d="M13 23c1.5 3 12.5 3 14 0" stroke="#7DC97D" stroke-width="2" stroke-linecap="round"/></svg>',
    5 => '<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#4CAF7D" stroke-width="2"/><circle cx="14" cy="15" r="2.5" fill="#4CAF7D"/><circle cx="26" cy="15" r="2.5" fill="#4CAF7D"/><path d="M12 22c1 5 15 5 16 0" stroke="#4CAF7D" stroke-width="2" stroke-linecap="round"/></svg>',
];
?>
<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal — Mental Wellbeing Audit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root {
            --bg:#0D1117; --surface:#141B24; --surface2:#1A2332; --surface3:#1F2B3E;
            --border:rgba(255,255,255,0.07); --gold:#C9A84C; --gold-dim:rgba(201,168,76,0.12);
            --text:#F0EBE1; --muted:#4E5D72; --muted2:#8A9BB0; --radius:14px;
            --ac:<?= $activeColor ?>;
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
        .btn-logout { font-size:0.8rem; color:var(--muted2); text-decoration:none; background:var(--surface2); border:1px solid var(--border); border-radius:7px; padding:5px 12px; transition:color 0.15s,border-color 0.15s; }
        .btn-logout:hover { color:var(--text); border-color:var(--muted2); }

        .page { max-width:1120px; margin:0 auto; padding:10px 32px 40px; position:relative; z-index:1; display:grid; grid-template-columns:220px 1fr; gap:16px; align-items:start; }
        .page-header { grid-column:1/-1; margin-bottom:0; }
        .page-eyebrow { font-size:0.65rem; font-weight:500; letter-spacing:0.12em; text-transform:uppercase; color:var(--gold); margin-bottom:2px; }
        .page-title { font-family:'DM Sans',sans-serif; font-size:1rem; font-weight:600; letter-spacing:-0.01em; color:var(--muted2); }

        /* Sidebar */
        .sidebar { display:flex; flex-direction:column; gap:6px; position:sticky; top:78px; }
        .sidebar-pillar { border-radius:10px; overflow:hidden; border:1px solid var(--border); }
        .sidebar-pillar-btn { width:100%; display:flex; align-items:center; gap:10px; padding:11px 14px; background:var(--surface); border:none; color:var(--text); font-family:inherit; font-size:0.875rem; font-weight:500; cursor:pointer; text-decoration:none; transition:background 0.15s; text-align:left; }
        .sidebar-pillar-btn:hover { background:var(--surface2); }
        .sidebar-pillar-btn.active { background:var(--surface2); }
        .sidebar-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .sidebar-pillar-name { flex:1; }
        .sidebar-count { font-size:0.7rem; color:var(--muted); background:var(--surface3); border-radius:99px; padding:1px 7px; }
        .sidebar-cats { background:var(--surface); border-top:1px solid var(--border); }
        .sidebar-cat-link { display:block; padding:8px 14px 8px 32px; font-size:0.82rem; color:var(--muted2); text-decoration:none; transition:color 0.15s,background 0.15s; border-left:2px solid transparent; }
        .sidebar-cat-link:hover { color:var(--text); background:var(--surface2); }
        .sidebar-cat-link.active { color:var(--ac); border-left-color:var(--ac); background:var(--surface2); font-weight:500; }

        /* Main */
        .journal-main { display:flex; flex-direction:column; gap:20px; }

        .entry-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
        .entry-card-header { padding:14px 20px 0; }
        .entry-cat-label { font-size:0.7rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:8px; color:var(--ac); }
        .entry-prompt { font-size:0.825rem; color:var(--text); line-height:1.65; font-style:italic; padding:10px 14px; background:var(--surface2); border-radius:8px; border-left:3px solid var(--ac); margin-bottom:16px; }

        .mood-row { display:flex; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
        .mood-label { font-size:0.75rem; font-weight:500; color:var(--muted2); margin-bottom:10px; display:block; }

        .entry-body { display:flex; flex-direction:column; border-top:1px solid var(--border); }
        .entry-mood-col { padding:16px 14px 16px 20px; border-right:1px solid var(--border); display:flex; flex-direction:column; align-items:flex-start; flex-shrink:0; }
        .mood-col-btns { display:flex; flex-direction:row; gap:8px; }
        .mood-btn { width:58px; height:74px; border-radius:12px; border:2px solid var(--border); background:var(--surface2); cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; transition:border-color 0.15s,transform 0.15s,background 0.15s; padding:0; }
        .mood-btn:hover { transform:translateY(-3px); border-color:var(--ac); }
        .mood-btn.selected { border-color:var(--ac); background:color-mix(in srgb, var(--ac) 12%, transparent); }
        .mood-btn svg { width:36px; height:36px; }
        .mood-btn-label { font-size:0.62rem; color:var(--text); font-family:'DM Sans',sans-serif; }

        .entry-text-col { display:flex; flex-direction:column; min-height:0; }
        textarea.entry-textarea { flex:1; width:100%; background:transparent; border:none; color:var(--text); font-family:'DM Sans',sans-serif; font-size:0.9rem; line-height:1.7; padding:16px 20px; resize:none; min-height:0; height:100%; outline:none; }
        textarea.entry-textarea::placeholder { color:var(--muted2); }

        .entry-footer { display:flex; align-items:center; justify-content:space-between; padding:12px 20px; border-top:1px solid var(--border); }

        .entry-meta { display:flex; align-items:center; gap:14px; }
        .char-count { font-size:0.75rem; color:var(--muted); }
        .save-feedback { font-size:0.8rem; color:#4CAF7D; opacity:0; transition:opacity 0.3s; }
        .save-feedback.show { opacity:1; }
        .btn-save { font-size:0.85rem; font-weight:500; padding:8px 22px; border-radius:8px; border:none; cursor:pointer; background:var(--ac); color:#0D1117; font-family:inherit; transition:opacity 0.15s,transform 0.1s; }
        .btn-save:hover:not(:disabled) { opacity:0.85; transform:translateY(-1px); }
        .btn-save:disabled { opacity:0.4; cursor:not-allowed; }

        .past-header { display:flex; align-items:center; justify-content:space-between; }
        .past-title { font-family:'Playfair Display',serif; font-size:1rem; font-weight:600; }
        .past-badge { font-size:0.72rem; color:var(--muted); background:var(--surface2); border:1px solid var(--border); border-radius:99px; padding:2px 10px; }

        .past-empty { background:var(--surface); border:1px dashed var(--border); border-radius:var(--radius); padding:28px; text-align:center; color:var(--muted2); font-size:0.875rem; margin-top:10px; }

        .entry-list { display:flex; flex-direction:column; gap:10px; margin-top:10px; }
        .entry-item { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 20px; }
        .entry-item-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
        .entry-date { font-size:0.72rem; color:var(--muted2); }
        .entry-mood-icon { width:44px; height:44px; border-radius:10px; border:2px solid var(--border); background:var(--surface2); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; flex-shrink:0; }
        .entry-mood-icon svg { width:28px; height:28px; }
        .entry-mood-icon-label { font-size:0.58rem; color:var(--muted2); font-family:'DM Sans',sans-serif; }
        .entry-text { font-size:0.875rem; color:var(--muted2); line-height:1.65; white-space:pre-wrap; word-break:break-word; }
        /* Hamburger — hidden on desktop always */
        .nav-hamburger { display:none; flex-direction:column; gap:5px; background:none; border:none; cursor:pointer; padding:4px; }
        .nav-hamburger span { display:block; width:22px; height:2px; background:var(--muted2); border-radius:2px; transition:transform 0.2s,opacity 0.2s; }
        .nav-hamburger[aria-expanded="true"] span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
        .nav-hamburger[aria-expanded="true"] span:nth-child(2) { opacity:0; }
        .nav-hamburger[aria-expanded="true"] span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }

        /* Mobile menu — forced hidden on desktop regardless of JS .open class */
        .nav-mobile-menu { display:none !important; position:fixed; top:54px; left:0; right:0; z-index:99; background:rgba(13,17,23,0.97); backdrop-filter:blur(16px); border-bottom:1px solid var(--border); flex-direction:column; padding:8px 0 12px; }
        .nav-mobile-link { padding:12px 20px; font-size:0.9rem; color:var(--muted2); text-decoration:none; transition:color 0.15s,background 0.15s; }
        .nav-mobile-link:hover { color:var(--text); background:var(--surface); }
        .nav-mobile-link.active { color:var(--gold); }
        .nav-mobile-signout { border-top:1px solid var(--border); margin-top:4px; padding-top:14px; color:var(--muted); }

        /* ── Mobile & Tablet Responsive ─────────────────────────────────── */
        @media (max-width:900px) {
            .page { padding:10px 20px 40px; gap:14px; }
        }

        @media (max-width:768px) {
            /* Nav */
            .topnav { padding:0 14px; height:54px; }
            .nav-brand-name { font-size:0.9rem; }
            .nav-link { display:none; }
            .nav-name { display:none; }
            .nav-right { gap:10px; }
            .btn-logout { display:none; }
            .nav-hamburger { display:flex; }
            .nav-mobile-menu { display:none !important; }
            .nav-mobile-menu.open { display:flex !important; }

            /* Page grid: single column */
            .page { grid-template-columns:1fr; padding:14px 14px 60px; gap:14px; }

            /* Sidebar: accordion-style stacked list */
            .sidebar { position:static; flex-direction:column; gap:4px; }
            .sidebar-pillar { flex:none; width:100%; }
            .sidebar-pillar-btn { padding:10px 12px; font-size:0.82rem; }
            .sidebar-cat-link { padding:7px 12px 7px 28px; font-size:0.8rem; }

            /* Entry card */
            .entry-card-header { padding:12px 14px 0; }
            .entry-prompt { font-size:0.8rem; padding:8px 12px; margin-bottom:12px; }
            .entry-cat-label { font-size:0.65rem; }

            /* Entry body: stack mood + textarea vertically */
            .entry-body { flex-direction:column; }
            .entry-mood-col {
                border-right:none;
                border-bottom:1px solid var(--border);
                padding:12px 14px;
                align-items:flex-start;
            }
            .mood-col-btns { flex-direction:row; flex-wrap:wrap; gap:6px; }
            .mood-btn { width:52px; height:66px; border-radius:10px; }
            .mood-btn svg { width:30px; height:30px; }
            .mood-btn-label { font-size:0.58rem; }

            .entry-text-col { min-height:140px; }
            textarea.entry-textarea { padding:12px 14px; font-size:0.875rem; min-height:140px; height:auto; }

            /* Footer */
            .entry-footer { padding:10px 14px; flex-wrap:wrap; gap:8px; }
            .btn-save { width:100%; text-align:center; padding:10px; }
            .entry-meta { width:100%; justify-content:space-between; }

            /* Past entries */
            .past-header { margin-top:4px; }
            .entry-item { padding:12px 14px; }
            .entry-mood-icon { width:38px; height:38px; }
            .entry-mood-icon svg { width:24px; height:24px; }
            .entry-mood-icon-label { font-size:0.52rem; }
        }

        @media (max-width:400px) {
            .topnav { padding:0 10px; }
            .nav-brand-name { display:none; }
            .page { padding:10px 10px 60px; }
            .mood-btn { width:46px; height:60px; }
            .mood-btn svg { width:26px; height:26px; }
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
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="view_recommendations.php" class="nav-link">Recommendations</a>
        <a href="journal.php" class="nav-link active">Journal</a>
        <div class="nav-user">
            <div class="nav-avatar"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
            <span class="nav-name"><?= htmlspecialchars($user['name']) ?></span>
            <a href="logout.php" class="btn-logout">Sign out</a>
        </div>
        <button class="nav-hamburger" id="nav-hamburger" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>
<div class="nav-mobile-menu" id="nav-mobile-menu" aria-hidden="true">
    <a href="dashboard.php" class="nav-mobile-link">Dashboard</a>
    <a href="view_recommendations.php" class="nav-mobile-link">Recommendations</a>
    <a href="journal.php" class="nav-mobile-link active">Journal</a>
    <a href="logout.php" class="nav-mobile-link nav-mobile-signout">Sign out</a>
</div>

<div class="page">
    <div class="page-header">
        <p class="page-eyebrow">Your reflections</p>
        <h1 class="page-title">Journal</h1>
    </div>

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="sidebar">
        <?php foreach ($pillarDefs as $pid => $p): ?>
        <div class="sidebar-pillar">
            <a href="?pillar=<?= $pid ?>&amp;category=<?= array_key_first($p['categories']) ?>"
               class="sidebar-pillar-btn <?= $pid === $activePillarId ? 'active' : '' ?>">
                <span class="sidebar-dot" style="background:<?= $p['color'] ?>"></span>
                <span class="sidebar-pillar-name"><?= htmlspecialchars($p['label']) ?></span>
                <?php if (!empty($pillarCounts[$pid])): ?>
                    <span class="sidebar-count"><?= $pillarCounts[$pid] ?></span>
                <?php endif; ?>
            </a>
            <?php if ($pid === $activePillarId): ?>
            <div class="sidebar-cats">
                <?php foreach ($p['categories'] as $catKey => $catLabel): ?>
                    <a href="?pillar=<?= $pid ?>&amp;category=<?= $catKey ?>"
                       class="sidebar-cat-link <?= $catKey === $activeCategory ? 'active' : '' ?>">
                        <?= htmlspecialchars($catLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </aside>

    <!-- ── Journal main ───────────────────────────────────────────── -->
    <div class="journal-main">

        <!-- Write -->
        <div class="entry-card">
            <div class="entry-card-header">
                <div class="entry-cat-label"><?= htmlspecialchars($activeCatLabel) ?></div>
                <?php if ($activePrompt): ?>
                    <div class="entry-prompt"><?= htmlspecialchars($activePrompt) ?></div>
                <?php endif; ?>
            </div>
            <div class="entry-body">
                <!-- textarea -->
                <div class="entry-text-col">
                    <textarea class="entry-textarea" id="entry-text" placeholder="Write your reflection here…"></textarea>
                </div>
            </div>
            <div class="entry-footer">
                <div class="entry-meta">
                    <span class="char-count" id="char-count">0 characters</span>
                    <span class="save-feedback" id="save-feedback">✓ Saved</span>
                </div>
                <button class="btn-save" id="btn-save" onclick="saveEntry()">Save Entry</button>
            </div>
        </div>

        <!-- Past entries -->
        <div>
            <div class="past-header">
                <h2 class="past-title">Past Entries</h2>
                <span class="past-badge" id="past-badge"><?= count($pastEntries) ?> <?= count($pastEntries) === 1 ? 'entry' : 'entries' ?></span>
            </div>

            <?php if (empty($pastEntries)): ?>
                <div class="past-empty" id="past-empty">No entries yet for <strong><?= htmlspecialchars($activeCatLabel) ?></strong>. Write your first reflection above.</div>
            <?php else: ?>
            <div class="entry-list" id="entry-list">
                <?php foreach ($pastEntries as $e): ?>
                <div class="entry-item">
                    <div class="entry-item-top">
                        <span class="entry-date" data-ts="<?= strtotime($e['created_at']) * 1000 ?>"></span>
                        <?php if ($e['mood'] && isset($moodSvgs[$e['mood']])): ?>
                            <div class="entry-mood-icon" title="<?= $moodLabels[$e['mood']] ?>">
                                <?= $moodSvgs[$e['mood']] ?>
                                <span class="entry-mood-icon-label"><?= $moodLabels[$e['mood']] ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="entry-text"><?= htmlspecialchars($e['entry_text']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
const PILLAR_ID = <?= $activePillarId ?>;
const CATEGORY  = <?= json_encode($activeCategory) ?>;
let selectedMood = null;

document.querySelectorAll('.mood-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedMood = parseInt(btn.dataset.mood);
    });
});

const textarea  = document.getElementById('entry-text');
const charCount = document.getElementById('char-count');
textarea.addEventListener('input', () => {
    const n = textarea.value.length;
    charCount.textContent = n + ' character' + (n === 1 ? '' : 's');
});

async function saveEntry() {
    const text = textarea.value.trim();
    if (!text) { textarea.focus(); return; }

    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const res  = await fetch('save_journal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pillar_id: PILLAR_ID, category: CATEGORY, entry_text: text, mood: selectedMood })
        });
        const data = await res.json();
        if (!data.success) throw new Error();

        // Feedback
        const fb = document.getElementById('save-feedback');
        fb.classList.add('show');
        setTimeout(() => fb.classList.remove('show'), 3000);

        // Prepend entry to list
        prependEntry(data.entry_id, text, selectedMood, data.created_at);

        // Reset form
        textarea.value = '';
        charCount.textContent = '0 characters';
        document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('selected'));
        selectedMood = null;

    } catch(e) {
        alert('Could not save. Please try again.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Entry';
    }
}

function moodIcon(mood) {
    if (!mood) return '';
    const labels  = {1:'Struggling',2:'Low',3:'Neutral',4:'Good',5:'Great'};
    const svgs = {
        1: `<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#E05252" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#E05252"/><circle cx="26" cy="16" r="2" fill="#E05252"/><path d="M13 27c1.5-3 12.5-3 14 0" stroke="#E05252" stroke-width="2" stroke-linecap="round"/><path d="M13 12l3 2M27 12l-3 2" stroke="#E05252" stroke-width="1.5" stroke-linecap="round"/></svg>`,
        2: `<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#E08A52" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#E08A52"/><circle cx="26" cy="16" r="2" fill="#E08A52"/><path d="M13 26c1.5-2 12.5-2 14 0" stroke="#E08A52" stroke-width="2" stroke-linecap="round"/></svg>`,
        3: `<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#C9A84C" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#C9A84C"/><circle cx="26" cy="16" r="2" fill="#C9A84C"/><path d="M14 26h12" stroke="#C9A84C" stroke-width="2" stroke-linecap="round"/></svg>`,
        4: `<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#7DC97D" stroke-width="2"/><circle cx="14" cy="16" r="2" fill="#7DC97D"/><circle cx="26" cy="16" r="2" fill="#7DC97D"/><path d="M13 23c1.5 3 12.5 3 14 0" stroke="#7DC97D" stroke-width="2" stroke-linecap="round"/></svg>`,
        5: `<svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="20" r="18" stroke="#4CAF7D" stroke-width="2"/><circle cx="14" cy="15" r="2.5" fill="#4CAF7D"/><circle cx="26" cy="15" r="2.5" fill="#4CAF7D"/><path d="M12 22c1 5 15 5 16 0" stroke="#4CAF7D" stroke-width="2" stroke-linecap="round"/></svg>`,
    };
    return `<div class="entry-mood-icon" title="${labels[mood]}">${svgs[mood]}<span class="entry-mood-icon-label">${labels[mood]}</span></div>`;
}

function prependEntry(id, text, mood, createdAt) {
    const d = new Date(createdAt);
    const dateStr = d.toLocaleDateString('en-GB',{weekday:'short',month:'short',day:'numeric',year:'numeric'})
                  + ' · ' + d.toLocaleTimeString('en-GB',{hour:'numeric',minute:'2-digit'});

    const div = document.createElement('div');
    div.className = 'entry-item';
    div.dataset.entryId = id;
    div.innerHTML = `
        <div class="entry-item-top">
            <span class="entry-date">${dateStr}</span>
            ${mood ? moodIcon(mood) : ''}
        </div>
        <div class="entry-text">${esc(text)}</div>`;

    // Remove empty state if present
    const empty = document.getElementById('past-empty');
    if (empty) empty.remove();

    let list = document.getElementById('entry-list');
    if (!list) {
        list = document.createElement('div');
        list.id = 'entry-list';
        list.className = 'entry-list';
        document.querySelector('.journal-main > div:last-child').appendChild(list);
    }
    list.prepend(div);

    // Update badge
    const badge = document.getElementById('past-badge');
    const n = list.children.length;
    badge.textContent = n + ' ' + (n === 1 ? 'entry' : 'entries');
}

function esc(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Reformat server-rendered entry dates into browser local time
document.querySelectorAll('.entry-date[data-ts]').forEach(el => {
    const d = new Date(parseInt(el.dataset.ts));
    el.textContent = d.toLocaleDateString('en-GB',{weekday:'short',month:'short',day:'numeric',year:'numeric'})
                   + ' · ' + d.toLocaleTimeString('en-GB',{hour:'numeric',minute:'2-digit'});
});
// Hamburger menu toggle
const hamburger = document.getElementById('nav-hamburger');
const mobileMenu = document.getElementById('nav-mobile-menu');
if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
        const open = mobileMenu.classList.toggle('open');
        hamburger.setAttribute('aria-expanded', open);
        mobileMenu.setAttribute('aria-hidden', !open);
    });
    // Close when a link is clicked
    mobileMenu.querySelectorAll('.nav-mobile-link').forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('open');
            hamburger.setAttribute('aria-expanded', false);
            mobileMenu.setAttribute('aria-hidden', true);
        });
    });
}
</script>
</body>
</html>
