<?php
require_once __DIR__ . '/bootstrap.php';
Auth::require();

$pdo  = Database::connect();
$user = Auth::user();

// ── Pillar config ─────────────────────────────────────────────────────────────
$PILLAR_CONFIG = [
    1 => [
        'title'    => 'Connection', 'accent' => '&amp; Love',
        'subtitle' => 'Explore your relationships, intimacy, community, and the ways you give and receive love.',
        'table'    => 'connection_love_assessments',
        'categories' => [
            'self_awareness'       => 'Self-Awareness &amp; Self-Compassion',
            'emotional_connection' => 'Emotional Connection &amp; Intimacy',
            'family_friends'       => 'Family &amp; Friends',
            'love_expression'      => 'Love Expression',
            'community'            => 'Community &amp; Belonging',
        ],
        'improve_placeholder'   => 'Which areas feel most urgent or impactful to address?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days?',
    ],
    2 => [
        'title'    => 'Growth', 'accent' => 'and Learning',
        'subtitle' => 'Reflect on how you nurture curiosity, build skills, learn from experience, develop a growth mindset, and connect your efforts to a deeper sense of purpose.',
        'table'    => 'growth_learning_assessments',
        'categories' => [
            'curiosity_mindset'   => 'Curiosity &amp; Mindset',
            'skill_building'      => 'Skill Building',
            'reflection_learning' => 'Reflection &amp; Learning',
            'growth_mindset'      => 'Growth Mindset',
            'purpose_meaning'     => 'Purpose &amp; Meaning',
        ],
        'improve_placeholder'   => 'Which learning habits or mindsets feel most important to develop?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days to grow?',
    ],
    3 => [
        'title'    => 'Contribution', 'accent' => '&amp; Helping',
        'subtitle' => 'Reflect on how you proactively help, share knowledge, engage with your community, and create meaningful impact.',
        'table'    => 'contribution_assessments',
        'categories' => [
            'proactive_help'    => 'Proactive Helping',
            'knowledge_sharing' => 'Sharing Knowledge &amp; Mentoring',
            'generosity'        => 'Generosity &amp; Community Engagement',
            'impact'            => 'Impact-Oriented Contribution',
            'sustainable_service' => 'Sustainable Helping &amp; Resilience',
        ],
        'improve_placeholder'   => 'Which contribution habits feel most important to develop?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days to give more meaningfully?',
    ],
    4 => [
        'title'    => 'Freedom', 'accent' => '&amp; Autonomy',
        'subtitle' => 'Reflect on how freely you spend your time, make decisions, shape your environment, and live by your own values.',
        'table'    => 'freedom_autonomy_assessments',
        'categories' => [
            'time_freedom' => 'Time Freedom', 'decision_freedom' => 'Decision &amp; Choice Freedom',
            'lifestyle_freedom' => 'Location &amp; Lifestyle Freedom', 'financial_autonomy' => 'Financial Autonomy',
            'value_alignment' => 'Psychological &amp; Value Alignment',
        ],
        'improve_placeholder'   => 'Which areas of freedom or autonomy feel most important to work on?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days to build more freedom?',
    ],
    5 => [
        'title'    => 'Security', 'accent' => '&amp; Certainty',
        'subtitle' => 'Reflect on your sense of financial stability, emotional safety, physical security, and your ability to face uncertainty with confidence.',
        'table'    => 'security_certainty_assessments',
        'categories' => [
            'financial_security'       => 'Financial Security',
            'emotional_safety'         => 'Emotional &amp; Psychological Safety',
            'health_physical_security' => 'Health &amp; Physical Security',
            'stability_predictability' => 'Stability &amp; Predictability',
            'security_trust'           => 'Sense of Security &amp; Trust',
        ],
        'improve_placeholder'   => 'Which areas of security or certainty feel most important to strengthen?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days to build more security?',
    ],
    6 => [
        'title'    => 'Nature', 'accent' => '&amp; Sustainability',
        'subtitle' => 'Reflect on your connection to the natural world, your sustainable living habits, and how nature supports your well-being.',
        'table'    => 'nature_sustainability_assessments',
        'categories' => [
            'nature_connection'       => 'Connection with Nature',
            'sustainable_living'      => 'Sustainable Living Practices',
            'natural_rhythms'         => 'Alignment with Natural Rhythms',
            'environmental_awareness' => 'Environmental Awareness &amp; Contribution',
            'nature_restoration'      => 'Nature as a Source of Joy &amp; Restoration',
        ],
        'improve_placeholder'   => 'Which aspects of nature connection or sustainability feel most important to develop?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days to deepen your connection with nature?',
    ],
    7 => [
        'title'    => 'Achievement', 'accent' => '&amp; Mastery',
        'subtitle' => 'Reflect on how you set goals, develop skills, build confidence, celebrate achievements, and overcome challenges.',
        'table'    => 'achievement_mastery_assessments',
        'categories' => [
            'goal_setting'          => 'Goal Setting &amp; Progress',
            'skill_development'     => 'Skill Development &amp; Mastery',
            'competence_confidence' => 'Competence &amp; Confidence',
            'recognition_achievement' => 'Recognition &amp; Achievement',
            'overcoming_challenges' => 'Overcoming Challenges &amp; Resilience',
       ],
        'improve_placeholder'   => 'Which areas of achievement or mastery feel most important to focus on?',
        'nextsteps_placeholder' => 'What specific, actionable steps will you take in the next 30 days to grow your mastery?',
    ],
];

