<?php
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// ============================================================
// CONFLICT CHECKER
// ============================================================
function checkScheduleConflict(PDO $db, array $sc, ?int $editId = null): array {
    $conflicts  = [];
    $excludeSql = $editId ? " AND s.id != $editId" : '';
    $sql = "SELECT s.*, sec.name AS sec_name, sub.code AS sub_code,
                   r.name AS rm_name, p.name AS pr_name
            FROM schedules s
            JOIN sections  sec ON sec.id = s.section_id
            JOIN subjects  sub ON sub.id = s.subject_id
            JOIN rooms     r   ON r.id   = s.room_id
            JOIN professors p  ON p.id   = s.professor_id
            WHERE s.day_code = ?
              AND TIME(s.start_time) < TIME(?)
              AND TIME(s.end_time)   > TIME(?)
              $excludeSql";
    $stmt = $db->prepare($sql);
    $stmt->execute([$sc['day_code'], $sc['end_time'], $sc['start_time']]);
    foreach ($stmt->fetchAll() as $o) {
        if ($o['room_id']      == $sc['room_id'])      $conflicts[] = ['type'=>'room',     'msg'=>"Room <strong>{$o['rm_name']}</strong> already booked by <strong>{$o['sub_code']}</strong> ({$o['sec_name']}) ".formatTimeRange($o['start_time'],$o['end_time'])];
        if ($o['professor_id'] == $sc['professor_id']) $conflicts[] = ['type'=>'professor', 'msg'=>"Prof. already teaching <strong>{$o['sub_code']}</strong> ({$o['sec_name']}) ".formatTimeRange($o['start_time'],$o['end_time'])];
        if ($o['section_id']   == $sc['section_id'])   $conflicts[] = ['type'=>'section',   'msg'=>"Section already has <strong>{$o['sub_code']}</strong> on ".dayFull($sc['day_code'])." ".formatTimeRange($o['start_time'],$o['end_time'])];
    }
    return $conflicts;
}

// ============================================================
// AJAX HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    $action    = $_POST['action'] ?? '';
    $sectionId = (int)($_POST['section_id']   ?? 0);
    $subjectId = (int)($_POST['subject_id']   ?? 0);
    $semester  = sanitize($_POST['semester']  ?? '');
    $dayCode   = sanitize($_POST['day_code']  ?? '');
    $startTime = sanitize($_POST['start_time']?? '');
    $endTime   = sanitize($_POST['end_time']  ?? '');
    $roomId    = (int)($_POST['room_id']      ?? 0);
    $profId    = (int)($_POST['professor_id'] ?? 0);
    $editId    = ($action === 'update') ? (int)($_POST['id'] ?? 0) : null;

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
        $db->prepare("DELETE FROM schedules WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true,'msg'=>'Schedule deleted.']);
        exit;
    }

    if (!$sectionId||!$subjectId||!$semester||!$dayCode||!$startTime||!$endTime||!$roomId||!$profId) {
        echo json_encode(['ok'=>false,'msg'=>'All fields are required.']); exit;
    }
    if (strtotime($startTime) >= strtotime($endTime)) {
        echo json_encode(['ok'=>false,'msg'=>'End time must be after start time.']); exit;
    }

    // Duplicate-subject guard (skip for Consultation type)
    $subTypeRow = $db->prepare("SELECT subject_type FROM subjects WHERE id=?");
    $subTypeRow->execute([$subjectId]);
    $subTypeVal = $subTypeRow->fetchColumn();

    if ($subTypeVal !== 'Consultation') {
        $dupSql    = "SELECT COUNT(*) FROM schedules WHERE section_id=? AND subject_id=? AND semester=?";
        $dupParams = [$sectionId, $subjectId, $semester];
        if ($editId) { $dupSql .= " AND id!=?"; $dupParams[] = $editId; }
        $dupStmt = $db->prepare($dupSql);
        $dupStmt->execute($dupParams);
        if ((int)$dupStmt->fetchColumn() > 0) {
            $subLabel = $db->prepare("SELECT CONCAT(code,' – ',name) FROM subjects WHERE id=?");
            $subLabel->execute([$subjectId]);
            $label = $subLabel->fetchColumn() ?: 'This subject';
            echo json_encode(['ok'=>false,'duplicate'=>true,'msg'=>"\"$label\" is already scheduled for this section in the {$semester}."]);
            exit;
        }
    }

    $sc = ['section_id'=>$sectionId,'subject_id'=>$subjectId,'semester'=>$semester,
           'day_code'=>$dayCode,'start_time'=>$startTime,'end_time'=>$endTime,
           'room_id'=>$roomId,'professor_id'=>$profId];

    $conflicts = checkScheduleConflict($db, $sc, $editId);
    if (!empty($conflicts)) {
        echo json_encode(['ok'=>false,'msg'=>'Conflicts detected.','conflicts'=>$conflicts]); exit;
    }

    try {
        if ($action === 'create') {
            $db->prepare("INSERT INTO schedules (section_id,subject_id,semester,day_code,start_time,end_time,room_id,professor_id) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$sectionId,$subjectId,$semester,$dayCode,$startTime,$endTime,$roomId,$profId]);
            echo json_encode(['ok'=>true,'msg'=>'Schedule added successfully.']);
        } elseif ($action === 'update') {
            $db->prepare("UPDATE schedules SET section_id=?,subject_id=?,semester=?,day_code=?,start_time=?,end_time=?,room_id=?,professor_id=? WHERE id=?")
               ->execute([$sectionId,$subjectId,$semester,$dayCode,$startTime,$endTime,$roomId,$profId,$editId]);
            echo json_encode(['ok'=>true,'msg'=>'Schedule updated successfully.']);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>'Database error: '.$e->getMessage()]);
    }
    exit;
}

// ============================================================
// PAGE LOAD
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Schedules';
require_once __DIR__ . '/includes/header.php';

$courses    = $db->query("SELECT id,code,name FROM courses ORDER BY code")->fetchAll();
$sections   = $db->query("SELECT s.*,c.code AS c_code FROM sections s JOIN courses c ON c.id=s.course_id ORDER BY s.year_level,s.name")->fetchAll();
$rooms      = $db->query("SELECT * FROM rooms ORDER BY building,name")->fetchAll();
$professors = $db->query("SELECT id,name,department,employment_type FROM professors ORDER BY name")->fetchAll();

$fCourse  = (int)($_GET['course_id']    ?? 0);
$fSection = (int)($_GET['section_id']   ?? 0);
$fSem     = sanitize($_GET['semester']  ?? '');
$fProf    = (int)($_GET['professor_id'] ?? 0);
$fRoom    = (int)($_GET['room_id']      ?? 0);

$where = ['1=1']; $params = [];
if ($fCourse)  { $where[] = 'sec.course_id=?';   $params[] = $fCourse; }
if ($fSection) { $where[] = 'sc.section_id=?';   $params[] = $fSection; }
if ($fSem)     { $where[] = 'sc.semester=?';     $params[] = $fSem; }
if ($fProf)    { $where[] = 'sc.professor_id=?'; $params[] = $fProf; }
if ($fRoom)    { $where[] = 'sc.room_id=?';      $params[] = $fRoom; }

