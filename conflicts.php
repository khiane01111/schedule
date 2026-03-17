<?php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDB();

/* ══════════════════════════════════════════════════════
   INLINE SCHEDULE UPDATE (AJAX POST from edit modal)
══════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $id        = (int)($_POST['id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $semester  = sanitize($_POST['semester']   ?? '');
    $dayCode   = sanitize($_POST['day_code']   ?? '');
    $startTime = sanitize($_POST['start_time'] ?? '');
    $endTime   = sanitize($_POST['end_time']   ?? '');
    $roomId    = (int)($_POST['room_id']    ?? 0);
    $profId    = (int)($_POST['professor_id'] ?? 0);

    if (!$id || !$sectionId || !$subjectId || !$semester || !$dayCode || !$startTime || !$endTime || !$roomId || !$profId) {
        echo json_encode(['ok' => false, 'msg' => 'All fields are required.']);
        exit;
    }
    if (strtotime($startTime) >= strtotime($endTime)) {
        echo json_encode(['ok' => false, 'msg' => 'End time must be after start time.']);
        exit;
    }

    // Conflict check before saving
    $sql = "SELECT s.id, sec.name AS sec_name, sub.code AS sub_code,
                   r.name AS rm_name, p.name AS pr_name,
                   s.room_id, s.professor_id, s.section_id,
                   s.start_time, s.end_time
            FROM schedules s
            JOIN sections  sec ON sec.id = s.section_id
            JOIN subjects  sub ON sub.id = s.subject_id
            JOIN rooms     r   ON r.id   = s.room_id
            JOIN professors p  ON p.id   = s.professor_id
            WHERE s.day_code = ?
              AND TIME(s.start_time) < TIME(?)
              AND TIME(s.end_time)   > TIME(?)
              AND s.id != ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$dayCode, $endTime, $startTime, $id]);
    $overlapping = $stmt->fetchAll();

    $conflicts = [];
    foreach ($overlapping as $o) {
        if ($o['room_id']      == $roomId)    $conflicts[] = "Room conflict with <strong>{$o['sub_code']}</strong> ({$o['sec_name']}) " . formatTimeRange($o['start_time'], $o['end_time']);
        if ($o['professor_id'] == $profId)    $conflicts[] = "Professor conflict with <strong>{$o['sub_code']}</strong> ({$o['sec_name']}) " . formatTimeRange($o['start_time'], $o['end_time']);
        if ($o['section_id']   == $sectionId) $conflicts[] = "Section conflict with <strong>{$o['sub_code']}</strong> " . formatTimeRange($o['start_time'], $o['end_time']);
    }
    if (!empty($conflicts)) {
        echo json_encode(['ok' => false, 'msg' => 'Still conflicts: ' . implode(' · ', $conflicts), 'conflicts' => $conflicts]);
        exit;
    }

    try {
        $db->prepare("UPDATE schedules SET section_id=?,subject_id=?,semester=?,day_code=?,start_time=?,end_time=?,room_id=?,professor_id=? WHERE id=?")
           ->execute([$sectionId,$subjectId,$semester,$dayCode,$startTime,$endTime,$roomId,$profId,$id]);
        echo json_encode(['ok' => true, 'msg' => 'Schedule #' . $id . ' updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════
   PAGE LOAD — Run conflict scan
══════════════════════════════════════════════════════ */
$pageTitle = 'Conflict Checker';
require_once __DIR__ . '/includes/header.php';

$sections   = $db->query("SELECT s.*, c.code AS c_code, c.id AS course_id FROM sections s JOIN courses c ON c.id=s.course_id ORDER BY s.year_level, s.name")->fetchAll();
$rooms      = $db->query("SELECT * FROM rooms ORDER BY name")->fetchAll();
$professors = $db->query("SELECT id,name FROM professors ORDER BY name")->fetchAll();
$courses    = $db->query("SELECT id,code,name FROM courses ORDER BY code")->fetchAll();

$days = ['M'=>'Monday','T'=>'Tuesday','W'=>'Wednesday','Th'=>'Thursday','F'=>'Friday','Sa'=>'Saturday'];
$dayColors = ['M'=>'#3b82f6','T'=>'#10b981','W'=>'#6366f1','Th'=>'#f59e0b','F'=>'#ef4444','Sa'=>'#8b5cf6'];

