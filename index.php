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

$fullTime   = (int)$db->query("SELECT COUNT(*) FROM professors WHERE employment_type='Full Time'")->fetchColumn();
$partTime   = (int)$db->query("SELECT COUNT(*) FROM professors WHERE employment_type='Part Time'")->fetchColumn();
$scheduled  = (int)$db->query("SELECT COUNT(DISTINCT professor_id) FROM schedules")->fetchColumn();
$unscheduled = $counts['professors'] - $scheduled;

$byDay = [];
$dayRows = $db->query("SELECT day_code, COUNT(*) as cnt FROM schedules GROUP BY day_code")->fetchAll();
foreach ($dayRows as $d) $byDay[$d['day_code']] = (int)$d['cnt'];

$sem1 = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE semester='1st Semester'")->fetchColumn();
$sem2 = (int)$db->query("SELECT COUNT(*) FROM schedules WHERE semester='2nd Semester'")->fetchColumn();

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

$busyRooms = $db->query("
    SELECT r.name, r.room_type, COUNT(*) as cnt
    FROM schedules sc JOIN rooms r ON r.id=sc.room_id
    GROUP BY sc.room_id ORDER BY cnt DESC LIMIT 5
")->fetchAll();

$topProfs = $db->query("
    SELECT p.name, p.employment_type, COUNT(*) as cnt
    FROM schedules sc JOIN professors p ON p.id=sc.professor_id
    GROUP BY sc.professor_id ORDER BY cnt DESC LIMIT 5
")->fetchAll();

// ── TODAY'S SCHEDULE ─────────────────────────────────────────────────────────
// Map PHP day-of-week (0=Sun…6=Sat) to your day_code values
$phpDow   = (int)date('w'); // 0=Sun,1=Mon,...,6=Sat
$dowMap   = [0=>null, 1=>'M', 2=>'T', 3=>'W', 4=>'Th', 5=>'F', 6=>'Sa'];
$todayCode = $dowMap[$phpDow] ?? null;

$todaySchedules = [];
if ($todayCode) {
    $stmt = $db->prepare("
        SELECT sc.*, sec.name AS section_name,
               sub.code AS subject_code, sub.name AS subject_name,
               r.name AS room_name, r.room_type,
               p.name AS prof_name
        FROM schedules sc
        JOIN sections  sec ON sec.id = sc.section_id
        JOIN subjects  sub ON sub.id = sc.subject_id
        JOIN rooms     r   ON r.id   = sc.room_id
        JOIN professors p  ON p.id   = sc.professor_id
        WHERE sc.day_code = ?
        ORDER BY sc.start_time ASC
    ");
    $stmt->execute([$todayCode]);
    $todaySchedules = $stmt->fetchAll();
}

// Current Manila time as seconds-since-midnight for comparison
$nowSec = (int)date('H') * 3600 + (int)date('i') * 60 + (int)date('s');

function timeToSec(string $t): int {
    [$h, $m] = explode(':', $t);
    return (int)$h * 3600 + (int)$m * 60;
}

function classStatus(string $start, string $end, int $nowSec): string {
    $s = timeToSec($start);
    $e = timeToSec($end);
    if ($nowSec >= $s && $nowSec < $e) return 'ongoing';
    if ($nowSec < $s)                  return 'upcoming';
    return 'done';
}

// Count rooms occupied right now (for the header badge)
$roomsInUse = 0;
$roomsDoneToday = 0;
$roomsUpcoming  = 0;
foreach ($todaySchedules as $r) {
    $st = classStatus($r['start_time'], $r['end_time'], $nowSec);
    if ($st === 'ongoing')  $roomsInUse++;
    if ($st === 'done')     $roomsDoneToday++;
    if ($st === 'upcoming') $roomsUpcoming++;
}

$dayColors = ['M'=>'#3b82f6','T'=>'#10b981','W'=>'#6366f1','Th'=>'#f59e0b','F'=>'#ef4444','Sa'=>'#8b5cf6'];
$dayBgs    = ['M'=>'#eff6ff','T'=>'#f0fdf4','W'=>'#eef2ff','Th'=>'#fffbeb','F'=>'#fff1f2','Sa'=>'#faf5ff'];
$days      = ['M'=>'Mon','T'=>'Tue','W'=>'Wed','Th'=>'Thu','F'=>'Fri','Sa'=>'Sat'];
$maxDay    = !empty($byDay) ? max($byDay) : 1;
?>

<style>
/* ── Dashboard base ──────────────────────────────── */
.db { font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Live indicator ──────────────────────────────── */
.db-live-badge {
    display:inline-flex; align-items:center; gap:6px;
    font-size:.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.6px; padding:4px 10px; border-radius:20px;
    background:rgba(34,197,94,.15); color:#4ade80;
    border:1px solid rgba(74,222,128,.2);
    transition: background .3s;
}
.db-live-badge.flash { background:rgba(34,197,94,.35); }
.db-live-dot {
    width:6px; height:6px; border-radius:50%; background:#4ade80;
    animation:pulse-green 2s infinite;
}
@keyframes pulse-green { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

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
    transition: transform .2s;
}
.db-stat-val.bump { transform: scale(1.18); }
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

/* ══════════════════════════════════════════════════
   TODAY'S SCHEDULE PANEL
══════════════════════════════════════════════════ */
.today-panel {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:14px; overflow:hidden;
    margin-bottom:24px;
    position:relative;
}
.today-panel::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:linear-gradient(90deg,#3b82f6,#6366f1,#8b5cf6);
}
.today-panel-hdr {
    display:flex; align-items:center; justify-content:space-between;
    padding:16px 20px; border-bottom:1.5px solid var(--border,#e2e8f0);
    flex-wrap:wrap; gap:10px;
}
.today-panel-title {
    display:flex; align-items:center; gap:10px;
    font-size:.95rem; font-weight:800; color:var(--text,#0f172a);
}
.today-day-badge {
    padding:4px 12px; border-radius:20px;
    font-size:.72rem; font-weight:900; text-transform:uppercase; letter-spacing:.6px;
}
.today-room-stats {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap;
}
.today-room-chip {
    display:inline-flex; align-items:center; gap:5px;
    font-size:.75rem; font-weight:700;
    padding:4px 10px; border-radius:20px;
}
.chip-ongoing  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.chip-upcoming { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
.chip-done     { background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; }
.chip-dot      { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.chip-ongoing  .chip-dot { background:#16a34a; animation:pulse-green 2s infinite; }
.chip-upcoming .chip-dot { background:#ca8a04; }
.chip-done     .chip-dot { background:#94a3b8; }
.today-no-school {
    text-align:center; padding:48px 20px; color:var(--text3,#94a3b8);
}
.today-no-school .ei { font-size:2.5rem; display:block; margin-bottom:10px; }

/* Today's table */
.today-table { width:100%; border-collapse:collapse; }
.today-table thead tr { background:var(--hover,#f8fafc); }
.today-table th {
    padding:9px 14px; font-size:.67rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.7px;
    color:var(--text3,#94a3b8); text-align:left;
    border-bottom:1.5px solid var(--border,#e2e8f0); white-space:nowrap;
}
.today-table td {
    padding:11px 14px; font-size:.85rem;
    border-bottom:1px solid var(--border,#f1f5f9);
    color:var(--text,#334155); vertical-align:middle;
}
.today-table tbody tr { transition:background .1s; }
.today-table tbody tr:hover { background:var(--hover,#f8fafc); }
.today-table tbody tr:last-child td { border-bottom:none; }
.today-table tbody tr.row-ongoing { background:#f0fdf4; }
.today-table tbody tr.row-done    { opacity:.55; }

/* Status badge */
.status-badge {
    display:inline-flex; align-items:center; gap:5px;
    font-size:.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.4px; padding:3px 9px; border-radius:20px; white-space:nowrap;
}
.st-ongoing  { background:#dcfce7; color:#166534; border:1px solid #86efac; }
.st-upcoming { background:#fef9c3; color:#854d0e; border:1px solid #fde047; }
.st-done     { background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; }
.st-ongoing .chip-dot  { width:6px; height:6px; border-radius:50%; background:#16a34a; animation:pulse-green 2s infinite; flex-shrink:0; }
.st-upcoming .chip-dot { width:6px; height:6px; border-radius:50%; background:#ca8a04; flex-shrink:0; }
.st-done .chip-dot     { width:6px; height:6px; border-radius:50%; background:#94a3b8; flex-shrink:0; }

/* Room occupancy pill */
.room-occ {
    display:inline-flex; align-items:center; gap:5px;
    font-size:.8rem; font-weight:700;
}
.room-occ-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.room-occ.occupied .room-occ-dot { background:#16a34a; animation:pulse-green 2s infinite; }
.room-occ.free     .room-occ-dot { background:#94a3b8; }

/* Progress bar for time remaining */
.time-progress { margin-top:3px; height:3px; border-radius:3px; background:#e2e8f0; overflow:hidden; width:80px; }
.time-progress-fill { height:100%; border-radius:3px; background:#10b981; transition:width 1s linear; }

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
    min-width:0; transition:width .6s cubic-bezier(.16,1,.3,1);
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
.day-pill { display:inline-flex; align-items:center; gap:5px; font-weight:800; font-size:.875rem; }
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
.db-empty { text-align:center; padding:48px 20px; color:var(--text3,#94a3b8); }
.db-empty .ei { font-size:2.5rem; display:block; margin-bottom:10px; }
.db-empty p { margin:0; font-size:.9rem; }

/* ── New row flash animation ─────────────────────── */
@keyframes rowFlash {
    0%   { background: #dbeafe; }
    100% { background: transparent; }
}
.row-new { animation: rowFlash 1.8s ease-out forwards; }

/* ── Responsive ──────────────────────────────────── */
@media(max-width:1200px){ .db-stats{grid-template-columns:repeat(3,1fr)} }
@media(max-width:960px) { .db-grid-3{grid-template-columns:1fr 1fr}; .db-grid-2{grid-template-columns:1fr} }
@media(max-width:700px) {
    .db-stats{grid-template-columns:repeat(2,1fr)}
    .db-grid-2,.db-grid-3{grid-template-columns:1fr}
    .db-hero{padding:22px 20px}
    .db-hero h2{font-size:1.3rem}
    .today-table th:nth-child(4),
    .today-table td:nth-child(4) { display:none; }
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
      <div class="db-live-badge" id="live-badge">
        <span class="db-live-dot"></span>
        <span id="live-label">Live</span>
      </div>
    </div>
  </div>

  <!-- ═══════════ STAT CARDS ═══════════ -->
  <div class="db-stats">
    <?php
    $statDefs = [
        ['icon'=>'🎓','label'=>'Courses',    'key'=>'courses',    'sub'=>'Academic programs', 'color'=>'c-blue'],
        ['icon'=>'🏫','label'=>'Sections',   'key'=>'sections',   'sub'=>'Class groups',      'color'=>'c-green'],
        ['icon'=>'📚','label'=>'Subjects',   'key'=>'subjects',   'sub'=>'Across all courses','color'=>'c-amber'],
        ['icon'=>'👨‍🏫','label'=>'Professors', 'key'=>'professors', 'sub'=>'Faculty members',   'color'=>'c-purple'],
        ['icon'=>'🚪','label'=>'Rooms',      'key'=>'rooms',      'sub'=>'Classrooms & labs', 'color'=>'c-red'],
        ['icon'=>'📅','label'=>'Schedules',  'key'=>'schedules',  'sub'=>'Class sessions',    'color'=>'c-teal'],
    ];
    foreach ($statDefs as $s): ?>
    <div class="db-stat <?= $s['color'] ?>">
      <div class="db-stat-ico"><?= $s['icon'] ?></div>
      <div class="db-stat-val" id="stat-<?= $s['key'] ?>"><?= number_format($counts[$s['key']]) ?></div>
      <div class="db-stat-label"><?= $s['label'] ?></div>
      <div class="db-stat-sub"><?= $s['sub'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════ TODAY'S SCHEDULE PANEL ═══════════ -->
  <div class="today-panel" id="today-panel">
    <div class="today-panel-hdr">
      <div class="today-panel-title">
        <div class="db-panel-title-icon" style="background:#eff6ff">📋</div>
        Today's Classes
        <?php if ($todayCode):
          $tc = $dayColors[$todayCode] ?? '#64748b';
          $tb = $dayBgs[$todayCode]    ?? '#f1f5f9'; ?>
          <span class="today-day-badge" style="background:<?= $tb ?>;color:<?= $tc ?>;border:1px solid <?= $tc ?>30">
            <?= $days[$todayCode] ?? $todayCode ?> — <?= date('M j') ?>
          </span>
        <?php else: ?>
          <span class="today-day-badge" style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0">
            No School Day
          </span>
        <?php endif; ?>
      </div>
      <div class="today-room-stats" id="today-room-stats">
        <?php if ($todayCode && count($todaySchedules)): ?>
          <span class="today-room-chip chip-ongoing" id="chip-ongoing">
            <span class="chip-dot"></span>
            <span id="chip-ongoing-cnt"><?= $roomsInUse ?></span> Ongoing
          </span>
          <span class="today-room-chip chip-upcoming" id="chip-upcoming">
            <span class="chip-dot"></span>
            <span id="chip-upcoming-cnt"><?= $roomsUpcoming ?></span> Upcoming
          </span>
          <span class="today-room-chip chip-done" id="chip-done">
            <span class="chip-dot"></span>
            <span id="chip-done-cnt"><?= $roomsDoneToday ?></span> Done
          </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$todayCode): ?>
      <div class="today-no-school">
        <span class="ei">🌴</span>
        <p>Today is Sunday — no classes scheduled.</p>
      </div>

    <?php elseif (empty($todaySchedules)): ?>
      <div class="today-no-school" id="today-empty">
        <span class="ei">📭</span>
        <p>No classes scheduled for <?= $days[$todayCode] ?>.</p>
      </div>

    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="today-table" id="today-table">
          <thead>
            <tr>
              <th>Status</th>
              <th>Section</th>
              <th>Subject</th>
              <th>Time</th>
              <th>Room</th>
              <th>Instructor</th>
              <th>Semester</th>
            </tr>
          </thead>
          <tbody id="today-tbody">
          <?php foreach ($todaySchedules as $r):
            $st   = classStatus($r['start_time'], $r['end_time'], $nowSec);
            $rowCls = match($st) { 'ongoing'=>'row-ongoing', 'done'=>'row-done', default=>'' };
            // Progress % for ongoing classes
            $prog = 0;
            if ($st === 'ongoing') {
                $dur  = timeToSec($r['end_time']) - timeToSec($r['start_time']);
                $elapsed = $nowSec - timeToSec($r['start_time']);
                $prog = $dur > 0 ? min(100, round($elapsed/$dur*100)) : 0;
            }
          ?>
          <tr class="<?= $rowCls ?>" data-start="<?= h($r['start_time']) ?>" data-end="<?= h($r['end_time']) ?>">
            <td>
              <?php if ($st === 'ongoing'): ?>
                <span class="status-badge st-ongoing">
                  <span class="chip-dot"></span> Ongoing
                </span>
                <div class="time-progress">
                  <div class="time-progress-fill" style="width:<?= $prog ?>%"></div>
                </div>
              <?php elseif ($st === 'upcoming'): ?>
                <span class="status-badge st-upcoming">
                  <span class="chip-dot"></span> Upcoming
                </span>
              <?php else: ?>
                <span class="status-badge st-done">
                  <span class="chip-dot"></span> Done
                </span>
              <?php endif; ?>
            </td>
            <td><span class="cell-section"><?= h($r['section_name']) ?></span></td>
            <td>
              <span class="sub-chip"><?= h($r['subject_code']) ?></span>
              <div class="sub-name"><?= h($r['subject_name']) ?></div>
            </td>
            <td>
              <span class="time-mono"><?= formatTimeRange($r['start_time'], $r['end_time']) ?></span>
            </td>
            <td>
              <div class="room-occ <?= $st === 'ongoing' ? 'occupied' : 'free' ?>">
                <span class="room-occ-dot"></span>
                <?= h($r['room_name']) ?>
              </div>
              <?php if ($r['room_type']): ?>
                <div style="font-size:.7rem;color:var(--text3,#94a3b8);margin-top:2px"><?= h($r['room_type']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="prof-name"><?= h($r['prof_name']) ?></span></td>
            <td>
              <span class="sem-pill <?= $r['semester']==='1st Semester'?'s1':'s2' ?>">
                <?= $r['semester']==='1st Semester'?'1st Sem':'2nd Sem' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
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
      <div class="day-chart" id="day-chart">
        <?php foreach ($days as $code => $label):
          $cnt   = $byDay[$code] ?? 0;
          $pct   = $maxDay > 0 ? round(($cnt/$maxDay)*100) : 0;
          $color = $dayColors[$code] ?? '#64748b';
          $isToday = ($code === $todayCode);
        ?>
        <div class="day-bar-row" data-day="<?= $code ?>">
          <span class="day-bar-label" style="color:<?= $color ?>;<?= $isToday?'font-weight:900':'' ?>"><?= $label ?></span>
          <div class="day-bar-track" style="<?= $isToday?'box-shadow:0 0 0 2px '.$color.'40':'' ?>">
            <div class="day-bar-fill" id="day-fill-<?= $code ?>" style="width:<?= max($pct,0) ?>%;background:<?= $color ?>">
              <?php if ($cnt > 0): ?><?= $cnt ?><?php endif; ?>
            </div>
          </div>
          <span class="day-bar-count" id="day-count-<?= $code ?>"><?= $cnt ?></span>
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
            <svg width="120" height="120" viewBox="0 0 120 120" id="sem-svg">
              <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r1 ?>" fill="none" stroke="#f1f5f9" stroke-width="14"/>
              <circle id="sem-arc2" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r1 ?>" fill="none"
                stroke="#f59e0b" stroke-width="14"
                stroke-dasharray="<?= round($dash2,2) ?> <?= round($circ,2) ?>"
                stroke-dashoffset="0" stroke-linecap="round"
                style="<?= $sem2===0?'display:none':'' ?>"/>
              <circle id="sem-arc1" cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r1 ?>" fill="none"
                stroke="#3b82f6" stroke-width="14"
                stroke-dasharray="<?= round($dash1,2) ?> <?= round($circ,2) ?>"
                stroke-dashoffset="<?= round(-$dash2,2) ?>" stroke-linecap="round"
                style="<?= $sem1===0?'display:none':'' ?>"/>
            </svg>
            <div class="sem-donut-label">
              <span class="sem-donut-total" id="sem-total"><?= $total ?></span>
              <span class="sem-donut-sub">Total</span>
            </div>
          </div>
        </div>
        <div class="sem-legend">
          <div class="sem-legend-item">
            <span class="sem-legend-dot" style="background:#3b82f6"></span>
            <span class="sem-legend-name">1st Semester</span>
            <span class="sem-legend-val" id="sem1-val"><?= $sem1 ?></span>
            <span class="sem-legend-pct" id="sem1-pct"><?= $total > 0 ? round($sem1/$total*100) : 0 ?>%</span>
          </div>
          <div class="sem-legend-item">
            <span class="sem-legend-dot" style="background:#f59e0b"></span>
            <span class="sem-legend-name">2nd Semester</span>
            <span class="sem-legend-val" id="sem2-val"><?= $sem2 ?></span>
            <span class="sem-legend-pct" id="sem2-pct"><?= $total > 0 ? round($sem2/$total*100) : 0 ?>%</span>
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
        <div class="prof-type-row">
          <div class="prof-type-ico" style="background:#f0fdf4">🟢</div>
          <div class="prof-type-text">
            <div class="prof-type-label">Full Time</div>
            <div class="prof-type-track">
              <div class="prof-type-fill" id="ft-bar" style="width:<?= $counts['professors']>0?round($fullTime/$counts['professors']*100):0 ?>%;background:#10b981"></div>
            </div>
          </div>
          <span class="prof-type-count" id="ft-count"><?= $fullTime ?></span>
        </div>
        <div class="prof-type-row">
          <div class="prof-type-ico" style="background:#fff7ed">🟠</div>
          <div class="prof-type-text">
            <div class="prof-type-label">Part Time</div>
            <div class="prof-type-track">
              <div class="prof-type-fill" id="pt-bar" style="width:<?= $counts['professors']>0?round($partTime/$counts['professors']*100):0 ?>%;background:#f59e0b"></div>
            </div>
          </div>
          <span class="prof-type-count" id="pt-count"><?= $partTime ?></span>
        </div>
        <div class="prof-divider"></div>
        <div class="sched-ratio">
          <div class="sched-ratio-ico" style="background:#eff6ff">📅</div>
          <span class="sched-ratio-label">With Schedules</span>
          <span class="sched-ratio-val" id="sched-count"><?= $scheduled ?></span>
        </div>
        <div class="sched-ratio">
          <div class="sched-ratio-ico" style="background:#f8fafc">⭕</div>
          <span class="sched-ratio-label">Unassigned</span>
          <span class="sched-ratio-val" id="unsched-count" style="color:<?= $unscheduled>0?'#ef4444':'#10b981' ?>"><?= $unscheduled ?></span>
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
        <div class="db-empty" id="busy-rooms-empty"><span class="ei">🚪</span><p>No room data yet.</p></div>
      <?php else: ?>
        <div class="rank-list" id="busy-rooms-list">
          <?php $maxR = max(array_column($busyRooms,'cnt')) ?: 1; ?>
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
        <div class="db-empty" id="top-profs-empty"><span class="ei">👨‍🏫</span><p>No schedule data yet.</p></div>
      <?php else: ?>
        <div class="rank-list" id="top-profs-list">
          <?php $maxP = max(array_column($topProfs,'cnt')) ?: 1; ?>
          <?php foreach ($topProfs as $i => $prof):
            $isFT = ($prof['employment_type'] ?? 'Full Time') === 'Full Time'; ?>
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
      <div class="db-empty" id="recent-empty">
        <span class="ei">📅</span>
        <p>No schedules yet. <a href="schedules.php" style="color:#3b82f6;font-weight:700;text-decoration:none">Add one now →</a></p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="db-table" id="recent-table">
          <thead>
            <tr>
              <th>Section</th><th>Subject</th><th>Semester</th>
              <th>Day</th><th>Time</th><th>Room</th><th>Instructor</th>
            </tr>
          </thead>
          <tbody id="recent-tbody">
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

<!-- ═══════════ SCRIPTS ═══════════ -->
<script>
(function() {
  const DAY_COLORS = {M:'#3b82f6',T:'#10b981',W:'#6366f1',Th:'#f59e0b',F:'#ef4444',Sa:'#8b5cf6'};
  const DAY_LABELS = {M:'Mon',T:'Tue',W:'Wed',Th:'Thu',F:'Fri',Sa:'Sat'};
  const CIRC = 2 * Math.PI * 52;

  // ── Helpers ───────────────────────────────────────
  function bump(el) {
    el.classList.remove('bump');
    void el.offsetWidth;
    el.classList.add('bump');
    setTimeout(() => el.classList.remove('bump'), 300);
  }
  function flashBadge() {
    const b = document.getElementById('live-badge');
    b.classList.add('flash');
    setTimeout(() => b.classList.remove('flash'), 600);
  }
  function updateStat(id, newVal) {
    const el = document.getElementById(id);
    if (!el) return;
    const formatted = newVal.toLocaleString();
    if (el.textContent !== formatted) { el.textContent = formatted; bump(el); }
  }
  function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function dayFull(code) {
    return {M:'Monday',T:'Tuesday',W:'Wednesday',Th:'Thursday',F:'Friday',Sa:'Saturday'}[code] ?? code;
  }
  function fmt12(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    return ((h % 12) || 12) + ':' + String(m).padStart(2,'0') + (h >= 12 ? ' PM' : ' AM');
  }
  function formatTimeRange(s, e) { return fmt12(s) + ' – ' + fmt12(e); }

  // ── Today's schedule: client-side live status refresh ─────────────────────
  // Runs every 30 seconds to flip row statuses without a server round-trip
  function refreshTodayStatuses() {
    const now = new Date();
    const nowSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();

    const rows = document.querySelectorAll('#today-tbody tr[data-start]');
    let ongoing = 0, upcoming = 0, done = 0;

    rows.forEach(row => {
      const s = timeToSec(row.dataset.start);
      const e = timeToSec(row.dataset.end);
      let st;
      if (nowSec >= s && nowSec < e)  st = 'ongoing';
      else if (nowSec < s)            st = 'upcoming';
      else                             st = 'done';

      // Update row class
      row.classList.remove('row-ongoing','row-done');
      if (st === 'ongoing')  { row.classList.add('row-ongoing'); ongoing++; }
      else if (st === 'done'){ row.classList.add('row-done');    done++; }
      else                   { upcoming++; }

      // Update badge in first cell
      const badgeCell = row.cells[0];
      if (!badgeCell) return;
      let prog = 0;
      if (st === 'ongoing') {
        const dur = e - s;
        prog = dur > 0 ? Math.min(100, Math.round((nowSec - s) / dur * 100)) : 0;
      }

      const badgeHtml = {
        ongoing:  `<span class="status-badge st-ongoing"><span class="chip-dot"></span> Ongoing</span>
                   <div class="time-progress"><div class="time-progress-fill" style="width:${prog}%"></div></div>`,
        upcoming: `<span class="status-badge st-upcoming"><span class="chip-dot"></span> Upcoming</span>`,
        done:     `<span class="status-badge st-done"><span class="chip-dot"></span> Done</span>`,
      };
      badgeCell.innerHTML = badgeHtml[st];

      // Update room occupancy dot (5th cell, index 4)
      const roomCell = row.cells[4];
      if (roomCell) {
        const occ = roomCell.querySelector('.room-occ');
        if (occ) {
          occ.className = 'room-occ ' + (st === 'ongoing' ? 'occupied' : 'free');
        }
      }
    });

    // Update header chips
    const oc = document.getElementById('chip-ongoing-cnt');
    const uc = document.getElementById('chip-upcoming-cnt');
    const dc = document.getElementById('chip-done-cnt');
    if (oc) oc.textContent = ongoing;
    if (uc) uc.textContent = upcoming;
    if (dc) dc.textContent = done;
  }

  function timeToSec(t) {
    if (!t) return 0;
    const [h, m] = t.split(':').map(Number);
    return h * 3600 + m * 60;
  }

  // Refresh statuses every 30 seconds
  refreshTodayStatuses();
  setInterval(refreshTodayStatuses, 30000);

  // ── SSE stat/chart updates ────────────────────────
  function updateDayChart(byDay) {
    const vals = Object.values(byDay);
    const maxD = vals.length ? Math.max(...vals, 1) : 1;
    for (const [code] of Object.entries(DAY_LABELS)) {
      const cnt  = byDay[code] ?? 0;
      const pct  = Math.round((cnt / maxD) * 100);
      const fill = document.getElementById('day-fill-' + code);
      const countEl = document.getElementById('day-count-' + code);
      if (fill)    { fill.style.width = Math.max(pct, 0) + '%'; fill.textContent = cnt > 0 ? cnt : ''; }
      if (countEl) countEl.textContent = cnt;
    }
  }

  function updateSemester(sem1, sem2) {
    const total = sem1 + sem2;
    const dash1 = total > 0 ? (sem1 / total) * CIRC : 0;
    const dash2 = total > 0 ? (sem2 / total) * CIRC : CIRC;
    const arc1 = document.getElementById('sem-arc1');
    const arc2 = document.getElementById('sem-arc2');
    if (arc1) {
      arc1.style.display = sem1 === 0 ? 'none' : '';
      arc1.setAttribute('stroke-dasharray', dash1.toFixed(2) + ' ' + CIRC.toFixed(2));
      arc1.setAttribute('stroke-dashoffset', (-dash2).toFixed(2));
    }
    if (arc2) {
      arc2.style.display = sem2 === 0 ? 'none' : '';
      arc2.setAttribute('stroke-dasharray', dash2.toFixed(2) + ' ' + CIRC.toFixed(2));
    }
    const tot = document.getElementById('sem-total');
    if (tot) tot.textContent = total;
    const v1 = document.getElementById('sem1-val'); if (v1) v1.textContent = sem1;
    const v2 = document.getElementById('sem2-val'); if (v2) v2.textContent = sem2;
    const p1 = document.getElementById('sem1-pct'); if (p1) p1.textContent = total > 0 ? Math.round(sem1/total*100)+'%' : '0%';
    const p2 = document.getElementById('sem2-pct'); if (p2) p2.textContent = total > 0 ? Math.round(sem2/total*100)+'%' : '0%';
  }

  function updateProfessors(d) {
    const totalP = d.counts.professors || 1;
    const unscheduled = d.counts.professors - d.scheduled;
    const ftBar = document.getElementById('ft-bar');
    const ptBar = document.getElementById('pt-bar');
    if (ftBar) ftBar.style.width = Math.round(d.fullTime / totalP * 100) + '%';
    if (ptBar) ptBar.style.width = Math.round(d.partTime / totalP * 100) + '%';
    const ftC = document.getElementById('ft-count'); if (ftC) ftC.textContent = d.fullTime;
    const ptC = document.getElementById('pt-count'); if (ptC) ptC.textContent = d.partTime;
    const sc  = document.getElementById('sched-count'); if (sc) sc.textContent = d.scheduled;
    const uc  = document.getElementById('unsched-count');
    if (uc) { uc.textContent = unscheduled; uc.style.color = unscheduled > 0 ? '#ef4444' : '#10b981'; }
  }

  function rebuildRankList(containerId, items, barColor, subFn) {
    const list = document.getElementById(containerId);
    if (!list) return;
    if (!items.length) { list.innerHTML = ''; return; }
    const maxVal = Math.max(...items.map(i => i.cnt), 1);
    list.innerHTML = items.map((item, i) => `
      <div class="rank-item">
        <span class="rank-num ${['top1','top2','top3'][i] ?? ''}">${i + 1}</span>
        <div class="rank-info">
          <div class="rank-name">${escHtml(item.name)}</div>
          <div class="rank-sub">${subFn(item)}</div>
        </div>
        <div class="rank-bar-wrap">
          <div class="rank-bar-track">
            <div class="rank-bar-fill" style="width:${Math.round(item.cnt/maxVal*100)}%;background:${barColor}"></div>
          </div>
          <div class="rank-count">${item.cnt} sessions</div>
        </div>
      </div>
    `).join('');
  }

  // Rebuild today's table from SSE data (preserves live status refresh)
  function rebuildTodayTable(schedules) {
    const tbody = document.getElementById('today-tbody');
    if (!tbody || !schedules) return;

    const now = new Date();
    const nowSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();

    tbody.innerHTML = schedules.map(r => {
      const s = timeToSec(r.start_time), e = timeToSec(r.end_time);
      let st;
      if (nowSec >= s && nowSec < e)  st = 'ongoing';
      else if (nowSec < s)            st = 'upcoming';
      else                             st = 'done';

      const rowCls = st === 'ongoing' ? 'row-ongoing' : st === 'done' ? 'row-done' : '';
      const prog   = st === 'ongoing' && (e-s) > 0 ? Math.min(100, Math.round((nowSec-s)/(e-s)*100)) : 0;

      const badgeHtml = {
        ongoing:  `<span class="status-badge st-ongoing"><span class="chip-dot"></span> Ongoing</span>
                   <div class="time-progress"><div class="time-progress-fill" style="width:${prog}%"></div></div>`,
        upcoming: `<span class="status-badge st-upcoming"><span class="chip-dot"></span> Upcoming</span>`,
        done:     `<span class="status-badge st-done"><span class="chip-dot"></span> Done</span>`,
      }[st];

      const semClass = r.semester === '1st Semester' ? 's1' : 's2';
      const semLabel = r.semester === '1st Semester' ? '1st Sem' : '2nd Sem';
      return `<tr class="${rowCls}" data-start="${escHtml(r.start_time)}" data-end="${escHtml(r.end_time)}">
        <td>${badgeHtml}</td>
        <td><span class="cell-section">${escHtml(r.section_name)}</span></td>
        <td>
          <span class="sub-chip">${escHtml(r.subject_code)}</span>
          <div class="sub-name">${escHtml(r.subject_name)}</div>
        </td>
        <td><span class="time-mono">${formatTimeRange(r.start_time, r.end_time)}</span></td>
        <td>
          <div class="room-occ ${st === 'ongoing' ? 'occupied' : 'free'}">
            <span class="room-occ-dot"></span>${escHtml(r.room_name)}
          </div>
          ${r.room_type ? `<div style="font-size:.7rem;color:#94a3b8;margin-top:2px">${escHtml(r.room_type)}</div>` : ''}
        </td>
        <td><span class="prof-name">${escHtml(r.prof_name)}</span></td>
        <td><span class="sem-pill ${semClass}">${semLabel}</span></td>
      </tr>`;
    }).join('');
  }

  let prevScheduleIds = [];

  function rebuildRecentTable(schedules, prevIds) {
    const tbody = document.getElementById('recent-tbody');
    if (!tbody) return;
    tbody.innerHTML = schedules.map((r, idx) => {
      const color = DAY_COLORS[r.day_code] ?? '#64748b';
      const isNew = idx === 0 && prevIds.length && r.id !== prevIds[0];
      const semClass = r.semester === '1st Semester' ? 's1' : 's2';
      const semLabel = r.semester === '1st Semester' ? '1st Sem' : '2nd Sem';
      return `<tr class="${isNew ? 'row-new' : ''}">
        <td><span class="cell-section">${escHtml(r.section_name)}</span></td>
        <td>
          <span class="sub-chip">${escHtml(r.subject_code)}</span>
          <div class="sub-name">${escHtml(r.subject_name)}</div>
        </td>
        <td><span class="sem-pill ${semClass}">${semLabel}</span></td>
        <td>
          <div class="day-pill">
            <span class="day-dot" style="background:${color}"></span>
            <span style="color:${color}">${dayFull(r.day_code)}</span>
          </div>
        </td>
        <td><span class="time-mono">${formatTimeRange(r.start_time, r.end_time)}</span></td>
        <td>
          <span class="room-tag">${escHtml(r.room_name)}
            ${r.room_type ? `<span class="room-type-badge">${escHtml(r.room_type)}</span>` : ''}
          </span>
        </td>
        <td><span class="prof-name">${escHtml(r.prof_name)}</span></td>
      </tr>`;
    }).join('');
  }

  // ── SSE connection ───────────────────────────────
  function connect() {
    const label = document.getElementById('live-label');
    const badge = document.getElementById('live-badge');
    const es = new EventSource('dashboard-stream.php');

    es.onopen = () => {
      if (label) label.textContent = 'Live';
    };

    es.onmessage = (e) => {
      try {
        const d = JSON.parse(e.data);
        flashBadge();

        for (const key of ['courses','sections','subjects','professors','rooms','schedules']) {
          updateStat('stat-' + key, d.counts[key]);
        }

        updateDayChart(d.byDay);
        updateSemester(d.sem1, d.sem2);
        updateProfessors(d);

        rebuildRankList('busy-rooms-list', d.busyRooms, '#ef4444', item => escHtml(item.room_type ?? ''));
        rebuildRankList('top-profs-list', d.topProfs, '#8b5cf6', item => {
          const ft = (item.employment_type ?? 'Full Time') === 'Full Time';
          return `<span style="color:${ft?'#10b981':'#f59e0b'}">${ft ? '🟢 Full Time' : '🟠 Part Time'}</span>`;
        });

        rebuildRecentTable(d.recentSchedules, prevScheduleIds);
        prevScheduleIds = d.recentSchedules.map(r => r.id);

        // Rebuild today's table if SSE sends it (add todaySchedules to dashboard-stream.php)
        if (d.todaySchedules) {
          rebuildTodayTable(d.todaySchedules);
          refreshTodayStatuses();
        }

      } catch (err) {
        console.error('Dashboard SSE parse error:', err);
      }
    };

    es.onerror = () => {
      if (label) label.textContent = 'Reconnecting…';
      es.close();
      setTimeout(connect, 6000);
    };
  }

  connect();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>