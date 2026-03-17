<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
date_default_timezone_set('Asia/Manila');

$db = getDB();

$counts = [
    'courses'    => (int)$db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'sections'   => (int)$db->query("SELECT COUNT(*) FROM sections")->fetchColumn(),
    'subjects'   => (int)$db->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
    'professors' => (int)$db->query("SELECT COUNT(*) FROM professors")->fetchColumn(),
    'rooms'      => (int)$db->query("SELECT COUNT(*) FROM rooms")->fetchColumn(),
    'schedules'  => (int)$db->query("SELECT COUNT(*) FROM schedules")->fetchColumn(),
];

/* ── Additional rich stats ── */
$fullTime   = (int)$db->query("SELECT COUNT(*) FROM professors WHERE employment_type='Full Time'")->fetchColumn();
$partTime   = (int)$db->query("SELECT COUNT(*) FROM professors WHERE employment_type='Part Time'")->fetchColumn();
$scheduled  = (int)$db->query("SELECT COUNT(DISTINCT professor_id) FROM schedules")->fetchColumn();
$unscheduled = $counts['professors'] - $scheduled;

/* Schedules by day */
$byDay = [];
$dayRows = $db->query("SELECT day_code, COUNT(*) as cnt FROM schedules GROUP BY day_code")->fetchAll();
foreach ($dayRows as $d) $byDay[$d['day_code']] = (int)$d['cnt'];

/* Schedules by semester */
$sem1 = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE semester='1st Semester'")->fetchColumn();
$sem2 = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE semester='2nd Semester'")->fetchColumn();