$pillarId = isset($_GET['pillar']) && isset($PILLAR_CONFIG[(int)$_GET['pillar']])
    ? (int)$_GET['pillar'] : null;

if (!$pillarId) { header('Location: dashboard.php'); exit; }

$config       = $PILLAR_CONFIG[$pillarId];
$errorMessage = '';

// ── Load questions ────────────────────────────────────────────────────────────
function loadQuestions(PDO $pdo, array $categoryFields): array {
    $placeholders = implode(',', array_fill(0, count($categoryFields), '?'));
    try {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE category_field IN ({$placeholders}) ORDER BY sort_order, id");
        $stmt->execute($categoryFields);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        return array_fill_keys($categoryFields, []);
    }
    if (empty($rows)) return array_fill_keys($categoryFields, []);
    $map = [];
    foreach ($rows as $r) { $map[$r['category_field']][$r['question_key']] = $r['question_text']; }
    $ordered = [];
    foreach ($categoryFields as $f) { $ordered[$f] = $map[$f] ?? []; }
    return $ordered;
}

$questions = loadQuestions($pdo, array_keys($config['categories']));

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $cb     = fn($key) => isset($_POST[$key]) ? 1 : 0;
        $rt     = fn($key) => isset($_POST[$key]) && $_POST[$key] !== '' ? (int)$_POST[$key] : null;
        $tx     = fn($key) => isset($_POST[$key]) ? trim($_POST[$key]) : '';
        $userId = Auth::id();

        $cols = ['user_id', 'tenant_id']; $pholdr = [':user_id', ':tenant_id']; $params = [':user_id' => $userId, ':tenant_id' => $user['tenant_id']];

        foreach ($questions as $cat => $qs) {
            foreach ($qs as $qKey => $qText) {
                $col = "{$cat}_{$qKey}";
                $cols[] = $col; $pholdr[] = ":{$col}"; $params[":{$col}"] = $cb($col);
            }
            $cols[] = "{$cat}_rating";  $pholdr[] = ":{$cat}_rating";  $params[":{$cat}_rating"]  = $rt("{$cat}_rating");
            $cols[] = "{$cat}_notes";   $pholdr[] = ":{$cat}_notes";   $params[":{$cat}_notes"]   = $tx("{$cat}_notes");
        }
        $cols[] = 'top_improvement_areas'; $pholdr[] = ':top_improvement_areas'; $params[':top_improvement_areas'] = $tx('top_improvement_areas');
        $cols[] = 'next_steps';            $pholdr[] = ':next_steps';            $params[':next_steps']            = $tx('next_steps');

        $sql  = "INSERT INTO {$config['table']} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $pholdr) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $totalScore = 0;
        foreach (array_keys($questions) as $cat) { $totalScore += $rt("{$cat}_rating") ?? 0; }

        $scoreStmt = $pdo->prepare("INSERT INTO total_scores (user_id, tenant_id, pillar_id, total_score) VALUES (:user_id, :tenant_id, :pillar_id, :total_score)");
        $scoreStmt->execute([':user_id' => $userId, ':tenant_id' => $user['tenant_id'], ':pillar_id' => $pillarId, ':total_score' => $totalScore]);

        header("Location: view_results.php?pillar={$pillarId}&saved=1");
        exit;
    } catch (PDOException $e) {
        error_log('Assessment save error: ' . $e->getMessage());
        $errorMessage = 'Error saving assessment. Please try again.';
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function renderCheckbox(string $name, string $text): string {
    $safe = htmlspecialchars($text);
    return "<label class=\"checkbox-label\"><input type=\"checkbox\" name=\"{$name}\" value=\"1\"><span>{$safe}</span></label>";
}

function renderRating(string $prefix, string $id_prefix): string {
    $html = '<div class="rating-group"><div class="rating-label">Overall Rating</div><div class="rating-buttons">';
    for ($i = 1; $i <= 5; $i++) {
        $id  = "{$id_prefix}_r{$i}";
        $req = $i === 1 ? ' required' : '';
        $html .= "<input type=\"radio\" name=\"{$prefix}_rating\" value=\"{$i}\" id=\"{$id}\"{$req}>";
        $html .= "<label for=\"{$id}\">{$i}</label>";
    }
    $html .= '</div><div class="rating-anchors"><span>Needs improvement</span><span>Thriving</span></div></div>';
    return $html;
}

$categoryKeys = array_keys($config['categories']);
$jsCategories = json_encode($categoryKeys);
$totalCats    = count($categoryKeys);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(html_entity_decode($config['title'] . ' ' . $config['accent'])) ?> — Assessment</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        .topnav { position:sticky; top:0; z-index:100; background:rgba(13,17,23,0.88); backdrop-filter:blur(16px); border-bottom:1px solid rgba(255,255,255,0.07); padding:0 32px; display:flex; align-items:center; justify-content:space-between; height:62px; }
        .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .nav-brand-icon { width:32px; height:32px; background:rgba(201,168,76,0.12); border:1px solid rgba(201,168,76,0.25); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; color:#C9A84C; }
        .nav-brand-name { font-family:'Playfair Display',serif; font-size:1rem; font-weight:600; color:#F0EBE1; }
        .nav-brand-name span { color:#C9A84C; }
        .nav-right { display:flex; align-items:center; gap:20px; }
        .nav-link { font-size:0.875rem; color:#8A9BB0; text-decoration:none; transition:color 0.15s; }
        .nav-link:hover { color:#F0EBE1; }
        .nav-user { display:flex; align-items:center; gap:9px; }
        .nav-avatar { width:30px; height:30px; border-radius:50%; background:rgba(201,168,76,0.12); border:1px solid rgba(201,168,76,0.3); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:600; color:#C9A84C; }
        .nav-name { font-size:0.875rem; color:#F0EBE1; }
        .btn-logout { font-size:0.8rem; color:#8A9BB0; text-decoration:none; background:#1A2332; border:1px solid rgba(255,255,255,0.07); border-radius:7px; padding:5px 12px; transition:color 0.15s,border-color 0.15s; }
        .btn-logout:hover { color:#F0EBE1; border-color:#8A9BB0; }

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

        /* ── Global overflow guard ──────────────────────────────── */
        html, body { max-width:100%; overflow-x:hidden; }
        *, *::before, *::after { box-sizing:border-box; }

        /* ── Tablet (≤900px) ────────────────────────────────────── */
        @media (max-width:900px) {
            .container { padding-left:20px; padding-right:20px; }
        }

    /* Fixed Mobile Query */
    @media (max-width: 768px) {
        .topnav { padding: 0 16px; height: 56px; }
        .mobile-nav-drawer { top: 56px; }
        .nav-link, .nav-name, .btn-logout { display: none; }
        .nav-mobile-toggle { display: flex; }
        
        .container { 
            padding: 16px 12px !important; 
            width: 100% !important; 
            max-width: 100% !important; 
            margin: 0 !important;
            overflow-x: hidden !important;
        }

        .header h1 { 
            font-size: 1.6rem !important; 
            white-space: normal !important; 
        }

        /* This fixes the vertical text seen in your screenshot */
        .checkbox-label { 
            display: flex !important; 
            padding: 12px !important; 
            white-space: normal !important; 
        }
        
        .checkbox-label span { 
            flex: 1; 
            min-width: 0; 
            word-break: break-word; 
        }

        /* Stack buttons vertically so they don't push the screen wide */
        .form-actions { 
            flex-direction: column !important; 
            gap: 10px !important; 
        }
        
        .btn { width: 100% !important; text-align: center; }
    }

            /* View Past Results button */
            .btn-secondary { font-size:0.85rem !important; padding:9px 16px !important; }
        }

        /* ── Small mobile (≤480px) ──────────────────────────────── */
        @media (max-width:480px) {
            .container { padding:14px 12px 32px !important; }
            .header h1 { font-size:1.5rem !important; }
            .rating-buttons { gap:4px !important; }
            .rating-buttons label { height:44px !important; font-size:0.95rem !important; }
            .category-card { padding:14px !important; }
            .progress-tracker { padding:12px !important; }
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
            <h1><?= $config['title'] ?> <span class="accent"><?= $config['accent'] ?></span></h1>
            <p class="subtitle"><?= htmlspecialchars($config['subtitle']) ?></p>
            <a href="view_results.php?pillar=<?= $pillarId ?>" class="btn btn-secondary" style="margin-top:0.75rem;display:inline-block;">View Past Results →</a>
        </header>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="progress-tracker">
            <div class="progress-info">
                <h3>Completion Points</h3>
                <div class="score-live" id="livePoints">0 <span>/ <?= $totalCats * 10 ?> pts</span></div>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-label">
                    <span id="completedCount">0 of <?= $totalCats ?> rated</span>
                    <span id="completedPct">0%</span>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="progressFill"></div>
                </div>
            </div>
        </div>

        <form id="assessmentForm" action="combined_assessment.php?pillar=<?= $pillarId ?>" method="POST">
            <?= csrfField() ?>

            <?php foreach ($questions as $cat => $qs):
                $cardNum  = array_search($cat, $categoryKeys) + 1;
                $label    = $config['categories'][$cat] ?? ucwords(str_replace('_', ' ', $cat));
                $idPrefix = substr(str_replace('_', '', $cat), 0, 3) . $cardNum;
            ?>
            <div class="category-card" id="card-<?= $cardNum ?>">
                <div class="category-header">
                    <span class="category-number"><?= $cardNum ?></span>
                    <h2><?= $label ?></h2>
                    <span class="card-points">0 pts</span><span class="category-check">✓</span>
                </div>
                <div class="category-body">
                    <?php if (!empty($qs)): ?>
                    <div class="checkbox-group">
                        <div class="checkbox-group-label">Check all that apply</div>
                        <?php foreach ($qs as $qKey => $qText): ?>
                            <?= renderCheckbox("{$cat}_{$qKey}", $qText) ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?= renderRating($cat, $idPrefix) ?>
                    <div class="notes-group">
                        <div class="notes-label">Reflections</div>
                        <textarea name="<?= $cat ?>_notes" rows="2" placeholder="What's working? What could improve?"></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="category-card action-planning">
                <div class="category-header">
                    <span class="category-number">✍</span>
                    <h2>Action Planning</h2>
                    <span class="category-check"></span>
                </div>
                <div class="category-body">
                    <div class="notes-group">
                        <div class="notes-label">Top Areas to Improve</div>
                        <textarea name="top_improvement_areas" rows="3" placeholder="<?= htmlspecialchars($config['improve_placeholder']) ?>"></textarea>
                    </div>
                    <div class="notes-group">
                        <div class="notes-label">Concrete Next Steps</div>
                        <textarea name="next_steps" rows="4" placeholder="<?= htmlspecialchars($config['nextsteps_placeholder']) ?>"></textarea>
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
        const categories          = <?= $jsCategories ?>;
        const TOTAL_CATEGORIES    = <?= $totalCats ?>;
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
            const pct         = Math.round((rated / TOTAL_CATEGORIES) * 100);
            livePoints.innerHTML       = `${totalPoints} <span>/ ${TOTAL_CATEGORIES * POINTS_PER_CATEGORY} pts</span>`;
            progressFill.style.width   = pct + '%';
            completedCount.textContent = `${rated} of ${TOTAL_CATEGORIES} rated`;
            completedPct.textContent   = pct + '%';
        }

        form.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', updateProgress));
        form.addEventListener('reset', () => {
            setTimeout(() => {
                document.querySelectorAll('.card-points').forEach(b => { b.textContent = '0 pts'; b.classList.remove('earned'); });
                document.querySelectorAll('.category-card').forEach(c => c.classList.remove('is-complete'));
                livePoints.innerHTML       = `0 <span>/ ${TOTAL_CATEGORIES * POINTS_PER_CATEGORY} pts</span>`;
                progressFill.style.width   = '0%';
                completedCount.textContent = `0 of ${TOTAL_CATEGORIES} rated`;
                completedPct.textContent   = '0%';
            }, 0);
        });

        // Prevent double submission
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving…';
            }
        });

        function toggleMobileNav(btn) {
            const drawer = document.getElementById('mobile-nav-drawer');
            const isOpen = drawer.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
            btn.setAttribute('aria-expanded', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                const drawer = document.getElementById('mobile-nav-drawer');
                const toggle = document.getElementById('nav-mobile-toggle');
                drawer.classList.remove('open');
                toggle.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>