$conflicts      = [];
$ran            = false;
$totalSchedules = (int)$db->query("SELECT COUNT(*) FROM schedules")->fetchColumn();

if (isset($_GET['run']) || isset($_GET['rerun'])) {
    $ran = true;
    $allSchedules = $db->query("
        SELECT sc.*, sec.name AS sec_name, sub.code AS sub_code, sub.name AS sub_name,
               r.name AS rm_name, p.name AS pr_name, c.code AS c_code,
               sec.course_id, sec.year_level
        FROM schedules sc
        JOIN sections  sec ON sec.id = sc.section_id
        JOIN courses   c   ON c.id  = sec.course_id
        JOIN subjects  sub ON sub.id = sc.subject_id
        JOIN rooms     r   ON r.id   = sc.room_id
        JOIN professors p  ON p.id   = sc.professor_id
        ORDER BY sc.day_code, sc.start_time
    ")->fetchAll();

    $n = count($allSchedules);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $a = $allSchedules[$i];
            $b = $allSchedules[$j];
            if ($a['day_code'] !== $b['day_code']) continue;
            $aS = timeToMinutes(substr($a['start_time'],0,5));
            $aE = timeToMinutes(substr($a['end_time'],0,5));
            $bS = timeToMinutes(substr($b['start_time'],0,5));
            $bE = timeToMinutes(substr($b['end_time'],0,5));
            if (!($aS < $bE && $aE > $bS)) continue;

            $day = dayFull($a['day_code']);
            if ($a['room_id'] == $b['room_id']) {
                $conflicts['Room'][] = ['type'=>'Room','icon'=>'🏫','color'=>'#ef4444','bg'=>'#fff1f2','border'=>'#fecaca',
                    'msg' => "Room <strong>{$a['rm_name']}</strong> double-booked on <strong>$day</strong>",
                    'a' => $a, 'b' => $b];
            }
            if ($a['professor_id'] == $b['professor_id']) {
                $conflicts['Professor'][] = ['type'=>'Professor','icon'=>'👨‍🏫','color'=>'#f59e0b','bg'=>'#fffbeb','border'=>'#fde68a',
                    'msg' => "Prof. <strong>{$a['pr_name']}</strong> has overlapping classes on <strong>$day</strong>",
                    'a' => $a, 'b' => $b];
            }
            if ($a['section_id'] == $b['section_id']) {
                $conflicts['Section'][] = ['type'=>'Section','icon'=>'👥','color'=>'#8b5cf6','bg'=>'#faf5ff','border'=>'#e9d5ff',
                    'msg' => "Section <strong>{$a['sec_name']}</strong> has overlapping subjects on <strong>$day</strong>",
                    'a' => $a, 'b' => $b];
            }
        }
    }
    // Deduplicate by id pair
    foreach ($conflicts as &$group) {
        $seen = [];
        $group = array_values(array_filter($group, function($item) use (&$seen) {
            $key = min($item['a']['id'],$item['b']['id']).'-'.max($item['a']['id'],$item['b']['id']);
            if (isset($seen[$key])) return false;
            $seen[$key] = true;
            return true;
        }));
    }
    unset($group);
}

$totalConflicts = array_sum(array_map('count', $conflicts));

// Build a quick lookup of all schedules by ID for the edit modal
$scheduleById = [];
if ($ran) {
    foreach ($allSchedules ?? [] as $sc) { $scheduleById[$sc['id']] = $sc; }
}
?>

<style>
.cf-page { font-family: 'Segoe UI', system-ui, sans-serif; }