$stmt = $db->prepare("
    SELECT sc.*,
           sec.name       AS sec_name,
           sec.year_level AS year_level,
           sec.course_id  AS course_id,
           sub.code       AS sub_code,
           sub.name       AS sub_name,
           r.name         AS rm_name,
           r.room_type    AS rm_type,
           p.name         AS pr_name,
           c.code         AS c_code
    FROM schedules sc
    JOIN sections   sec ON sec.id = sc.section_id
    JOIN courses    c   ON c.id   = sec.course_id
    JOIN subjects   sub ON sub.id = sc.subject_id
    JOIN rooms      r   ON r.id   = sc.room_id
    JOIN professors p   ON p.id   = sc.professor_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY sec.name, FIELD(sc.day_code,'M','T','W','Th','F','Sa'), sc.start_time
");
$stmt->execute($params);
$schedules = $stmt->fetchAll();

$dayColors = ['M'=>'#3b82f6','T'=>'#10b981','W'=>'#6366f1','Th'=>'#f59e0b','F'=>'#ef4444','Sa'=>'#8b5cf6'];
$dayBgs    = ['M'=>'#eff6ff','T'=>'#f0fdf4','W'=>'#eef2ff','Th'=>'#fffbeb','F'=>'#fff1f2','Sa'=>'#faf5ff'];
$days      = ['M'=>'Monday','T'=>'Tuesday','W'=>'Wednesday','Th'=>'Thursday','F'=>'Friday','Sa'=>'Saturday'];

$totalSections = count(array_unique(array_column($schedules,'section_id')));
$totalRooms    = count(array_unique(array_column($schedules,'room_id')));
$totalProfs    = count(array_unique(array_column($schedules,'professor_id')));
$activeFilters = (int)(bool)$fCourse+(int)(bool)$fSection+(int)(bool)$fSem+(int)(bool)$fProf+(int)(bool)$fRoom;
?>

<style>
.sp { font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Header ── */
.sp-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.sp-header-left h2 { font-size:1.5rem; font-weight:800; color:var(--text,#0f172a); margin:0 0 2px; letter-spacing:-.5px; }
.sp-header-left p  { margin:0; color:var(--text3,#94a3b8); font-size:.85rem; }
.btn-add-sched {
    display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border-radius:10px;
    background:#1e3a8a; color:#fff; font-size:.875rem; font-weight:700; border:none; cursor:pointer;
    box-shadow:0 2px 8px rgba(30,58,138,.25); transition:background .15s,transform .15s,box-shadow .15s;
}
.btn-add-sched:hover { background:#1e40af; transform:translateY(-1px); box-shadow:0 4px 14px rgba(30,58,138,.3); }

/* ── Stats ── */
.sp-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }
.sp-stat { background:var(--card-bg,#fff); border:1.5px solid var(--border,#e2e8f0); border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:11px; transition:box-shadow .15s,transform .15s; }
.sp-stat:hover { box-shadow:0 6px 20px rgba(0,0,0,.07); transform:translateY(-1px); }
.sp-stat-ico { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.sp-stat-val { font-size:1.4rem; font-weight:900; color:var(--text,#0f172a); line-height:1; }
.sp-stat-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3,#94a3b8); margin-top:2px; }

/* ── Filters ── */
.sp-filters { background:var(--card-bg,#fff); border:1.5px solid var(--border,#e2e8f0); border-radius:13px; padding:16px 20px; margin-bottom:20px; }
.sp-filters-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:13px; }
.sp-filters-title { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:var(--text3,#94a3b8); display:flex; align-items:center; gap:6px; }
.active-badge { background:#dbeafe; color:#1d4ed8; border-radius:20px; padding:1px 8px; font-size:.68rem; font-weight:800; }
.sp-filter-grid { display:grid; grid-template-columns:repeat(5,1fr) auto; gap:10px; align-items:end; }
.sp-fi label { display:block; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text3,#94a3b8); margin-bottom:4px; }
.sp-fi select { width:100%; padding:8px 11px; border:1.5px solid var(--border,#e2e8f0); border-radius:8px; font-size:.83rem; color:var(--text,#1e293b); background:var(--card-bg,#fff); cursor:pointer; transition:border-color .15s; }
.sp-fi select:focus { outline:none; border-color:#3b82f6; }
.sp-filter-btns { display:flex; gap:7px; }
.btn-go { padding:8px 18px; border-radius:8px; font-size:.83rem; font-weight:700; background:#1e3a8a; color:#fff; border:none; cursor:pointer; transition:background .15s; }
.btn-go:hover { background:#1e40af; }
.btn-clr { padding:8px 13px; border-radius:8px; font-size:.83rem; font-weight:600; background:var(--hover,#f1f5f9); color:var(--text2,#475569); border:1.5px solid var(--border,#e2e8f0); text-decoration:none; transition:background .15s; }
.btn-clr:hover { background:#e2e8f0; }

/* ── Table card ── */
.sp-card { background:var(--card-bg,#fff); border:1.5px solid var(--border,#e2e8f0); border-radius:14px; overflow:hidden; }
.sp-card-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1.5px solid var(--border,#e2e8f0); }
.sp-card-title { font-size:.95rem; font-weight:800; color:var(--text,#0f172a); }
.sp-card-count { font-size:.75rem; font-weight:700; color:#1d4ed8; background:#dbeafe; border-radius:20px; padding:3px 10px; }
.sp-table { width:100%; border-collapse:collapse; }
.sp-table thead tr { background:var(--hover,#f8fafc); }
.sp-table th { padding:10px 14px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--text3,#94a3b8); text-align:left; border-bottom:1.5px solid var(--border,#e2e8f0); white-space:nowrap; }
.sp-table td { padding:13px 14px; font-size:.875rem; border-bottom:1px solid var(--border,#f1f5f9); vertical-align:middle; color:var(--text,#334155); }
.sp-table tbody tr { transition:background .1s; }
.sp-table tbody tr:hover { background:var(--hover,#f8fafc); }
.sp-table tbody tr:last-child td { border-bottom:none; }
.cell-section-name { font-weight:700; color:var(--text,#0f172a); font-size:.875rem; }
.cell-course-tag   { font-size:.68rem; font-weight:600; color:var(--text3,#94a3b8); margin-top:1px; }
.sub-chip { display:inline-block; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:6px; font-size:.72rem; font-weight:800; padding:2px 8px; letter-spacing:.3px; }
.sub-name-cell { font-size:.82rem; color:var(--text2,#475569); margin-top:2px; line-height:1.3; }
.sem-tag { display:inline-flex; align-items:center; font-size:.72rem; font-weight:700; padding:3px 10px; border-radius:20px; }
.sem1 { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
.sem2 { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
.day-cell { display:flex; align-items:center; gap:7px; }
.day-pip  { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.day-txt  { font-weight:700; font-size:.875rem; }
.time-tag { font-family:'DM Mono','Courier New',monospace; font-size:.77rem; font-weight:600; background:var(--hover,#f1f5f9); color:var(--text2,#475569); padding:4px 10px; border-radius:7px; white-space:nowrap; display:inline-block; border:1px solid var(--border,#e2e8f0); }
.room-tag { display:inline-flex; align-items:center; gap:5px; font-size:.82rem; color:var(--text,#334155); }
.room-type { font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; background:var(--hover,#f1f5f9); color:var(--text3,#94a3b8); padding:1px 6px; border-radius:4px; margin-left:4px; }
.prof-name { font-size:.875rem; color:var(--text,#334155); font-weight:500; }
.row-acts { display:flex; gap:6px; align-items:center; }
.btn-row-edit { padding:5px 13px; border-radius:7px; font-size:.75rem; font-weight:700; border:1.5px solid var(--border,#e2e8f0); background:var(--card-bg,#fff); color:var(--text2,#475569); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:border-color .15s,color .15s,background .15s; }
.btn-row-edit:hover { border-color:#3b82f6; color:#1d4ed8; background:#eff6ff; }
.btn-row-del { padding:5px 10px; border-radius:7px; font-size:.75rem; font-weight:700; border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626; cursor:pointer; display:inline-flex; align-items:center; gap:3px; transition:background .15s,border-color .15s; }
.btn-row-del:hover { background:#fee2e2; border-color:#f87171; }
.btn-row-del.deleting { opacity:.5; pointer-events:none; }
.sp-empty { text-align:center; padding:56px 24px; color:var(--text3,#94a3b8); }
.sp-empty .ei { font-size:2.8rem; display:block; margin-bottom:12px; }
.sp-empty p { margin:0; font-size:.9rem; }
.sp-empty a { color:#3b82f6; font-weight:700; text-decoration:none; }

/* ══════════════════════════════════════
   MODAL
══════════════════════════════════════ */
#sc-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:600; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(3px); }
#sc-modal.open { display:flex; }
.sc-modal-box { background:var(--card-bg,#fff); border-radius:18px; width:100%; max-width:780px; max-height:94vh; overflow-y:auto; box-shadow:0 40px 100px rgba(0,0,0,.28); animation:slideUp .24s cubic-bezier(.16,1,.3,1); }
@keyframes slideUp { from{transform:translateY(22px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }

/* Modal header */
.sc-modal-hdr {
    display:flex; align-items:center; justify-content:space-between;
    padding:22px 26px 18px;
    border-bottom:1.5px solid var(--border,#e2e8f0);
    position:sticky; top:0; background:var(--card-bg,#fff); z-index:1;
    border-radius:18px 18px 0 0;
    background:linear-gradient(135deg,#f8faff 0%,var(--card-bg,#fff) 100%);
}
.sc-modal-hdr-l { display:flex; align-items:center; gap:14px; }
.sc-modal-ico {
    width:44px; height:44px; border-radius:12px;
    background:linear-gradient(135deg,#1e3a8a,#3b82f6);
    display:flex; align-items:center; justify-content:center; font-size:1.2rem;
    box-shadow:0 4px 12px rgba(30,58,138,.25);
}
.sc-modal-ttl { font-size:1.05rem; font-weight:800; color:var(--text,#0f172a); }
.sc-modal-sub { font-size:.73rem; color:var(--text3,#94a3b8); margin-top:2px; }
.sc-modal-cls { background:none; border:none; width:34px; height:34px; display:flex; align-items:center; justify-content:center; font-size:1rem; color:var(--text3,#94a3b8); cursor:pointer; border-radius:9px; transition:background .15s,color .15s; }
.sc-modal-cls:hover { background:var(--hover,#f1f5f9); color:var(--text,#0f172a); }
.sc-modal-body { padding:24px 26px 28px; }

/* Form section headers */
.fsec { margin-bottom:22px; }
.fsec:last-of-type { margin-bottom:0; }
.fsec-title {
    display:flex; align-items:center; gap:8px;
    font-size:.68rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.8px; color:var(--text3,#94a3b8);
    padding-bottom:10px; margin-bottom:14px;
    border-bottom:1.5px solid var(--border,#f1f5f9);
}
.fsec-title .fi-icon { width:22px; height:22px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:.85rem; }
.fsec-step {
    width:22px; height:22px; border-radius:50%;
    background:#1e3a8a; color:#fff;
    font-size:.65rem; font-weight:900;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}

.fg2 { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.fg3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px 18px; }
.fg4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:14px 18px; }

.fl { display:block; font-size:.72rem; font-weight:700; color:var(--text2,#475569); margin-bottom:6px; text-transform:uppercase; letter-spacing:.35px; }
.fl .req { color:#ef4444; margin-left:2px; }

.fc { width:100%; padding:10px 13px; border:1.5px solid var(--border,#e2e8f0); border-radius:10px; font-size:.875rem; color:var(--text,#1e293b); background:var(--input-bg,#f8fafc); transition:border-color .15s,box-shadow .15s; box-sizing:border-box; }
.fc:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.13); background:#fff; }

/* ── Searchable picker ── */
.picker-wrap { position:relative; }
.picker-search-box {
    display:flex; align-items:center;
    border:1.5px solid var(--border,#e2e8f0); border-radius:10px;
    background:var(--input-bg,#f8fafc); overflow:hidden;
    transition:border-color .15s,box-shadow .15s;
}
.picker-search-box:focus-within {
    border-color:#3b82f6;
    box-shadow:0 0 0 3px rgba(59,130,246,.13);
    background:#fff;
}
.picker-search-icon { padding:0 10px; color:var(--text3,#94a3b8); display:flex; align-items:center; flex-shrink:0; }
.picker-search-input { border:none; outline:none; background:transparent; padding:10px 10px 10px 0; font-size:.875rem; color:var(--text,#1e293b); width:100%; }
.picker-search-input::placeholder { color:var(--text3,#94a3b8); }

.picker-dropdown {
    position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:200;
    background:var(--card-bg,#fff); border:1.5px solid var(--border,#e2e8f0);
    border-radius:11px; box-shadow:0 12px 40px rgba(0,0,0,.14);
    max-height:220px; overflow-y:auto; display:none;
}
.picker-dropdown.open { display:block; }
.picker-option {
    padding:10px 14px; cursor:pointer; font-size:.855rem;
    color:var(--text,#334155); transition:background .1s;
    display:flex; align-items:center; gap:10px;
    border-bottom:1px solid var(--border,#f1f5f9);
}
.picker-option:last-child { border-bottom:none; }
.picker-option:hover, .picker-option.highlighted { background:var(--hover,#f1f5f9); }
.picker-option.selected { background:#eff6ff; color:#1d4ed8; }
.picker-option-main  { font-weight:700; line-height:1.3; }
.picker-option-sub   { font-size:.72rem; color:var(--text3,#94a3b8); margin-top:1px; }
.picker-option-icon  { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; }
.picker-no-results   { padding:16px; text-align:center; font-size:.82rem; color:var(--text3,#94a3b8); }
.picker-selected-badge {
    display:none; align-items:center; gap:8px;
    margin-top:6px; padding:8px 11px; border-radius:9px;
    background:#f0fdf4; border:1.5px solid #bbf7d0;
    font-size:.8rem; color:#166534; font-weight:600;
}
.picker-selected-badge.show { display:flex; }
.picker-selected-badge-clear { margin-left:auto; cursor:pointer; color:#94a3b8; font-size:.85rem; transition:color .15s; }
.picker-selected-badge-clear:hover { color:#dc2626; }

/* Hidden actual selects */
.picker-hidden { display:none !important; }

/* Day selector */
.day-selector { display:grid; grid-template-columns:repeat(6,1fr); gap:7px; }
.day-btn {
    padding:10px 4px; border-radius:9px; font-size:.75rem; font-weight:800;
    border:1.5px solid var(--border,#e2e8f0); background:var(--card-bg,#fff);
    color:var(--text3,#94a3b8); cursor:pointer; text-align:center;
    transition:all .15s; display:flex; flex-direction:column; align-items:center; gap:3px;
}
.day-btn .day-dot-sm { width:6px; height:6px; border-radius:50%; opacity:0; transition:opacity .15s; }
.day-btn:hover { border-color:#3b82f6; color:#1d4ed8; background:#eff6ff; }
.day-btn.selected { color:#fff; border-color:transparent; }
.day-btn.selected .day-dot-sm { opacity:1; background:rgba(255,255,255,.5); }

/* Time range display */
.time-preview {
    margin-top:8px; padding:9px 13px; border-radius:8px;
    background:var(--hover,#f1f5f9); border:1px solid var(--border,#e2e8f0);
    font-size:.8rem; color:var(--text2,#475569); display:none;
    align-items:center; gap:6px;
}
.time-preview.show { display:flex; }
.time-preview strong { color:var(--text,#0f172a); font-family:'DM Mono','Courier New',monospace; }

/* Notice box */
.modal-notice { border-radius:11px; padding:13px 16px; margin-top:16px; display:none; }
.modal-notice.show { display:block; }
.modal-notice.type-conflict { background:#fef2f2; border:1.5px solid #fecaca; }
.modal-notice.type-conflict .mn-title { color:#991b1b; }
.modal-notice.type-conflict .mn-item  { background:#fff5f5; border:1px solid #fecaca; color:#7f1d1d; }
.modal-notice.type-duplicate { background:#fffbeb; border:1.5px solid #fde68a; }
.modal-notice.type-duplicate .mn-title { color:#92400e; }
.modal-notice.type-duplicate .mn-item  { background:#fefce8; border:1px solid #fde68a; color:#78350f; }
.mn-title { display:flex; align-items:center; gap:7px; font-size:.82rem; font-weight:700; margin-bottom:8px; }
.mn-list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:5px; }
.mn-item { display:flex; align-items:flex-start; gap:8px; border-radius:8px; padding:8px 11px; font-size:.8rem; }
.mc-badge { font-size:.63rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; padding:2px 7px; border-radius:20px; flex-shrink:0; margin-top:1px; }
.mc-room { background:#fee2e2; color:#dc2626; }
.mc-prof { background:#fef3c7; color:#b45309; }
.mc-sec  { background:#ede9fe; color:#6d28d9; }
.mc-dup  { background:#fef3c7; color:#b45309; }

/* Modal footer */
.sc-modal-ftr { display:flex; align-items:center; gap:10px; margin-top:22px; padding-top:18px; border-top:1.5px solid var(--border,#e2e8f0); flex-wrap:wrap; }
.btn-save-sc { padding:11px 24px; border-radius:10px; font-size:.875rem; font-weight:800; background:linear-gradient(135deg,#1e3a8a,#2563eb); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; gap:7px; box-shadow:0 3px 12px rgba(30,58,138,.3); transition:opacity .15s,transform .15s; }
.btn-save-sc:hover { transform:translateY(-1px); opacity:.92; }
.btn-save-sc.busy  { opacity:.65; pointer-events:none; }
.btn-cncl-sc { padding:11px 18px; border-radius:10px; font-size:.875rem; font-weight:600; background:var(--hover,#f1f5f9); color:var(--text2,#475569); border:1.5px solid var(--border,#e2e8f0); cursor:pointer; transition:background .15s; }
.btn-cncl-sc:hover { background:#e2e8f0; }
.auto-check-note { font-size:.72rem; color:var(--text3,#94a3b8); display:flex; align-items:center; gap:4px; margin-left:auto; }

/* Toast */
#sp-toasts { position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.sp-toast { display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:11px; font-size:.875rem; font-weight:600; max-width:360px; box-shadow:0 8px 30px rgba(0,0,0,.13); animation:tIn .25s cubic-bezier(.16,1,.3,1); pointer-events:auto; }
.sp-toast.ok  { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }
.sp-toast.err { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; }
@keyframes tIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes tOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }

@media(max-width:1024px){ .sp-filter-grid{grid-template-columns:1fr 1fr 1fr} }
@media(max-width:860px) { .sp-stats{grid-template-columns:1fr 1fr} }
@media(max-width:640px) {
    .sp-stats{grid-template-columns:1fr 1fr}
    .fg2,.fg3,.fg4{grid-template-columns:1fr}
    .sp-header{flex-direction:column;align-items:flex-start}
    .sp-filter-grid{grid-template-columns:1fr 1fr}
    .day-selector{grid-template-columns:repeat(3,1fr)}
}
</style>

<div id="sp-toasts"></div>

<div class="sp">

  <!-- Header -->
  <div class="sp-header">
    <div class="sp-header-left">
      <h2>Schedule Management</h2>
      <p>Manage class schedules with real-time conflict detection</p>
    </div>
    <button class="btn-add-sched" onclick="openModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Schedule
    </button>
  </div>

  <!-- Stats -->
  <div class="sp-stats">
    <div class="sp-stat">
      <div class="sp-stat-ico" style="background:#eff6ff">📅</div>
      <div><div class="sp-stat-val"><?= count($schedules) ?></div><div class="sp-stat-lbl">Total Entries</div></div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-ico" style="background:#f0fdf4">🏫</div>
      <div><div class="sp-stat-val"><?= $totalSections ?></div><div class="sp-stat-lbl">Sections</div></div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-ico" style="background:#faf5ff">👤</div>
      <div><div class="sp-stat-val"><?= $totalProfs ?></div><div class="sp-stat-lbl">Professors</div></div>
    </div>
    <div class="sp-stat">
      <div class="sp-stat-ico" style="background:#fffbeb">🚪</div>
      <div><div class="sp-stat-val"><?= $totalRooms ?></div><div class="sp-stat-lbl">Rooms Used</div></div>
    </div>
  </div>

  <!-- Filters -->
  <div class="sp-filters no-print">
    <div class="sp-filters-head">
      <span class="sp-filters-title">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
        Filters
        <?php if ($activeFilters): ?><span class="active-badge"><?= $activeFilters ?> active</span><?php endif; ?>
      </span>
    </div>
    <form method="GET">
      <div class="sp-filter-grid">
        <div class="sp-fi">
          <label>Course</label>
          <select name="course_id" id="fc-course" onchange="fcFilterSections()">
            <option value="">All Courses</option>
            <?php foreach ($courses as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $fCourse==$c['id']?'selected':'' ?>><?= h($c['code']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sp-fi">
          <label>Section</label>
          <select name="section_id" id="fc-section">
            <option value="">All Sections</option>
            <?php foreach ($sections as $s): ?>
              <option value="<?= $s['id'] ?>" data-course="<?= $s['course_id'] ?>" <?= $fSection==$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sp-fi">
          <label>Semester</label>
          <select name="semester">
            <option value="">All Semesters</option>
            <option value="1st Semester" <?= $fSem==='1st Semester'?'selected':'' ?>>1st Semester</option>
            <option value="2nd Semester" <?= $fSem==='2nd Semester'?'selected':'' ?>>2nd Semester</option>
          </select>
        </div>
        <div class="sp-fi">
          <label>Professor</label>
          <select name="professor_id">
            <option value="">All Professors</option>
            <?php foreach ($professors as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $fProf==$p['id']?'selected':'' ?>><?= h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sp-fi">
          <label>Room</label>
          <select name="room_id">
            <option value="">All Rooms</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" <?= $fRoom==$r['id']?'selected':'' ?>><?= h($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sp-filter-btns">
          <button type="submit" class="btn-go">Apply</button>
          <a href="schedules.php" class="btn-clr">Clear</a>
        </div>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="sp-card">
    <div class="sp-card-head">
      <span class="sp-card-title">All Schedules</span>
      <span class="sp-card-count"><?= count($schedules) ?> entr<?= count($schedules)==1?'y':'ies' ?></span>
    </div>
    <?php if (empty($schedules)): ?>
      <div class="sp-empty">
        <span class="ei">📅</span>
        <p>No schedules found. <a href="#" onclick="openModal();return false">Add one now →</a></p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="sp-table">
          <thead>
            <tr>
              <th>Section</th><th>Subject</th><th>Semester</th><th>Day</th>
              <th>Time</th><th>Room</th><th>Professor</th>
              <th class="no-print" style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody id="sp-tbody">
          <?php foreach ($schedules as $sc):
            $dc  = $sc['day_code'];
            $clr = $dayColors[$dc] ?? '#64748b';
            $payload = htmlspecialchars(json_encode([
                'id'           => $sc['id'],
                'section_id'   => $sc['section_id'],
                'subject_id'   => $sc['subject_id'],
                'semester'     => $sc['semester'],
                'day_code'     => $dc,
                'start_time'   => $sc['start_time'],
                'end_time'     => $sc['end_time'],
                'room_id'      => $sc['room_id'],
                'professor_id' => $sc['professor_id'],
                'course_id'    => $sc['course_id'],
                'year_level'   => $sc['year_level'],
            ]), ENT_QUOTES);
          ?>
          <tr id="row-<?= $sc['id'] ?>">
            <td>
              <div class="cell-section-name"><?= h($sc['sec_name']) ?></div>
              <div class="cell-course-tag"><?= h($sc['c_code']) ?></div>
            </td>
            <td>
              <div><span class="sub-chip"><?= h($sc['sub_code']) ?></span></div>
              <div class="sub-name-cell"><?= h($sc['sub_name']) ?></div>
            </td>
            <td><span class="sem-tag <?= $sc['semester']==='1st Semester'?'sem1':'sem2' ?>"><?= $sc['semester']==='1st Semester'?'1st Sem':'2nd Sem' ?></span></td>
            <td>
              <div class="day-cell">
                <span class="day-pip" style="background:<?= $clr ?>"></span>
                <span class="day-txt" style="color:<?= $clr ?>"><?= dayFull($dc) ?></span>
              </div>
            </td>
            <td><span class="time-tag"><?= formatTimeRange($sc['start_time'],$sc['end_time']) ?></span></td>
            <td><div class="room-tag"><?= h($sc['rm_name']) ?><span class="room-type"><?= h($sc['rm_type']??'') ?></span></div></td>
            <td><span class="prof-name"><?= h($sc['pr_name']) ?></span></td>
            <td class="no-print">
              <div class="row-acts" style="justify-content:center">
                <button class="btn-row-edit" onclick="editSchedule(<?= $payload ?>)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                  Edit
                </button>
                <button class="btn-row-del" onclick="deleteSchedule(<?= $sc['id'] ?>,this)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ════════════════════════════════════
     MODAL
════════════════════════════════════ -->
<div id="sc-modal" role="dialog" aria-modal="true">
  <div class="sc-modal-box" id="sc-modal-box">

    <div class="sc-modal-hdr">
      <div class="sc-modal-hdr-l">
        <div class="sc-modal-ico">📅</div>
        <div>
          <div class="sc-modal-ttl" id="sc-modal-ttl">Add New Schedule</div>
          <div class="sc-modal-sub">Fill in all sections below — conflicts are checked automatically</div>
        </div>
      </div>
      <button type="button" class="sc-modal-cls" id="sc-cls">✕</button>
    </div>

    <div class="sc-modal-body">
      <form id="sc-form">
        <input type="hidden" id="sc-action" name="action" value="create">
        <input type="hidden" id="sc-id"     name="id"     value="">

        <!-- STEP 1: Class Assignment -->
        <div class="fsec">
          <div class="fsec-title">
            <span class="fsec-step">1</span>
            Class Assignment
          </div>
          <div class="fg2">
            <div>
              <label class="fl">Course <span class="req">*</span></label>
              <select id="sc-course" class="fc" onchange="modalFilterSections()">
                <option value="">— Select Course —</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= h($c['code']) ?> – <?= h($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="fl">Section <span class="req">*</span></label>
              <select id="sc-section" name="section_id" class="fc" required onchange="modalLoadSubjects()">
                <option value="">— Select Section —</option>
                <?php foreach ($sections as $s): ?>
                  <option value="<?= $s['id'] ?>" data-course="<?= $s['course_id'] ?>" data-year="<?= h($s['year_level']) ?>">
                    <?= h($s['name']) ?> (<?= h($s['year_level']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="fl">Semester <span class="req">*</span></label>
              <select id="sc-semester" name="semester" class="fc" required onchange="modalLoadSubjects()">
                <option value="1st Semester">1st Semester</option>
                <option value="2nd Semester">2nd Semester</option>
              </select>
            </div>
            <div>
              <label class="fl">Subject <span class="req">*</span></label>
              <select id="sc-subject" name="subject_id" class="fc" required>
                <option value="">— Select Subject —</option>
              </select>
            </div>
          </div>
        </div>

        <!-- STEP 2: Day & Time -->
        <div class="fsec">
          <div class="fsec-title">
            <span class="fsec-step">2</span>
            Day &amp; Time
          </div>
          <div style="margin-bottom:14px">
            <label class="fl">Day <span class="req">*</span></label>
            <input type="hidden" id="sc-day" name="day_code" value="M">
            <div class="day-selector" id="day-selector">
              <?php foreach ($days as $code => $label):
                $color = $dayColors[$code] ?? '#64748b';
              ?>
              <button type="button" class="day-btn <?= $code==='M'?'selected':'' ?>"
                      data-code="<?= $code ?>"
                      style="<?= $code==='M'?"background:{$color};border-color:{$color}":'' ?>"
                      onclick="selectDay('<?= $code ?>','<?= $color ?>')">
                <span class="day-dot-sm"></span>
                <?= $label ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="fg2">
            <div>
              <label class="fl">Start Time <span class="req">*</span></label>
              <input id="sc-start" class="fc" name="start_time" type="time" value="07:30" required oninput="updateTimePreview()">
            </div>
            <div>
              <label class="fl">End Time <span class="req">*</span></label>
              <input id="sc-end" class="fc" name="end_time" type="time" value="09:00" required oninput="updateTimePreview()">
            </div>
          </div>
          <div class="time-preview" id="time-preview">
            🕐 <strong id="time-preview-text"></strong>
          </div>
        </div>

        <!-- STEP 3: Room -->
        <div class="fsec">
          <div class="fsec-title">
            <span class="fsec-step">3</span>
            Room
          </div>
          <!-- Hidden select for form submission -->
          <select id="sc-room" name="room_id" class="picker-hidden" required>
            <option value=""></option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" data-building="<?= h($r['building']??'') ?>" data-type="<?= h($r['room_type']??'') ?>" data-cap="<?= h($r['capacity']??'') ?>"><?= h($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="picker-wrap" id="room-picker-wrap">
            <div class="picker-search-box" onclick="openPicker('room')">
              <span class="picker-search-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </span>
              <input type="text" class="picker-search-input" id="room-search" placeholder="Search rooms by name, building, or type…"
                     oninput="filterPicker('room', this.value)" onfocus="openPicker('room')" autocomplete="off">
            </div>
            <div class="picker-dropdown" id="room-dropdown">
              <?php
              $roomsByBuilding = [];
              foreach ($rooms as $r) {
                  $b = $r['building'] ?? 'Other';
                  $roomsByBuilding[$b][] = $r;
              }
              foreach ($roomsByBuilding as $building => $bRooms): ?>
                <div class="picker-option" style="background:var(--hover,#f8fafc);pointer-events:none;padding:7px 14px">
                  <span style="font-size:.65rem;font-weight:900;text-transform:uppercase;letter-spacing:.6px;color:var(--text3,#94a3b8)"><?= h($building) ?></span>
                </div>
                <?php foreach ($bRooms as $r): ?>
                <div class="picker-option" data-id="<?= $r['id'] ?>"
                     data-name="<?= h($r['name']) ?>"
                     data-search="<?= strtolower(h($r['name'].' '.$r['building'].' '.$r['room_type'])) ?>"
                     onclick="selectPickerOption('room', <?= $r['id'] ?>, '<?= h($r['name']) ?>', '<?= h($r['room_type']??'') ?>·<?= h($r['building']??'') ?>·Cap: <?= h($r['capacity']??'') ?>')">
                  <div class="picker-option-icon" style="background:#fff1f2">🚪</div>
                  <div>
                    <div class="picker-option-main"><?= h($r['name']) ?></div>
                    <div class="picker-option-sub"><?= h($r['room_type']??'') ?> · <?= h($r['building']??'') ?> · Cap: <?= h($r['capacity']??'') ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <div class="picker-no-results" id="room-no-results" style="display:none">No rooms found</div>
            </div>
            <div class="picker-selected-badge" id="room-selected-badge">
              <span>🚪</span>
              <span id="room-selected-text"></span>
              <span class="picker-selected-badge-clear" onclick="clearPicker('room')" title="Clear selection">✕</span>
            </div>
          </div>
        </div>

        <!-- STEP 4: Professor -->
        <div class="fsec">
          <div class="fsec-title">
            <span class="fsec-step">4</span>
            Instructor
          </div>
          <!-- Hidden select for form submission -->
          <select id="sc-prof" name="professor_id" class="picker-hidden" required>
            <option value=""></option>
            <?php foreach ($professors as $p): ?>
              <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="picker-wrap" id="prof-picker-wrap">
            <div class="picker-search-box" onclick="openPicker('prof')">
              <span class="picker-search-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </span>
              <input type="text" class="picker-search-input" id="prof-search" placeholder="Search professors by name or department…"
                     oninput="filterPicker('prof', this.value)" onfocus="openPicker('prof')" autocomplete="off">
            </div>
            <div class="picker-dropdown" id="prof-dropdown">
              <?php foreach ($professors as $p):
                $isFT = ($p['employment_type']??'Full Time') === 'Full Time';
                $words = explode(' ', $p['name']);
                $initials = strtoupper(substr($words[0],0,1).(isset($words[1])?substr($words[1],0,1):''));
              ?>
              <div class="picker-option" data-id="<?= $p['id'] ?>"
                   data-name="<?= h($p['name']) ?>"
                   data-search="<?= strtolower(h($p['name'].' '.($p['department']??''))) ?>"
                   onclick="selectPickerOption('prof', <?= $p['id'] ?>, '<?= h($p['name']) ?>', '<?= h($p['department']??'') ?> · <?= $isFT?'Full Time':'Part Time' ?>')">
                <div class="picker-option-icon" style="background:<?= $isFT?'#f0fdf4':'#fff7ed' ?>;color:<?= $isFT?'#166534':'#c2410c' ?>;font-size:.72rem;font-weight:900;font-family:monospace"><?= $initials ?></div>
                <div>
                  <div class="picker-option-main"><?= h($p['name']) ?></div>
                  <div class="picker-option-sub">
                    <?= h($p['department']??'') ?>
                    <span style="color:<?= $isFT?'#10b981':'#f59e0b' ?>;font-weight:700">· <?= $isFT?'🟢 Full Time':'🟠 Part Time' ?></span>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
              <div class="picker-no-results" id="prof-no-results" style="display:none">No professors found</div>
            </div>
            <div class="picker-selected-badge" id="prof-selected-badge">
              <span>👨‍🏫</span>
              <span id="prof-selected-text"></span>
              <span class="picker-selected-badge-clear" onclick="clearPicker('prof')" title="Clear selection">✕</span>
            </div>
          </div>
        </div>

        <!-- Notice box -->
        <div class="modal-notice" id="sc-notice">
          <div class="mn-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span id="sc-notice-heading">Issue detected</span>
          </div>
          <ul class="mn-list" id="sc-notice-list"></ul>
        </div>

        <div class="sc-modal-ftr">
          <button type="submit" class="btn-save-sc" id="sc-save-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Schedule
          </button>
          <button type="button" class="btn-cncl-sc" id="sc-cncl-btn">Cancel</button>
          <span class="auto-check-note">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Conflicts &amp; duplicates checked on save
          </span>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ════════════════════════════════════════
   DATA
════════════════════════════════════════ */
const allSections = <?= json_encode(array_values(array_map(fn($s)=>[
    'id'=>$s['id'],'name'=>$s['name'],'year_level'=>$s['year_level'],'course_id'=>$s['course_id']
], $sections))) ?>;

const dayColors = <?= json_encode($dayColors) ?>;

/* ════════════════════════════════════════
   TOAST
════════════════════════════════════════ */
function toast(msg, type) {
    const w = document.getElementById('sp-toasts');
    const t = document.createElement('div');
    t.className = 'sp-toast '+(type==='ok'?'ok':'err');
    t.innerHTML = (type==='ok'?'✅ ':'❌ ')+msg;
    w.appendChild(t);
    setTimeout(()=>{ t.style.animation='tOut .3s ease forwards'; setTimeout(()=>t.remove(),300); }, 3500);
}

/* ════════════════════════════════════════
   DAY SELECTOR
════════════════════════════════════════ */
function selectDay(code, color) {
    document.getElementById('sc-day').value = code;
    document.querySelectorAll('.day-btn').forEach(b => {
        if (b.dataset.code === code) {
            b.classList.add('selected');
            b.style.background = color;
            b.style.borderColor = color;
            b.style.color = '#fff';
        } else {
            b.classList.remove('selected');
            b.style.background = '';
            b.style.borderColor = '';
            b.style.color = '';
        }
    });
}

/* ════════════════════════════════════════
   TIME PREVIEW
════════════════════════════════════════ */
function updateTimePreview() {
    const s = document.getElementById('sc-start').value;
    const e = document.getElementById('sc-end').value;
    const el = document.getElementById('time-preview');
    const txt = document.getElementById('time-preview-text');
    if (s && e) {
        const fmt = t => {
            const [h,m] = t.split(':').map(Number);
            const ampm = h >= 12 ? 'PM' : 'AM';
            const hr = h % 12 || 12;
            return `${hr}:${String(m).padStart(2,'0')} ${ampm}`;
        };
        txt.textContent = fmt(s) + ' → ' + fmt(e);
        el.classList.add('show');
    } else {
        el.classList.remove('show');
    }
}

/* ════════════════════════════════════════
   SEARCHABLE PICKER (Room & Professor)
════════════════════════════════════════ */
let activePicker = null;

function openPicker(type) {
    closePicker(activePicker);
    activePicker = type;
    document.getElementById(type+'-dropdown').classList.add('open');
}

function closePicker(type) {
    if (!type) return;
    document.getElementById(type+'-dropdown').classList.remove('open');
    if (activePicker === type) activePicker = null;
}

function filterPicker(type, query) {
    const q = query.trim().toLowerCase();
    const options = document.querySelectorAll('#'+type+'-dropdown .picker-option[data-id]');
    const groups  = document.querySelectorAll('#'+type+'-dropdown .picker-option:not([data-id])');
    let visible = 0;
    options.forEach(o => {
        const match = !q || (o.dataset.search||'').includes(q);
        o.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    groups.forEach(g => { g.style.display = q ? 'none' : ''; });
    const nr = document.getElementById(type+'-no-results');
    if (nr) nr.style.display = visible === 0 ? 'block' : 'none';
    openPicker(type);
}

function selectPickerOption(type, id, name, sub) {
    // Set hidden select value
    document.getElementById('sc-'+type === 'sc-prof' ? 'sc-prof' : 'sc-room').value = id;
    const selectId = type === 'room' ? 'sc-room' : 'sc-prof';
    document.getElementById(selectId).value = id;

    // Mark selected in dropdown
    document.querySelectorAll('#'+type+'-dropdown .picker-option[data-id]').forEach(o => {
        o.classList.toggle('selected', o.dataset.id == id);
    });

    // Show badge
    document.getElementById(type+'-selected-text').textContent = name + (sub ? ' — ' + sub : '');
    document.getElementById(type+'-selected-badge').classList.add('show');

    // Update search input
    document.getElementById(type+'-search').value = name;

    closePicker(type);
}

function clearPicker(type) {
    const selectId = type === 'room' ? 'sc-room' : 'sc-prof';
    document.getElementById(selectId).value = '';
    document.getElementById(type+'-search').value = '';
    document.getElementById(type+'-selected-badge').classList.remove('show');
    document.querySelectorAll('#'+type+'-dropdown .picker-option[data-id]').forEach(o => o.classList.remove('selected'));
    filterPicker(type, '');
}

// Close picker on outside click
document.addEventListener('click', function(e) {
    if (activePicker && !e.target.closest('#'+activePicker+'-picker-wrap')) {
        closePicker(activePicker);
    }
});

/* ════════════════════════════════════════
   NOTICE BOX
════════════════════════════════════════ */
function clearNotice() {
    const el = document.getElementById('sc-notice');
    el.classList.remove('show','type-conflict','type-duplicate');
    document.getElementById('sc-notice-list').innerHTML = '';
}
function showConflictNotice(conflicts) {
    const el = document.getElementById('sc-notice');
    const list = document.getElementById('sc-notice-list');
    const heading = document.getElementById('sc-notice-heading');
    list.innerHTML = '';
    heading.textContent = 'Conflicts Detected — adjust and try again';
    el.className = 'modal-notice show type-conflict';
    const typeMap = {room:'mc-room',professor:'mc-prof',section:'mc-sec'};
    conflicts.forEach(c => {
        const li = document.createElement('li');
        li.className = 'mn-item';
        const cls = typeMap[c.type]||'mc-room';
        const lbl = (c.type||'').charAt(0).toUpperCase()+(c.type||'').slice(1);
        li.innerHTML = `<span class="mc-badge ${cls}">${lbl}</span><span>${c.msg}</span>`;
        list.appendChild(li);
    });
}
function showDuplicateNotice(msg) {
    const el = document.getElementById('sc-notice');
    const list = document.getElementById('sc-notice-list');
    const heading = document.getElementById('sc-notice-heading');
    list.innerHTML = '';
    heading.textContent = 'Duplicate Subject — already scheduled for this section';
    el.className = 'modal-notice show type-duplicate';
    const li = document.createElement('li');
    li.className = 'mn-item';
    li.innerHTML = `<span class="mc-badge mc-dup">Duplicate</span><span>${msg}</span>`;
    list.appendChild(li);
}

/* ════════════════════════════════════════
   MODAL OPEN / CLOSE
════════════════════════════════════════ */
const scModal = document.getElementById('sc-modal');
const scBox   = document.getElementById('sc-modal-box');
const scTtl   = document.getElementById('sc-modal-ttl');

function openModal(title) {
    scTtl.textContent = title || 'Add New Schedule';
    scModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    updateTimePreview();
}
function closeModal() {
    scModal.classList.remove('open');
    document.body.style.overflow = '';
    resetModal();
}
function resetModal() {
    document.getElementById('sc-action').value   = 'create';
    document.getElementById('sc-id').value       = '';
    document.getElementById('sc-course').value   = '';
    document.getElementById('sc-semester').value = '1st Semester';
    document.getElementById('sc-start').value    = '07:30';
    document.getElementById('sc-end').value      = '09:00';
    document.getElementById('sc-subject').innerHTML = '<option value="">— Select Subject —</option>';
    selectDay('M', dayColors['M']);
    clearPicker('room');
    clearPicker('prof');
    clearNotice();
    scTtl.textContent = 'Add New Schedule';
    modalFilterSections();
    updateTimePreview();
}

document.getElementById('sc-cls').addEventListener('click',      e=>{ e.preventDefault(); e.stopPropagation(); closeModal(); });
document.getElementById('sc-cncl-btn').addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); closeModal(); });
scModal.addEventListener('click', e=>{ if(e.target===scModal) closeModal(); });
scBox.addEventListener('click',   e=>e.stopPropagation());
document.addEventListener('keydown', e=>{ if(e.key==='Escape'&&scModal.classList.contains('open')) closeModal(); });

/* ════════════════════════════════════════
   SECTION / SUBJECT LOADING
════════════════════════════════════════ */
function modalFilterSections(preSelectId) {
    const cid  = document.getElementById('sc-course').value;
    const sel  = document.getElementById('sc-section');
    const prev = preSelectId || sel.value;
    sel.innerHTML = '<option value="">— Select Section —</option>';
    allSections.filter(s=>!cid||s.course_id==cid).forEach(s=>{
        const o = document.createElement('option');
        o.value=s.id; o.dataset.course=s.course_id; o.dataset.year=s.year_level;
        o.textContent=s.name+' ('+s.year_level+')';
        if(s.id==prev) o.selected=true;
        sel.appendChild(o);
    });
}

function modalLoadSubjects() {
    const secSel = document.getElementById('sc-section');
    const opt    = secSel.options[secSel.selectedIndex];
    if (!opt||!opt.value) return;
    const cid    = document.getElementById('sc-course').value||opt.dataset.course;
    const year   = opt.dataset.year;
    const sem    = document.getElementById('sc-semester').value;
    const secId  = opt.value;
    const editId = document.getElementById('sc-id').value;
    if (!cid||!year||!sem) return;
    loadSubjectsFor(cid, year, sem, null, secId, editId);
}

function loadSubjectsFor(courseId, yearLevel, semester, selectId, sectionId, editId) {
    if (!courseId||!yearLevel||!semester) {
        document.getElementById('sc-subject').innerHTML='<option value="">— Select Subject —</option>'; return;
    }
    const sel = document.getElementById('sc-subject');
    sel.innerHTML = '<option value="">Loading…</option>';
    let url = 'ajax_subjects.php?course_id='+encodeURIComponent(courseId)+'&year_level='+encodeURIComponent(yearLevel)+'&semester='+encodeURIComponent(semester);
    if (sectionId) url += '&section_id='+encodeURIComponent(sectionId);
    if (editId)    url += '&edit_id='+encodeURIComponent(editId);
    fetch(url).then(r=>r.json()).then(data=>{
        sel.innerHTML = '<option value="">— Select Subject —</option>';
        if (!data.length) { sel.innerHTML='<option value="">All subjects already scheduled</option>'; return; }
        data.forEach(s=>{ const o=document.createElement('option'); o.value=s.id; o.textContent=s.code+' – '+s.name; if(selectId&&s.id==selectId) o.selected=true; sel.appendChild(o); });
    }).catch(()=>{ sel.innerHTML='<option value="">Error loading subjects</option>'; });
}

/* ════════════════════════════════════════
   EDIT
════════════════════════════════════════ */
function editSchedule(sc) {
    clearNotice();
    document.getElementById('sc-action').value   = 'update';
    document.getElementById('sc-id').value       = sc.id;
    document.getElementById('sc-course').value   = sc.course_id;
    document.getElementById('sc-semester').value = sc.semester;
    document.getElementById('sc-start').value    = sc.start_time?sc.start_time.substring(0,5):'';
    document.getElementById('sc-end').value      = sc.end_time?sc.end_time.substring(0,5):'';
    modalFilterSections(sc.section_id);
    loadSubjectsFor(sc.course_id, sc.year_level, sc.semester, sc.subject_id, sc.section_id, sc.id);
    selectDay(sc.day_code, dayColors[sc.day_code]||'#64748b');
    updateTimePreview();

    // Pre-fill room picker
    const roomOpt = document.querySelector('#sc-room option[value="'+sc.room_id+'"]');
    if (roomOpt) {
        selectPickerOption('room', sc.room_id, roomOpt.textContent.trim(), '');
        // Find richer sub from dropdown
        const dropOpt = document.querySelector('#room-dropdown .picker-option[data-id="'+sc.room_id+'"]');
        if (dropOpt) {
            const sub = dropOpt.querySelector('.picker-option-sub');
            document.getElementById('room-selected-text').textContent = roomOpt.textContent.trim() + (sub?' — '+sub.textContent.trim():'');
        }
    }

    // Pre-fill professor picker
    const profOpt = document.querySelector('#sc-prof option[value="'+sc.professor_id+'"]');
    if (profOpt) {
        selectPickerOption('prof', sc.professor_id, profOpt.textContent.trim(), '');
        const dropOpt = document.querySelector('#prof-dropdown .picker-option[data-id="'+sc.professor_id+'"]');
        if (dropOpt) {
            const sub = dropOpt.querySelector('.picker-option-sub');
            document.getElementById('prof-selected-text').textContent = profOpt.textContent.trim() + (sub?' — '+sub.textContent.trim():'');
        }
    }

    openModal('Edit Schedule');
}

/* ════════════════════════════════════════
   AJAX SAVE
════════════════════════════════════════ */
document.getElementById('sc-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    clearNotice();
    const btn = document.getElementById('sc-save-btn');
    btn.classList.add('busy');
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-6.49"/></svg> Saving…';
    try {
        const res  = await fetch('schedules.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(this)});
        const json = await res.json();
        if (json.ok) {
            toast(json.msg,'ok'); closeModal(); setTimeout(()=>location.reload(),800);
        } else if (json.conflicts&&json.conflicts.length) {
            showConflictNotice(json.conflicts);
        } else if (json.duplicate) {
            showDuplicateNotice(json.msg);
        } else {
            toast(json.msg||'An error occurred.','err');
        }
    } catch(err) { toast('Network error. Please try again.','err'); }
    finally {
        btn.classList.remove('busy');
        btn.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Schedule';
    }
});

/* ════════════════════════════════════════
   AJAX DELETE
════════════════════════════════════════ */
async function deleteSchedule(id, btn) {
    if (!confirm('Delete this schedule entry? This cannot be undone.')) return;
    btn.classList.add('deleting');
    const data = new FormData(); data.append('action','delete'); data.append('id',id);
    try {
        const res  = await fetch('schedules.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:data});
        const json = await res.json();
        if (json.ok) {
            const row=document.getElementById('row-'+id);
            if(row){row.style.transition='opacity .25s';row.style.opacity='0';setTimeout(()=>row.remove(),250);}
            toast(json.msg,'ok');
        } else { toast(json.msg||'Delete failed.','err'); btn.classList.remove('deleting'); }
    } catch { toast('Network error.','err'); btn.classList.remove('deleting'); }
}

/* ════════════════════════════════════════
   FILTER BAR section cascade
════════════════════════════════════════ */
function fcFilterSections() {
    const cid = document.getElementById('fc-course').value;
    const sel = document.getElementById('fc-section');
    const prev = <?= $fSection ?>;
    sel.innerHTML='<option value="">All Sections</option>';
    allSections.filter(s=>!cid||s.course_id==cid).forEach(s=>{
        const o=document.createElement('option');
        o.value=s.id; o.textContent=s.name; if(s.id==prev) o.selected=true; sel.appendChild(o);
    });
}
fcFilterSections();

const styleEl = document.createElement('style');
styleEl.textContent='@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}';
document.head.appendChild(styleEl);

// Init time preview
updateTimePreview();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>