/* Recent schedules */
$recentSchedules = $db->query("
    SELECT sc.*, sec.name AS section_name, sub.code AS subject_code, sub.name AS subject_name,
           r.name AS room_name, r.room_type, p.name AS prof_name
    FROM schedules sc
    JOIN sections  sec ON sec.id = sc.section_id
    JOIN subjects  sub ON sub.id = sc.subject_id
    JOIN rooms     r   ON r.id   = sc.room_id
    JOIN professors p  ON p.id   = sc.professor_id
    ORDER BY sc.id DESC LIMIT 8
")->fetchAll();

/* Busiest rooms */
$busyRooms = $db->query("
    SELECT r.name, r.room_type, COUNT(*) as cnt
    FROM schedules sc JOIN rooms r ON r.id=sc.room_id
    GROUP BY sc.room_id ORDER BY cnt DESC LIMIT 5
")->fetchAll();

/* Top professors by schedule count */
$topProfs = $db->query("
    SELECT p.name, p.employment_type, COUNT(*) as cnt
    FROM schedules sc JOIN professors p ON p.id=sc.professor_id
    GROUP BY sc.professor_id ORDER BY cnt DESC LIMIT 5
")->fetchAll();

$dayColors = ['M'=>'#3b82f6','T'=>'#10b981','W'=>'#6366f1','Th'=>'#f59e0b','F'=>'#ef4444','Sa'=>'#8b5cf6'];
$dayBgs    = ['M'=>'#eff6ff','T'=>'#f0fdf4','W'=>'#eef2ff','Th'=>'#fffbeb','F'=>'#fff1f2','Sa'=>'#faf5ff'];
$days      = ['M'=>'Mon','T'=>'Tue','W'=>'Wed','Th'=>'Thu','F'=>'Fri','Sa'=>'Sat'];
$maxDay    = !empty($byDay) ? max($byDay) : 1;
?>

<style>
/* ── Dashboard base ──────────────────────────────── */
.db { font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Page header ─────────────────────────────────── */
.db-hero {
    position:relative; overflow:hidden;
    background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 60%,#1d4ed8 100%);
    border-radius:16px; padding:32px 36px; margin-bottom:28px;
    display:flex; align-items:center; justify-content:space-between;
    gap:20px; flex-wrap:wrap;
}
.db-hero::before {
    content:''; position:absolute; inset:0;
    background:radial-gradient(ellipse at 80% 50%, rgba(99,102,241,.35) 0%, transparent 65%);
    pointer-events:none;
}
.db-hero::after {
    content:''; position:absolute; top:-60px; right:-60px;
    width:280px; height:280px; border-radius:50%;
    border:1.5px solid rgba(255,255,255,.07);
    pointer-events:none;
}
.db-hero-text { position:relative; z-index:1; }
.db-hero-eyebrow {
    font-size:.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:1.2px; color:#93c5fd; margin-bottom:6px;
    display:flex; align-items:center; gap:6px;
}
.db-hero-eyebrow::before { content:''; display:inline-block; width:18px; height:2px; background:#3b82f6; border-radius:2px; }
.db-hero h2 {
    font-size:1.75rem; font-weight:900; color:#fff;
    margin:0 0 6px; letter-spacing:-.5px; line-height:1.15;
}
.db-hero p { margin:0; color:#94a3b8; font-size:.9rem; }

.db-hero-meta {
    position:relative; z-index:1;
    display:flex; flex-direction:column; align-items:flex-end; gap:6px;
}
.db-hero-date {
    font-size:.78rem; font-weight:600; color:#cbd5e1;
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
    border-radius:8px; padding:6px 12px;
    display:flex; align-items:center; gap:6px;
}
.db-hero-badge {
    font-size:.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.6px; padding:4px 10px; border-radius:20px;
    background:rgba(34,197,94,.15); color:#4ade80;
    border:1px solid rgba(74,222,128,.2);
    display:flex; align-items:center; gap:5px;
}
.db-hero-badge::before { content:''; width:6px; height:6px; border-radius:50%; background:#4ade80; animation:pulse-green 2s infinite; }
@keyframes pulse-green { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

/* ── Stat cards ──────────────────────────────────── */
.db-stats {
    display:grid; grid-template-columns:repeat(6,1fr);
    gap:12px; margin-bottom:24px;
}
.db-stat {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:14px; padding:16px 18px;
    position:relative; overflow:hidden;
    transition:transform .18s, box-shadow .18s;
    cursor:default;
}
.db-stat:hover { transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,.1); }
.db-stat::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    border-radius:14px 14px 0 0;
}
.db-stat.c-blue::before   { background:linear-gradient(90deg,#3b82f6,#6366f1); }
.db-stat.c-green::before  { background:linear-gradient(90deg,#10b981,#34d399); }
.db-stat.c-amber::before  { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
.db-stat.c-purple::before { background:linear-gradient(90deg,#8b5cf6,#a78bfa); }
.db-stat.c-red::before    { background:linear-gradient(90deg,#ef4444,#f87171); }
.db-stat.c-teal::before   { background:linear-gradient(90deg,#14b8a6,#2dd4bf); }

.db-stat-ico {
    width:38px; height:38px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; margin-bottom:12px;
}
.c-blue   .db-stat-ico { background:#eff6ff; }
.c-green  .db-stat-ico { background:#f0fdf4; }
.c-amber  .db-stat-ico { background:#fffbeb; }
.c-purple .db-stat-ico { background:#faf5ff; }
.c-red    .db-stat-ico { background:#fff1f2; }
.c-teal   .db-stat-ico { background:#f0fdfa; }

.db-stat-val {
    font-size:1.9rem; font-weight:900; line-height:1;
    letter-spacing:-.5px; margin-bottom:4px;
    color:var(--text,#0f172a);
}
.db-stat-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3,#94a3b8); }
.db-stat-sub   { font-size:.75rem; color:var(--text3,#94a3b8); margin-top:3px; }

/* ── Two column layout ───────────────────────────── */
.db-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
.db-grid-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:20px; margin-bottom:20px; }

/* ── Panel base ──────────────────────────────────── */
.db-panel {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:14px; overflow:hidden;
}
.db-panel-hdr {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; border-bottom:1.5px solid var(--border,#e2e8f0);
}
.db-panel-title {
    display:flex; align-items:center; gap:9px;
    font-size:.9rem; font-weight:800; color:var(--text,#0f172a);
}
.db-panel-title-icon {
    width:30px; height:30px; border-radius:8px;
    display:flex; align-items:center; justify-content:center; font-size:.95rem;
}
.db-panel-link {
    font-size:.78rem; font-weight:700; color:#3b82f6;
    text-decoration:none; padding:5px 11px; border-radius:7px;
    background:#eff6ff; border:1px solid #bfdbfe;
    transition:background .15s;
}
.db-panel-link:hover { background:#dbeafe; }

/* ── Day schedule chart ──────────────────────────── */
.day-chart { padding:20px; display:flex; flex-direction:column; gap:10px; }
.day-bar-row { display:flex; align-items:center; gap:10px; }
.day-bar-label {
    width:32px; font-size:.75rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.4px;
    color:var(--text2,#475569); flex-shrink:0; text-align:right;
}
.day-bar-track {
    flex:1; height:28px; background:var(--hover,#f1f5f9);
    border-radius:8px; overflow:hidden; position:relative;
}
.day-bar-fill {
    height:100%; border-radius:8px;
    display:flex; align-items:center; padding-left:10px;
    font-size:.72rem; font-weight:800; color:#fff;
    min-width:28px; transition:width .6s cubic-bezier(.16,1,.3,1);
    position:relative;
}
.day-bar-count {
    width:36px; text-align:right;
    font-size:.82rem; font-weight:800;
    color:var(--text,#0f172a); flex-shrink:0;
}

/* ── Semester donut ──────────────────────────────── */
.sem-panel { padding:20px; }
.sem-donut-wrap { display:flex; align-items:center; justify-content:center; margin-bottom:18px; }
.sem-donut { position:relative; width:120px; height:120px; }
.sem-donut svg { transform:rotate(-90deg); }
.sem-donut-label {
    position:absolute; inset:0; display:flex;
    flex-direction:column; align-items:center; justify-content:center;
}
.sem-donut-total { font-size:1.4rem; font-weight:900; color:var(--text,#0f172a); line-height:1; }
.sem-donut-sub   { font-size:.62rem; font-weight:700; color:var(--text3,#94a3b8); text-transform:uppercase; letter-spacing:.4px; }
.sem-legend { display:flex; flex-direction:column; gap:8px; }
.sem-legend-item { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.sem-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
.sem-legend-name { font-size:.8rem; font-weight:600; color:var(--text2,#475569); flex:1; }
.sem-legend-val  { font-size:.82rem; font-weight:800; color:var(--text,#0f172a); }
.sem-legend-pct  { font-size:.72rem; color:var(--text3,#94a3b8); }

/* ── Professor stats ─────────────────────────────── */
.prof-split { padding:20px; display:flex; flex-direction:column; gap:14px; }
.prof-type-row { display:flex; align-items:center; gap:12px; }
.prof-type-ico { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.prof-type-text { flex:1; }
.prof-type-label { font-size:.75rem; font-weight:700; color:var(--text2,#475569); margin-bottom:3px; }
.prof-type-track { height:8px; background:var(--hover,#f1f5f9); border-radius:10px; overflow:hidden; }
.prof-type-fill  { height:100%; border-radius:10px; transition:width .6s cubic-bezier(.16,1,.3,1); }
.prof-type-count { font-size:.9rem; font-weight:900; color:var(--text,#0f172a); flex-shrink:0; }
.prof-divider    { height:1px; background:var(--border,#e2e8f0); }
.sched-ratio { display:flex; align-items:center; gap:10px; }
.sched-ratio-ico { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; }
.sched-ratio-label { font-size:.78rem; font-weight:600; color:var(--text2,#475569); }
.sched-ratio-val   { font-size:.95rem; font-weight:900; color:var(--text,#0f172a); margin-left:auto; }

/* ── Top rooms & professors ──────────────────────── */
.rank-list { padding:0; }
.rank-item {
    display:flex; align-items:center; gap:12px;
    padding:12px 20px; border-bottom:1px solid var(--border,#f1f5f9);
    transition:background .1s;
}
.rank-item:last-child { border-bottom:none; }
.rank-item:hover { background:var(--hover,#f8fafc); }
.rank-num {
    width:22px; height:22px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:.68rem; font-weight:900; flex-shrink:0;
    background:var(--hover,#f1f5f9); color:var(--text3,#94a3b8);
}
.rank-num.top1 { background:#fef9c3; color:#854d0e; }
.rank-num.top2 { background:#f1f5f9; color:#475569; }
.rank-num.top3 { background:#fff7ed; color:#c2410c; }
.rank-info { flex:1; min-width:0; }
.rank-name { font-size:.875rem; font-weight:700; color:var(--text,#0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rank-sub  { font-size:.72rem; color:var(--text3,#94a3b8); margin-top:1px; }
.rank-bar-wrap { width:80px; }
.rank-bar-track { height:6px; background:var(--hover,#f1f5f9); border-radius:10px; overflow:hidden; margin-bottom:2px; }
.rank-bar-fill  { height:100%; border-radius:10px; }
.rank-count { font-size:.72rem; font-weight:700; color:var(--text3,#94a3b8); text-align:right; }

/* ── Recent schedules table ──────────────────────── */
.db-table { width:100%; border-collapse:collapse; }
.db-table thead tr { background:var(--hover,#f8fafc); }
.db-table th {
    padding:10px 16px; font-size:.67rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.7px;
    color:var(--text3,#94a3b8); text-align:left;
    border-bottom:1.5px solid var(--border,#e2e8f0); white-space:nowrap;
}
.db-table td {
    padding:12px 16px; font-size:.855rem;
    border-bottom:1px solid var(--border,#f1f5f9);
    color:var(--text,#334155); vertical-align:middle;
}
.db-table tbody tr { transition:background .1s; }
.db-table tbody tr:hover { background:var(--hover,#f8fafc); }
.db-table tbody tr:last-child td { border-bottom:none; }

/* Cell pieces */
.cell-section { font-weight:800; color:var(--text,#0f172a); font-size:.875rem; }
.sub-chip {
    display:inline-block; background:#eff6ff; color:#1d4ed8;
    border:1px solid #bfdbfe; border-radius:5px;
    font-size:.7rem; font-weight:800; padding:2px 7px; letter-spacing:.3px;
    vertical-align:middle;
}
.sub-name { font-size:.8rem; color:var(--text2,#475569); margin-top:1px; }
.sem-pill {
    display:inline-flex; align-items:center;
    font-size:.68rem; font-weight:800; padding:3px 9px; border-radius:20px;
}
.sem-pill.s1 { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
.sem-pill.s2 { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
.day-pill {
    display:inline-flex; align-items:center; gap:5px;
    font-weight:800; font-size:.875rem;
}
.day-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.time-mono {
    font-family:'DM Mono','Courier New',monospace;
    font-size:.77rem; font-weight:600;
    background:var(--hover,#f1f5f9); color:var(--text2,#475569);
    padding:3px 9px; border-radius:6px; white-space:nowrap;
    border:1px solid var(--border,#e2e8f0);
}
.room-tag { font-size:.82rem; color:var(--text,#334155); }
.room-type-badge {
    font-size:.63rem; font-weight:700; text-transform:uppercase;
    letter-spacing:.4px; background:var(--hover,#f1f5f9);
    color:var(--text3,#94a3b8); padding:1px 6px; border-radius:4px; margin-left:4px;
}
.prof-name { font-size:.875rem; color:var(--text,#334155); }

/* ── Empty state ─────────────────────────────────── */
.db-empty { text-align:center; padding:48px 20px; color:var(--text3,#94a3b8); }
.db-empty .ei { font-size:2.5rem; display:block; margin-bottom:10px; }
.db-empty p { margin:0; font-size:.9rem; }

/* ── Responsive ──────────────────────────────────── */
@media(max-width:1200px){ .db-stats{grid-template-columns:repeat(3,1fr)} }
@media(max-width:960px) { .db-grid-3{grid-template-columns:1fr 1fr}; .db-grid-2{grid-template-columns:1fr} }
@media(max-width:700px) {
    .db-stats{grid-template-columns:repeat(2,1fr)}
    .db-grid-2,.db-grid-3{grid-template-columns:1fr}
    .db-hero{padding:22px 20px}
    .db-hero h2{font-size:1.3rem}
}
</style>

<div class="db">

  <!-- ═══════════ HERO HEADER ═══════════ -->
  <div class="db-hero">
    <div class="db-hero-text">
      <div class="db-hero-eyebrow">College Scheduling System</div>
      <h2>Good <?php
        $hr = (int)date('H');
        echo $hr < 12 ? 'Morning' : ($hr < 17 ? 'Afternoon' : 'Evening');
      ?> 👋</h2>
      <p>Here's what's happening across your scheduling system today.</p>
    </div>
    <div class="db-hero-meta">
      <div class="db-hero-date">
        📅 <?= date('l, F j, Y') ?>
      </div>
      <div class="db-hero-badge">System Active</div>
    </div>
  </div>

  <!-- ═══════════ STAT CARDS ═══════════ -->
  <div class="db-stats">
    <?php
    $statDefs = [
        ['icon'=>'🎓','label'=>'Courses',    'value'=>$counts['courses'],    'sub'=>'Academic programs', 'color'=>'c-blue'],
        ['icon'=>'🏫','label'=>'Sections',   'value'=>$counts['sections'],   'sub'=>'Class groups',      'color'=>'c-green'],
        ['icon'=>'📚','label'=>'Subjects',   'value'=>$counts['subjects'],   'sub'=>'Across all courses','color'=>'c-amber'],
        ['icon'=>'👨‍🏫','label'=>'Professors', 'value'=>$counts['professors'], 'sub'=>'Faculty members',   'color'=>'c-purple'],
        ['icon'=>'🚪','label'=>'Rooms',      'value'=>$counts['rooms'],      'sub'=>'Classrooms & labs', 'color'=>'c-red'],
        ['icon'=>'📅','label'=>'Schedules',  'value'=>$counts['schedules'],  'sub'=>'Class sessions',    'color'=>'c-teal'],
    ];
    foreach ($statDefs as $s): ?>
    <div class="db-stat <?= $s['color'] ?>">
      <div class="db-stat-ico"><?= $s['icon'] ?></div>
      <div class="db-stat-val"><?= number_format($s['value']) ?></div>
      <div class="db-stat-label"><?= $s['label'] ?></div>
      <div class="db-stat-sub"><?= $s['sub'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════ ROW 2: Day chart + Semester donut + Professor split ═══════════ -->
  <div class="db-grid-3">

    <!-- Schedules by Day -->
    <div class="db-panel">
      <div class="db-panel-hdr">
        <div class="db-panel-title">
          <div class="db-panel-title-icon" style="background:#eff6ff">📊</div>
          Schedules by Day
        </div>
      </div>
      <div class="day-chart">
        <?php foreach ($days as $code => $label):
          $cnt   = $byDay[$code] ?? 0;
          $pct   = $maxDay > 0 ? round(($cnt/$maxDay)*100) : 0;
          $color = $dayColors[$code] ?? '#64748b';
          $bg    = $dayBgs[$code]    ?? '#f8fafc';
        ?>
        <div class="day-bar-row">
          <span class="day-bar-label" style="color:<?= $color ?>"><?= $label ?></span>
          <div class="day-bar-track">
            <div class="day-bar-fill" style="width:<?= max($pct,0) ?>%;background:<?= $color ?>">
              <?php if ($cnt > 0): ?><?= $cnt ?><?php endif; ?>
            </div>
          </div>
          <span class="day-bar-count"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Semester breakdown -->
    <div class="db-panel">
      <div class="db-panel-hdr">
        <div class="db-panel-title">
          <div class="db-panel-title-icon" style="background:#fffbeb">🗓️</div>
          By Semester
        </div>
      </div>
      <div class="sem-panel">
        <?php
        $total = $sem1 + $sem2;
        $r1    = 52; $cx = 60; $cy = 60;
        $circ  = 2 * M_PI * $r1;
        $dash1 = $total > 0 ? ($sem1/$total)*$circ : 0;
        $dash2 = $total > 0 ? ($sem2/$total)*$circ : $circ;
        ?>
        <div class="sem-donut-wrap">
          <div class="sem-donut">
            <svg width="120" height="120" viewBox="0 0 120 120">
              <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r1 ?>" fill="none" stroke="#f1f5f9" stroke-width="14"/>
              <?php if ($sem2 > 0): ?>
              <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r1 ?>" fill="none"
                stroke="#f59e0b" stroke-width="14"
                stroke-dasharray="<?= round($dash2,2) ?> <?= round($circ,2) ?>"
                stroke-dashoffset="0" stroke-linecap="round"/>
              <?php endif; ?>
              <?php if ($sem1 > 0): ?>
              <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r1 ?>" fill="none"
                stroke="#3b82f6" stroke-width="14"
                stroke-dasharray="<?= round($dash1,2) ?> <?= round($circ,2) ?>"
                stroke-dashoffset="<?= round(-$dash2,2) ?>" stroke-linecap="round"/>
              <?php endif; ?>
            </svg>
            <div class="sem-donut-label">
              <span class="sem-donut-total"><?= $total ?></span>
              <span class="sem-donut-sub">Total</span>
            </div>
          </div>
        </div>
        <div class="sem-legend">
          <div class="sem-legend-item">
            <span class="sem-legend-dot" style="background:#3b82f6"></span>
            <span class="sem-legend-name">1st Semester</span>
            <span class="sem-legend-val"><?= $sem1 ?></span>
            <span class="sem-legend-pct"><?= $total > 0 ? round($sem1/$total*100) : 0 ?>%</span>
          </div>
          <div class="sem-legend-item">
            <span class="sem-legend-dot" style="background:#f59e0b"></span>
            <span class="sem-legend-name">2nd Semester</span>
            <span class="sem-legend-val"><?= $sem2 ?></span>
            <span class="sem-legend-pct"><?= $total > 0 ? round($sem2/$total*100) : 0 ?>%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Professor snapshot -->
    <div class="db-panel">
      <div class="db-panel-hdr">
        <div class="db-panel-title">
          <div class="db-panel-title-icon" style="background:#faf5ff">👨‍🏫</div>
          Professors
        </div>
      </div>
      <div class="prof-split">
        <?php $total_p = $counts['professors'] ?: 1; ?>
        <div class="prof-type-row">
          <div class="prof-type-ico" style="background:#f0fdf4">🟢</div>
          <div class="prof-type-text">
            <div class="prof-type-label">Full Time</div>
            <div class="prof-type-track">
              <div class="prof-type-fill" style="width:<?= round($fullTime/$total_p*100) ?>%;background:#10b981"></div>
            </div>
          </div>
          <span class="prof-type-count"><?= $fullTime ?></span>
        </div>
        <div class="prof-type-row">
          <div class="prof-type-ico" style="background:#fff7ed">🟠</div>
          <div class="prof-type-text">
            <div class="prof-type-label">Part Time</div>
            <div class="prof-type-track">
              <div class="prof-type-fill" style="width:<?= round($partTime/$total_p*100) ?>%;background:#f59e0b"></div>
            </div>
          </div>
          <span class="prof-type-count"><?= $partTime ?></span>
        </div>
        <div class="prof-divider"></div>
        <div class="sched-ratio">
          <div class="sched-ratio-ico" style="background:#eff6ff">📅</div>
          <span class="sched-ratio-label">With Schedules</span>
          <span class="sched-ratio-val"><?= $scheduled ?></span>
        </div>
        <div class="sched-ratio">
          <div class="sched-ratio-ico" style="background:#f8fafc">⭕</div>
          <span class="sched-ratio-label">Unassigned</span>
          <span class="sched-ratio-val" style="color:<?= $unscheduled>0?'#ef4444':'#10b981' ?>"><?= $unscheduled ?></span>
        </div>
      </div>
    </div>

  </div>

  <!-- ═══════════ ROW 3: Top rooms + Top professors ═══════════ -->
  <div class="db-grid-2" style="margin-bottom:20px">

    <!-- Busiest Rooms -->
    <div class="db-panel">
      <div class="db-panel-hdr">
        <div class="db-panel-title">
          <div class="db-panel-title-icon" style="background:#fff1f2">🚪</div>
          Busiest Rooms
        </div>
      </div>
      <?php if (empty($busyRooms)): ?>
        <div class="db-empty"><span class="ei">🚪</span><p>No room data yet.</p></div>
      <?php else:
        $maxR = max(array_column($busyRooms,'cnt')) ?: 1;
      ?>
      <div class="rank-list">
        <?php foreach ($busyRooms as $i => $rm): ?>
        <div class="rank-item">
          <span class="rank-num <?= ['top1','top2','top3'][$i] ?? '' ?>"><?= $i+1 ?></span>
          <div class="rank-info">
            <div class="rank-name"><?= h($rm['name']) ?></div>
            <div class="rank-sub"><?= h($rm['room_type'] ?? '') ?></div>
          </div>
          <div class="rank-bar-wrap">
            <div class="rank-bar-track">
              <div class="rank-bar-fill" style="width:<?= round($rm['cnt']/$maxR*100) ?>%;background:#ef4444"></div>
            </div>
            <div class="rank-count"><?= $rm['cnt'] ?> sessions</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Top Professors -->
    <div class="db-panel">
      <div class="db-panel-hdr">
        <div class="db-panel-title">
          <div class="db-panel-title-icon" style="background:#faf5ff">🏆</div>
          Most Scheduled Professors
        </div>
      </div>
      <?php if (empty($topProfs)): ?>
        <div class="db-empty"><span class="ei">👨‍🏫</span><p>No schedule data yet.</p></div>
      <?php else:
        $maxP = max(array_column($topProfs,'cnt')) ?: 1;
      ?>
      <div class="rank-list">
        <?php foreach ($topProfs as $i => $prof):
          $isFT = ($prof['employment_type'] ?? 'Full Time') === 'Full Time';
        ?>
        <div class="rank-item">
          <span class="rank-num <?= ['top1','top2','top3'][$i] ?? '' ?>"><?= $i+1 ?></span>
          <div class="rank-info">
            <div class="rank-name"><?= h($prof['name']) ?></div>
            <div class="rank-sub" style="color:<?= $isFT?'#10b981':'#f59e0b' ?>">
              <?= $isFT ? '🟢 Full Time' : '🟠 Part Time' ?>
            </div>
          </div>
          <div class="rank-bar-wrap">
            <div class="rank-bar-track">
              <div class="rank-bar-fill" style="width:<?= round($prof['cnt']/$maxP*100) ?>%;background:#8b5cf6"></div>
            </div>
            <div class="rank-count"><?= $prof['cnt'] ?> sessions</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ═══════════ RECENT SCHEDULES ═══════════ -->
  <div class="db-panel">
    <div class="db-panel-hdr">
      <div class="db-panel-title">
        <div class="db-panel-title-icon" style="background:#f0fdf4">🕐</div>
        Recent Schedules
      </div>
      <a href="schedules.php" class="db-panel-link">View All →</a>
    </div>

    <?php if (empty($recentSchedules)): ?>
      <div class="db-empty">
        <span class="ei">📅</span>
        <p>No schedules yet. <a href="schedules.php" style="color:#3b82f6;font-weight:700;text-decoration:none">Add one now →</a></p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="db-table">
          <thead>
            <tr>
              <th>Section</th>
              <th>Subject</th>
              <th>Semester</th>
              <th>Day</th>
              <th>Time</th>
              <th>Room</th>
              <th>Instructor</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recentSchedules as $r):
            $dc  = $r['day_code'];
            $clr = $dayColors[$dc] ?? '#64748b';
          ?>
          <tr>
            <td><span class="cell-section"><?= h($r['section_name']) ?></span></td>
            <td>
              <span class="sub-chip"><?= h($r['subject_code']) ?></span>
              <div class="sub-name"><?= h($r['subject_name']) ?></div>
            </td>
            <td>
              <span class="sem-pill <?= $r['semester']==='1st Semester'?'s1':'s2' ?>">
                <?= $r['semester']==='1st Semester'?'1st Sem':'2nd Sem' ?>
              </span>
            </td>
            <td>
              <div class="day-pill">
                <span class="day-dot" style="background:<?= $clr ?>"></span>
                <span style="color:<?= $clr ?>"><?= dayFull($dc) ?></span>
              </div>
            </td>
            <td><span class="time-mono"><?= formatTimeRange($r['start_time'],$r['end_time']) ?></span></td>
            <td>
              <span class="room-tag">
                <?= h($r['room_name']) ?>
                <?php if ($r['room_type']): ?>
                  <span class="room-type-badge"><?= h($r['room_type']) ?></span>
                <?php endif; ?>
              </span>
            </td>
            <td><span class="prof-name"><?= h($r['prof_name']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>