/* Header */
.cf-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; gap: 16px; flex-wrap: wrap;
}
.cf-header h2 { font-size: 1.55rem; font-weight: 700; color: var(--text,#111); margin: 0 0 3px; letter-spacing: -.3px; }
.cf-header p  { margin: 0; color: var(--text3,#888); font-size: .875rem; }

/* Summary strip */
.cf-summary {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 22px;
}
.cf-stat {
    background: var(--card-bg,#fff);
    border: 1.5px solid var(--border,#e8e8e8);
    border-radius: 12px; padding: 14px 18px;
    display: flex; align-items: center; gap: 12px;
    transition: box-shadow .15s;
}
.cf-stat:hover { box-shadow: 0 4px 16px rgba(0,0,0,.07); }
.cf-stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.cf-stat-num  { font-size: 1.35rem; font-weight: 800; color: var(--text,#111); line-height: 1; }
.cf-stat-lbl  { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--text3,#999); margin-top: 2px; }

/* Scan result banner */
.result-banner {
    border-radius: 12px; padding: 16px 20px;
    margin-bottom: 20px; display: flex; align-items: center; gap: 14px;
}
.result-banner.ok    { background: #f0fdf4; border: 1.5px solid #bbf7d0; }
.result-banner.warn  { background: #fef2f2; border: 1.5px solid #fecaca; }
.result-icon { font-size: 1.8rem; flex-shrink: 0; }
.result-title { font-size: .95rem; font-weight: 700; margin-bottom: 2px; }
.result-sub   { font-size: .8rem; color: var(--text3,#888); }

/* Conflict type section */
.cf-section { margin-bottom: 20px; }
.cf-section-head {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 12px; padding-bottom: 10px;
    border-bottom: 1.5px solid var(--border,#e8e8e8);
}
.cf-type-badge {
    font-size: .72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .6px; padding: 4px 12px; border-radius: 20px;
}
.cf-section-count { font-size: .8rem; color: var(--text3,#999); font-weight: 600; }
.cf-section-count span { font-weight: 800; }

/* Individual conflict card */
.cf-card {
    border-radius: 11px; border: 1.5px solid;
    margin-bottom: 12px; overflow: hidden;
}
.cf-card-head {
    padding: 12px 16px; display: flex; align-items: center;
    justify-content: space-between; gap: 10px; flex-wrap: wrap;
}
.cf-card-icon-msg { display: flex; align-items: center; gap: 10px; }
.cf-card-icon { font-size: 1.1rem; flex-shrink: 0; }
.cf-card-msg  { font-size: .875rem; line-height: 1.4; }
.cf-entries {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 0; border-top: 1px solid;
}
.cf-entry {
    padding: 12px 16px; border-right: 1px solid;
    background: var(--card-bg,#fff);
}
.cf-entry:last-child { border-right: none; }
.cf-entry-label {
    font-size: .68rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text3,#aaa); margin-bottom: 6px;
}
.cf-entry-code {
    display: inline-block; background: #eff6ff; color: #1d4ed8;
    border: 1px solid #bfdbfe; border-radius: 5px;
    font-size: .73rem; font-weight: 800; padding: 2px 8px;
    letter-spacing: .3px; margin-bottom: 4px;
}
.cf-entry-name   { font-size: .83rem; font-weight: 600; color: var(--text,#111); }
.cf-entry-meta   { font-size: .75rem; color: var(--text3,#777); margin-top: 3px; display: flex; flex-direction: column; gap: 2px; }
.cf-entry-time   {
    font-family: 'DM Mono','Courier New',monospace; font-size: .75rem;
    background: var(--hover,#f5f5f5); padding: 2px 7px; border-radius: 5px;
    display: inline-block; margin-top: 4px;
}
.btn-fix {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 8px; font-size: .78rem; font-weight: 700;
    background: #1e3a8a; color: #fff; border: none; cursor: pointer;
    transition: background .15s, transform .15s; white-space: nowrap; flex-shrink: 0;
}
.btn-fix:hover { background: #1e40af; transform: translateY(-1px); }

/* Prompt / clean state */
.cf-prompt {
    text-align: center; padding: 52px 24px;
    background: var(--card-bg,#fff);
    border: 1.5px solid var(--border,#e8e8e8);
    border-radius: 14px;
}
.cf-prompt .icon { font-size: 3rem; display: block; margin-bottom: 14px; }
.cf-prompt h3 { font-size: 1rem; font-weight: 700; color: var(--text,#111); margin: 0 0 8px; }
.cf-prompt p  { font-size: .875rem; color: var(--text3,#888); margin: 0; }
.btn-scan {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px; border-radius: 10px; font-size: .9rem; font-weight: 700;
    background: #1e3a8a; color: #fff; text-decoration: none;
    transition: background .15s, transform .15s; margin-top: 18px;
}
.btn-scan:hover { background: #1e40af; transform: translateY(-2px); }

/* Tip strip */
.cf-tip {
    background: #eff6ff; border: 1.5px solid #bfdbfe;
    border-radius: 10px; padding: 12px 16px;
    font-size: .8rem; color: #1e40af;
    display: flex; align-items: flex-start; gap: 8px; margin-top: 16px;
}
.cf-tip svg { flex-shrink: 0; margin-top: 1px; }

/* ── Edit Modal ─────────────────────────────────────── */
#edit-modal {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 500;
    align-items: center; justify-content: center; padding: 20px;
}
#edit-modal.active { display: flex; }
.em-box {
    background: var(--card-bg,#fff); border-radius: 14px;
    width: 100%; max-width: 680px; max-height: 92vh; overflow-y: auto;
    box-shadow: 0 24px 60px rgba(0,0,0,.2);
    animation: mIn .22s ease; position: relative;
}
@keyframes mIn {
    from { transform: translateY(16px) scale(.97); opacity: 0; }
    to   { transform: translateY(0)    scale(1);   opacity: 1; }
}
.em-hdr {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px 14px; border-bottom: 1px solid var(--border,#e8e8e8);
    position: sticky; top: 0; background: var(--card-bg,#fff);
    z-index: 1; border-radius: 14px 14px 0 0;
}
.em-hdr-left { display: flex; align-items: center; gap: 10px; }
.em-icon { width: 34px; height: 34px; border-radius: 8px; background: #eff6ff; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
.em-title { font-size: .95rem; font-weight: 700; color: var(--text,#111); }
.em-sub   { font-size: .75rem; color: var(--text3,#999); margin-top: 1px; }
.em-close {
    background: none; border: none; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.05rem; color: var(--text3,#888); cursor: pointer;
    border-radius: 8px; flex-shrink: 0; transition: background .15s, color .15s;
}
.em-close:hover { background: var(--hover,#f0f0f0); color: var(--text,#111); }
.em-body { padding: 20px 22px 24px; }

.em-context {
    background: var(--hover,#f7f7f7); border-radius: 9px;
    padding: 11px 14px; margin-bottom: 18px;
    font-size: .8rem; color: var(--text2,#555);
    display: flex; flex-wrap: wrap; gap: 10px;
}
.em-ctx-item { display: flex; align-items: center; gap: 5px; }
.em-ctx-label { font-weight: 700; color: var(--text3,#aaa); font-size: .7rem; text-transform: uppercase; letter-spacing: .3px; }

.fg2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
.fg3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px 16px; }
.fl  { display: block; font-size: .75rem; font-weight: 600; color: var(--text2,#555); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .3px; }
.fl .req { color: #e53e3e; margin-left: 2px; }
.fc {
    width: 100%; padding: 9px 12px;
    border: 1.5px solid var(--border,#ddd); border-radius: 8px;
    font-size: .875rem; color: var(--text,#111); background: var(--input-bg,#fafafa);
    transition: border-color .15s, box-shadow .15s; box-sizing: border-box;
}
.fc:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); background: #fff; }

.em-actions {
    display: flex; align-items: center; gap: 10px; margin-top: 18px;
    padding-top: 16px; border-top: 1px solid var(--border,#e8e8e8); flex-wrap: wrap;
}
.btn-save-em { min-width: 130px; }
.btn-save-em.loading { opacity: .7; pointer-events: none; }

/* Save conflict notice inside modal */
.em-conflict-notice {
    background: #fef2f2; border: 1.5px solid #fecaca;
    border-radius: 9px; padding: 10px 14px;
    font-size: .8rem; color: #991b1b; margin-top: 12px;
    display: none;
}
.em-conflict-notice.visible { display: block; }

/* Toast */
#toast-wrap { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
.toast {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 18px; border-radius: 10px; font-size: .875rem; font-weight: 600;
    box-shadow: 0 8px 28px rgba(0,0,0,.14); animation: tIn .25s ease;
    pointer-events: auto; max-width: 360px;
}
.toast.success { background: #f0fdf4; color: #166534; border: 1.5px solid #bbf7d0; }
.toast.error   { background: #fef2f2; color: #991b1b; border: 1.5px solid #fecaca; }
@keyframes tIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes tOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }

@media(max-width:860px) { .cf-summary{grid-template-columns:1fr 1fr} }
@media(max-width:600px) {
    .cf-entries{grid-template-columns:1fr}
    .cf-entry{border-right:none;border-bottom:1px solid}
    .cf-entry:last-child{border-bottom:none}
    .fg2,.fg3{grid-template-columns:1fr}
}
</style>

<!-- Toast container -->
<div id="toast-wrap"></div>

<div class="cf-page">

  <!-- Header -->
  <div class="cf-header">
    <div>
      <h2>Conflict Checker</h2>
      <p>Detect and resolve scheduling conflicts — room, professor, and section overlaps</p>
    </div>
    <a href="conflicts.php?run=1" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:5px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <?= $ran ? 'Re-run Check' : 'Run Conflict Check' ?>
    </a>
  </div>

  <!-- Summary Stats -->
  <div class="cf-summary">
    <div class="cf-stat">
      <div class="cf-stat-icon" style="background:#eff6ff">📅</div>
      <div><div class="cf-stat-num"><?= $totalSchedules ?></div><div class="cf-stat-lbl">Total Schedules</div></div>
    </div>
    <div class="cf-stat">
      <div class="cf-stat-icon" style="background:<?= $totalConflicts>0?'#fff1f2':'#f0fdf4' ?>">
        <?= $totalConflicts>0 ? '⚠️' : '✅' ?>
      </div>
      <div><div class="cf-stat-num" style="color:<?= $totalConflicts>0?'#dc2626':'#16a34a' ?>"><?= $totalConflicts ?></div><div class="cf-stat-lbl">Conflicts</div></div>
    </div>
    <div class="cf-stat">
      <div class="cf-stat-icon" style="background:#fff1f2">🏫</div>
      <div><div class="cf-stat-num"><?= count($conflicts['Room'] ?? []) ?></div><div class="cf-stat-lbl">Room Issues</div></div>
    </div>
    <div class="cf-stat">
      <div class="cf-stat-icon" style="background:#fffbeb">👨‍🏫</div>
      <div><div class="cf-stat-num"><?= count($conflicts['Professor'] ?? []) + count($conflicts['Section'] ?? []) ?></div><div class="cf-stat-lbl">Prof / Section</div></div>
    </div>
  </div>

  <?php if (!$ran): ?>
  <!-- Prompt to run -->
  <div class="cf-prompt">
    <span class="icon">🔍</span>
    <h3>Ready to Scan</h3>
    <p>Click <strong>Run Conflict Check</strong> to scan all <?= $totalSchedules ?> schedule entries<br>for room, professor, and section overlaps.</p>
    <a href="conflicts.php?run=1" class="btn-scan">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Run Conflict Check
    </a>
  </div>

  <?php elseif ($ran && $totalConflicts === 0): ?>
  <!-- All clear -->
  <div class="result-banner ok">
    <div class="result-icon">🎉</div>
    <div>
      <div class="result-title" style="color:#166534">All Clear — No Conflicts Found!</div>
      <div class="result-sub">All <?= $totalSchedules ?> schedule entries passed. No room, professor, or section overlaps detected.</div>
    </div>
  </div>

  <?php else: ?>
  <!-- Conflicts found -->
  <div class="result-banner warn">
    <div class="result-icon">⚠️</div>
    <div>
      <div class="result-title" style="color:#991b1b"><?= $totalConflicts ?> Conflict<?= $totalConflicts!==1?'s':'' ?> Found</div>
      <div class="result-sub">Review each conflict below and click <strong>Fix This</strong> to edit the schedule directly — no need to leave this page.</div>
    </div>
  </div>

  <?php
  $typeMeta = [
    'Room'      => ['color'=>'#ef4444','bg'=>'#fff1f2','border'=>'#fecaca','badgeBg'=>'#fee2e2','badgeFg'=>'#dc2626','label'=>'Room Conflicts'],
    'Professor' => ['color'=>'#f59e0b','bg'=>'#fffbeb','border'=>'#fde68a','badgeBg'=>'#fef3c7','badgeFg'=>'#d97706','label'=>'Professor Conflicts'],
    'Section'   => ['color'=>'#8b5cf6','bg'=>'#faf5ff','border'=>'#e9d5ff','badgeBg'=>'#ede9fe','badgeFg'=>'#7c3aed','label'=>'Section Conflicts'],
  ];
  foreach ($conflicts as $type => $items):
    if (empty($items)) continue;
    $tm = $typeMeta[$type] ?? $typeMeta['Room'];
  ?>
  <div class="cf-section">
    <div class="cf-section-head">
      <span class="cf-type-badge" style="background:<?= $tm['badgeBg'] ?>;color:<?= $tm['badgeFg'] ?>"><?= $type ?></span>
      <span class="cf-section-count"><span><?= count($items) ?></span> issue<?= count($items)!==1?'s':'' ?></span>
    </div>

    <?php foreach ($items as $item):
      $a = $item['a']; $b = $item['b'];
    ?>
    <div class="cf-card" style="background:<?= $tm['bg'] ?>;border-color:<?= $tm['border'] ?>">
      <!-- Card header -->
      <div class="cf-card-head" style="background:<?= $tm['bg'] ?>">
        <div class="cf-card-icon-msg">
          <span class="cf-card-icon"><?= $item['icon'] ?></span>
          <span class="cf-card-msg"><?= $item['msg'] ?></span>
        </div>
      </div>

      <!-- Two conflicting entries side-by-side -->
      <div class="cf-entries" style="border-top-color:<?= $tm['border'] ?>">
        <?php foreach ([$a, $b] as $idx => $sc): ?>
        <div class="cf-entry" style="border-right-color:<?= $tm['border'] ?>">
          <div class="cf-entry-label">Entry <?= chr(65+$idx) ?> — Schedule #<?= $sc['id'] ?></div>
          <div><span class="cf-entry-code"><?= h($sc['sub_code']) ?></span></div>
          <div class="cf-entry-name"><?= h($sc['sub_name']) ?></div>
          <div class="cf-entry-meta">
            <span>🏫 <?= h($sc['sec_name']) ?> &nbsp;·&nbsp; <?= h($sc['c_code']) ?></span>
            <span>👤 <?= h($sc['pr_name']) ?></span>
            <span>🚪 <?= h($sc['rm_name']) ?></span>
            <span>📅 <?= dayFull($sc['day_code']) ?></span>
          </div>
          <div class="cf-entry-time"><?= formatTimeRange($sc['start_time'],$sc['end_time']) ?></div>
          <div style="margin-top:10px">
            <button type="button" class="btn-fix"
              onclick="openEditModal(<?= htmlspecialchars(json_encode($sc), ENT_QUOTES) ?>)">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Fix This
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <div class="cf-tip">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <span><strong>Tip:</strong> After saving a fix, the page will automatically re-scan and update the conflict list. You can fix conflicts one by one without leaving this page.</span>
  </div>

  <?php endif; ?>

</div><!-- /cf-page -->

<!-- ══════════════════════════════════════════════════
     INLINE EDIT MODAL
══════════════════════════════════════════════════ -->
<div id="edit-modal" role="dialog" aria-modal="true">
  <div class="em-box" id="em-box">
    <div class="em-hdr">
      <div class="em-hdr-left">
        <div class="em-icon">✏️</div>
        <div>
          <div class="em-title" id="em-title">Edit Schedule</div>
          <div class="em-sub" id="em-sub">Adjust to resolve the conflict</div>
        </div>
      </div>
      <button type="button" class="em-close" id="em-close">✕</button>
    </div>
    <div class="em-body">
      <!-- Context strip (read-only info) -->
      <div class="em-context" id="em-context"></div>

      <form id="em-form">
        <input type="hidden" name="id"         id="em-id">
        <input type="hidden" name="section_id" id="em-section-id">
        <input type="hidden" name="subject_id" id="em-subject-id">
        <input type="hidden" name="semester"   id="em-semester">

        <div class="fg2" style="margin-bottom:14px">
          <div>
            <label class="fl">Day <span class="req">*</span></label>
            <select id="em-day" name="day_code" class="fc" required>
              <?php foreach ($days as $code => $label): ?>
                <option value="<?= $code ?>"><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><!-- spacer --></div>
          <div>
            <label class="fl">Start Time <span class="req">*</span></label>
            <input id="em-start" class="fc" name="start_time" type="time" required>
          </div>
          <div>
            <label class="fl">End Time <span class="req">*</span></label>
            <input id="em-end" class="fc" name="end_time" type="time" required>
          </div>
        </div>

        <div class="fg2">
          <div>
            <label class="fl">Room <span class="req">*</span></label>
            <select id="em-room" name="room_id" class="fc" required>
              <option value="">— Select Room —</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= $r['id'] ?>"><?= h($r['name']) ?> (<?= h($r['room_type']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="fl">Professor <span class="req">*</span></label>
            <select id="em-prof" name="professor_id" class="fc" required>
              <option value="">— Select Professor —</option>
              <?php foreach ($professors as $p): ?>
                <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Conflict notice inside modal -->
        <div class="em-conflict-notice" id="em-conflict-notice"></div>

        <div class="em-actions">
          <button type="submit" class="btn btn-primary btn-save-em" id="em-save">💾 Save & Re-check</button>
          <button type="button" class="btn btn-ghost" id="em-cancel">Cancel</button>
          <span style="font-size:.73rem;color:var(--text3,#aaa);margin-left:auto;display:flex;align-items:center;gap:4px">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Conflicts re-checked automatically
          </span>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ── Toast ─────────────────────────────────────────── */
function showToast(msg, type) {
    const wrap = document.getElementById('toast-wrap');
    const t    = document.createElement('div');
    t.className = 'toast ' + (type || 'success');
    t.innerHTML = (type === 'error' ? '❌ ' : '✅ ') + msg;
    wrap.appendChild(t);
    setTimeout(() => {
        t.style.animation = 'tOut .3s ease forwards';
        setTimeout(() => t.remove(), 300);
    }, 3500);
}

/* ── Modal open/close ───────────────────────────────── */
const modal   = document.getElementById('edit-modal');
const modalBx = document.getElementById('em-box');

function openEditModal(sc) {
    // Populate context strip
    document.getElementById('em-context').innerHTML =
        `<div class="em-ctx-item"><span class="em-ctx-label">Subject</span> <strong>${sc.sub_code}</strong> — ${sc.sub_name}</div>` +
        `<div class="em-ctx-item"><span class="em-ctx-label">Section</span> ${sc.sec_name}</div>` +
        `<div class="em-ctx-item"><span class="em-ctx-label">Semester</span> ${sc.semester}</div>`;

    document.getElementById('em-title').textContent = 'Edit Schedule #' + sc.id;
    document.getElementById('em-sub').textContent   = sc.sub_code + ' — ' + sc.sub_name;

    // Fill hidden fields
    document.getElementById('em-id').value         = sc.id;
    document.getElementById('em-section-id').value = sc.section_id;
    document.getElementById('em-subject-id').value = sc.subject_id;
    document.getElementById('em-semester').value   = sc.semester;

    // Fill editable fields
    document.getElementById('em-day').value   = sc.day_code;
    document.getElementById('em-start').value = sc.start_time ? sc.start_time.substring(0,5) : '';
    document.getElementById('em-end').value   = sc.end_time   ? sc.end_time.substring(0,5)   : '';
    document.getElementById('em-room').value  = sc.room_id;
    document.getElementById('em-prof').value  = sc.professor_id;

    // Clear any previous conflict notice
    const notice = document.getElementById('em-conflict-notice');
    notice.innerHTML = '';
    notice.classList.remove('visible');

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('em-close').addEventListener('click',  function(e){ e.preventDefault(); e.stopPropagation(); closeModal(); });
document.getElementById('em-cancel').addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); closeModal(); });
modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
modalBx.addEventListener('click', function(e){ e.stopPropagation(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('active')) closeModal(); });

/* ── AJAX Save ──────────────────────────────────────── */
document.getElementById('em-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn    = document.getElementById('em-save');
    const notice = document.getElementById('em-conflict-notice');
    btn.classList.add('loading');
    btn.textContent = 'Saving…';
    notice.classList.remove('visible');

    try {
        const res  = await fetch('conflicts.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(this)
        });
        const json = await res.json();

        if (json.ok) {
            showToast(json.msg, 'success');
            closeModal();
            // Re-run conflict check by reloading with run param
            setTimeout(() => {
                window.location.href = 'conflicts.php?rerun=1';
            }, 800);
        } else {
            // Show conflict detail inside the modal
            let html = '<strong>⚠ Cannot save — still conflicts:</strong><ul style="margin:6px 0 0 16px;padding:0">';
            if (json.conflicts && json.conflicts.length) {
                json.conflicts.forEach(c => { html += '<li>' + c + '</li>'; });
            } else {
                html += '<li>' + json.msg + '</li>';
            }
            html += '</ul>';
            notice.innerHTML = html;
            notice.classList.add('visible');
            showToast('Conflicts still exist — adjust and try again.', 'error');
        }
    } catch (err) {
        showToast('Network error. Please try again.', 'error');
    } finally {
        btn.classList.remove('loading');
        btn.innerHTML = '💾 Save & Re-check';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>