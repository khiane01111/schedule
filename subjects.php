<?php
require_once __DIR__ . '/includes/config.php';
$db = getDB();

/* ── AJAX handler ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    session_start();
    header('Content-Type: application/json');

    $action      = $_POST['action'] ?? '';
    $courseId    = ($_POST['course_id'] ?? '') !== '' ? (int)$_POST['course_id'] : null;
    $code        = sanitize($_POST['code'] ?? '');
    $name        = sanitize($_POST['name'] ?? '');
    $year        = sanitize($_POST['year_level'] ?? '');
    $sem         = sanitize($_POST['semester'] ?? '');
    $units       = (int)($_POST['units'] ?? 3);
    $hours       = (int)($_POST['hours_week'] ?? 3);
    $days        = (int)($_POST['days_week'] ?? 1);
    $profId      = ($_POST['professor_id'] ?? '') !== '' ? (int)$_POST['professor_id'] : null;
    $subjectType = sanitize($_POST['subject_type'] ?? 'Regular');

    try {
        if ($action === 'create') {

            if ($subjectType === 'Consultation') {
                if (!$code) $code = 'CONSULT';
                if (!$name) $name = 'Consultation Hours';
                /* course_id = NULL, year_level = 'All', semester = 'All' */
                $db->prepare("INSERT INTO subjects
                    (course_id,code,name,year_level,semester,units,hours_week,days_week,professor_id,subject_type)
                    VALUES (NULL,?,?,'All','All',?,?,?,?,'Consultation')")
                   ->execute([$code,$name,$units,$hours,$days,$profId]);
                echo json_encode(['ok'=>true,'msg'=>'Consultation Hours added successfully.']);

            } else {
                if (!$courseId || !$code || !$name || !$year || !$sem) {
                    echo json_encode(['ok'=>false,'msg'=>'Required fields missing.']); exit;
                }
                $db->prepare("INSERT INTO subjects
                    (course_id,code,name,year_level,semester,units,hours_week,days_week,professor_id,subject_type)
                    VALUES (?,?,?,?,?,?,?,?,?,'Regular')")
                   ->execute([$courseId,$code,$name,$year,$sem,$units,$hours,$days,$profId]);
                echo json_encode(['ok'=>true,'msg'=>'Subject added successfully.']);
            }

        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);

            if ($subjectType === 'Consultation') {
                if (!$code) $code = 'CONSULT';
                if (!$name) $name = 'Consultation Hours';
                $db->prepare("UPDATE subjects
                    SET course_id=NULL, code=?, name=?, year_level='All', semester='All',
                        units=?, hours_week=?, days_week=?, professor_id=?, subject_type='Consultation'
                    WHERE id=?")
                   ->execute([$code,$name,$units,$hours,$days,$profId,$id]);
            } else {
                if (!$courseId || !$code || !$name || !$year || !$sem) {
                    echo json_encode(['ok'=>false,'msg'=>'Required fields missing.']); exit;
                }
                $db->prepare("UPDATE subjects
                    SET course_id=?,code=?,name=?,year_level=?,semester=?,
                        units=?,hours_week=?,days_week=?,professor_id=?,subject_type='Regular'
                    WHERE id=?")
                   ->execute([$courseId,$code,$name,$year,$sem,$units,$hours,$days,$profId,$id]);
            }
            echo json_encode(['ok'=>true,'msg'=>'Subject updated successfully.']);

        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $db->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true,'msg'=>'Subject deleted.']);

        } else {
            echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>'Database error: '.$e->getMessage()]);
    }
    exit;
}

/* ── Normal GET page load ── */
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Subjects';
require_once __DIR__ . '/includes/header.php';

/* ── Ensure subject_type column exists ── */
try {
    $db->exec("ALTER TABLE subjects ADD COLUMN subject_type VARCHAR(30) NOT NULL DEFAULT 'Regular' AFTER professor_id");
} catch (Exception $e) { /* already exists */ }

/* ── Make course_id nullable (safe to run repeatedly) ── */
try {
    $db->exec("ALTER TABLE subjects MODIFY COLUMN course_id INT NULL");
} catch (Exception $e) { /* already nullable */ }

$courses    = $db->query("SELECT id,code,name FROM courses ORDER BY code")->fetchAll();
$professors = $db->query("SELECT id,name FROM professors ORDER BY name")->fetchAll();

$activeCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
$fYear = sanitize($_GET['year_level'] ?? '');
$fSem  = sanitize($_GET['semester']   ?? '');

/* ── Regular subjects per course ── */
$allSubjects = [];
foreach ($courses as $c) {
    $where  = ["sub.course_id = ?", "sub.subject_type = 'Regular'"];
    $params = [$c['id']];
    if ($fYear && (int)$c['id'] === $activeCourseId) { $where[] = 'sub.year_level=?'; $params[] = $fYear; }
    if ($fSem  && (int)$c['id'] === $activeCourseId) { $where[] = 'sub.semester=?';   $params[] = $fSem; }
    $stmt = $db->prepare("SELECT sub.*, c.code AS c_code, p.name AS prof_name
                          FROM subjects sub
                          JOIN courses c ON c.id = sub.course_id
                          LEFT JOIN professors p ON p.id = sub.professor_id
                          WHERE " . implode(' AND ', $where) . "
                          ORDER BY sub.year_level, sub.semester, sub.code");
    $stmt->execute($params);
    $allSubjects[$c['id']] = $stmt->fetchAll();
}

/* ── Consultation subjects (course_id IS NULL) ── */
$consultSubjects = $db->query("SELECT sub.*, p.name AS prof_name
                                FROM subjects sub
                                LEFT JOIN professors p ON p.id = sub.professor_id
                                WHERE sub.subject_type = 'Consultation'
                                ORDER BY sub.code, sub.id")
                      ->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $es = $db->prepare("SELECT * FROM subjects WHERE id=?");
    $es->execute([(int)$_GET['edit']]);
    $edit = $es->fetch();
    if ($edit && $edit['subject_type'] !== 'Consultation') {
        $activeCourseId = (int)$edit['course_id'];
    }
}

$yearLevels = ['1st Year','2nd Year','3rd Year','4th Year'];
$semesters  = ['1st Semester','2nd Semester'];
?>

<style>
.subj-page { font-family:'Segoe UI',system-ui,sans-serif; }

.subj-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:28px; flex-wrap:wrap; gap:12px;
}
.subj-header h2 { font-size:1.55rem; font-weight:700; color:var(--text,#111); margin:0 0 3px; letter-spacing:-.3px; }
.subj-header p  { margin:0; color:var(--text3,#888); font-size:.875rem; }

/* Toast */
#toast-wrap { position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.toast { display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:10px; font-size:.875rem; font-weight:600; box-shadow:0 8px 28px rgba(0,0,0,.14); animation:toastIn .25s ease; pointer-events:auto; max-width:340px; }
.toast.success { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }
.toast.error   { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; }
@keyframes toastIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes toastOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }

/* Modal */
#subj-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500; align-items:center; justify-content:center; padding:20px; }
#subj-modal.active { display:flex; }
.modal-box { background:var(--card-bg,#fff); border-radius:14px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 60px rgba(0,0,0,.2); animation:modalIn .22s ease; }
@keyframes modalIn { from{transform:translateY(18px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border,#e8e8e8); position:sticky; top:0; background:var(--card-bg,#fff); z-index:1; border-radius:14px 14px 0 0; }
.modal-title { font-size:1rem; font-weight:700; color:var(--text,#111); }
.modal-close { background:none; border:none; width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:var(--text3,#888); cursor:pointer; border-radius:8px; transition:background .15s,color .15s; }
.modal-close:hover { background:var(--hover,#f0f0f0); color:var(--text,#111); }
.modal-body { padding:22px 24px 24px; }

/* Type toggle */
.type-section { margin-bottom:18px; }
.type-section-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text2,#555); margin-bottom:7px; display:block; }
.type-toggle { display:flex; gap:8px; }
.type-btn { flex:1; padding:10px 12px; border-radius:9px; font-size:.82rem; font-weight:700; border:1.5px solid var(--border,#ddd); background:var(--card-bg,#fff); color:var(--text2,#555); cursor:pointer; transition:all .15s; text-align:center; display:flex; align-items:center; justify-content:center; gap:6px; }
.type-btn:hover { border-color:#3b82f6; color:#1d4ed8; background:#eff6ff; }
.type-btn.sel-regular { background:#eff6ff; color:#1d4ed8; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
.type-btn.sel-consult { background:#fdf4ff; color:#7c3aed; border-color:#a855f7; box-shadow:0 0 0 3px rgba(168,85,247,.1); }

/* Consultation hint */
.consult-hint { background:linear-gradient(135deg,#fdf4ff,#f5f3ff); border:1.5px solid #e9d5ff; border-radius:10px; padding:11px 14px; margin-bottom:16px; font-size:.82rem; color:#6b21a8; line-height:1.55; display:none; align-items:flex-start; gap:10px; }
.consult-hint.show { display:flex; }
.consult-hint-icon { font-size:1.15rem; flex-shrink:0; }

/* Form */
.fg { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
.fg .full { grid-column:1 / -1; }
.form-label { display:block; font-size:.78rem; font-weight:600; color:var(--text2,#555); margin-bottom:5px; letter-spacing:.3px; text-transform:uppercase; }
.form-label .req { color:#e53e3e; margin-left:2px; }
.fc { width:100%; padding:9px 12px; border:1.5px solid var(--border,#ddd); border-radius:8px; font-size:.9rem; color:var(--text,#111); background:var(--input-bg,#fafafa); transition:border-color .15s,box-shadow .15s; box-sizing:border-box; }
.fc:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.13); background:#fff; }
.form-field-hidden { display:none !important; }
.form-actions { display:flex; gap:10px; margin-top:20px; padding-top:18px; border-top:1px solid var(--border,#e8e8e8); }
.btn-save.loading { opacity:.7; pointer-events:none; }

/* Course Tabs */
.course-tabs { display:flex; gap:4px; border-bottom:2px solid var(--border,#e8e8e8); margin-bottom:22px; overflow-x:auto; }
.course-tab { display:flex; align-items:center; gap:7px; padding:10px 18px; border-radius:8px 8px 0 0; font-size:.875rem; font-weight:600; color:var(--text3,#888); text-decoration:none; white-space:nowrap; border:1.5px solid transparent; border-bottom:none; margin-bottom:-2px; transition:color .15s,background .15s; }
.course-tab:hover { color:var(--text,#111); background:var(--hover,#f5f5f5); }
.course-tab.active { color:#3b82f6; background:var(--card-bg,#fff); border-color:var(--border,#e8e8e8); border-bottom-color:var(--card-bg,#fff); }
.tab-count { font-size:.72rem; font-weight:700; background:var(--hover,#eee); border-radius:20px; padding:1px 7px; color:var(--text3,#777); }
.course-tab.active .tab-count { background:#dbeafe; color:#1d4ed8; }
.course-tab.consult-tab { color:#7c3aed; }
.course-tab.consult-tab.active { color:#7c3aed; border-color:#e9d5ff; border-bottom-color:var(--card-bg,#fff); }
.course-tab.consult-tab .tab-count { background:#ede9fe; color:#5b21b6; }

/* Stat Pills */
.stat-pills { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
.stat-pill { display:flex; align-items:center; gap:6px; padding:7px 14px; border-radius:10px; background:var(--card-bg,#fff); border:1.5px solid var(--border,#e8e8e8); font-size:.82rem; font-weight:600; color:var(--text2,#555); }
.stat-pill span { font-size:1rem; font-weight:800; color:var(--text,#111); }
.stat-pill.consult-pill { border-color:#e9d5ff; background:#fdf4ff; }
.stat-pill.consult-pill span { color:#7c3aed; }

/* Filters */
.filters-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:18px; }
.filter-item label { display:block; font-size:.75rem; font-weight:600; color:var(--text3,#888); text-transform:uppercase; letter-spacing:.3px; margin-bottom:4px; }
.filter-item select { padding:8px 12px; border:1.5px solid var(--border,#ddd); border-radius:8px; font-size:.85rem; color:var(--text,#111); background:var(--card-bg,#fff); cursor:pointer; min-width:140px; }
.filter-item select:focus { outline:none; border-color:#3b82f6; }

/* Year groups */
.year-group { margin-bottom:28px; }
.year-badge { font-size:.72rem; font-weight:700; letter-spacing:.5px; text-transform:uppercase; padding:4px 11px; border-radius:20px; }
.yr1{background:#dcfce7;color:#166534} .yr2{background:#dbeafe;color:#1e40af}
.yr3{background:#fef9c3;color:#854d0e} .yr4{background:#f3e8ff;color:#6b21a8}
.sem-label { font-size:.75rem; font-weight:600; color:var(--text3,#999); letter-spacing:.5px; text-transform:uppercase; padding:2px 9px; border-radius:20px; background:var(--hover,#f0f0f0); }

/* Consultation section header */
.consult-section-header { display:flex; align-items:center; gap:12px; padding:18px 20px 14px; border-bottom:1.5px solid #e9d5ff; background:linear-gradient(135deg,#fdf4ff,#f9f5ff); }
.consult-section-icon { font-size:1.5rem; }
.consult-section-title { font-size:.95rem; font-weight:800; color:#6b21a8; }
.consult-section-sub { font-size:.78rem; color:#a855f7; margin-top:1px; }
.consult-all-badge { margin-left:auto; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; padding:3px 10px; border-radius:20px; background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe; white-space:nowrap; }

/* Table */
.subj-table { width:100%; border-collapse:collapse; }
.subj-table thead tr { background:var(--hover,#f7f7f7); }
.subj-table th { padding:10px 14px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3,#888); text-align:left; border-bottom:1.5px solid var(--border,#e8e8e8); white-space:nowrap; }
.subj-table td { padding:12px 14px; font-size:.875rem; color:var(--text,#333); border-bottom:1px solid var(--border,#f0f0f0); vertical-align:middle; }
.subj-table tbody tr { transition:background .12s; }
.subj-table tbody tr:hover { background:var(--hover,#fafafa); }
.subj-table tbody tr:last-child td { border-bottom:none; }
.subj-table tbody tr.is-consultation { background:#fdf4ff; }
.subj-table tbody tr.is-consultation:hover { background:#f5e8ff; }

.code-chip { display:inline-block; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:6px; font-size:.78rem; font-weight:700; padding:3px 9px; letter-spacing:.3px; }
.code-chip.chip-consult { background:#fdf4ff; color:#7c3aed; border-color:#e9d5ff; }
.subj-name { font-weight:600; color:var(--text,#111); }
.prof-unassigned { color:var(--text3,#aaa); font-style:italic; font-size:.83rem; }
.td-center { text-align:center; }
.global-info { display:inline-flex; align-items:center; gap:5px; font-size:.73rem; font-weight:600; color:#7c3aed; background:#fdf4ff; border:1px solid #e9d5ff; border-radius:6px; padding:3px 9px; }

/* Row actions */
.row-actions { display:flex; gap:6px; align-items:center; }
.btn-edit { padding:5px 12px; border-radius:7px; font-size:.78rem; font-weight:600; border:1.5px solid var(--border,#ddd); background:var(--card-bg,#fff); color:var(--text,#333); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:border-color .15s,background .15s; }
.btn-edit:hover { border-color:#3b82f6; color:#3b82f6; background:#eff6ff; }
.btn-del { padding:5px 10px; border-radius:7px; font-size:.78rem; font-weight:600; border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626; cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:background .15s,border-color .15s; }
.btn-del:hover { background:#fee2e2; border-color:#f87171; }

/* Empty */
.empty-subj { text-align:center; padding:48px 20px; color:var(--text3,#aaa); }
.empty-subj .empty-icon { font-size:2.2rem; display:block; margin-bottom:10px; }
.empty-subj p { margin:0; font-size:.9rem; }

@media(max-width:640px){
    .fg{grid-template-columns:1fr} .fg .full{grid-column:1}
    .modal-box{border-radius:10px}
    .subj-header{flex-direction:column;align-items:flex-start;gap:12px}
    .type-toggle{flex-direction:column}
}
</style>

<div id="toast-wrap"></div>

<div class="subj-page">

  <div class="subj-header">
    <div>
      <h2>Subject Management</h2>
      <p>Browse and manage subjects organised by course, year level, and semester</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()">+ Add Subject</button>
  </div>

  <!-- ══════════════════════ MODAL ══════════════════════ -->
  <div id="subj-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-box" id="modal-box">
      <div class="modal-header">
        <span class="modal-title" id="modal-title">Add New Subject</span>
        <button type="button" class="modal-close" id="btn-close-modal" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <form id="subj-form">
          <input type="hidden" name="action"       id="form-action"       value="create">
          <input type="hidden" name="id"           id="form-id"           value="">
          <input type="hidden" name="subject_type" id="form-subject-type" value="Regular">

          <!-- Type toggle -->
          <div class="type-section">
            <span class="type-section-label">Subject Type</span>
            <div class="type-toggle">
              <button type="button" class="type-btn sel-regular" id="type-btn-regular" onclick="setSubjectType('Regular')">
                📚 Regular Subject
              </button>
              <button type="button" class="type-btn" id="type-btn-consult" onclick="setSubjectType('Consultation')">
                🕐 Consultation Hours
              </button>
            </div>
          </div>

          <!-- Consultation hint -->
          <div class="consult-hint" id="consult-hint">
            <span class="consult-hint-icon">💡</span>
            <span>Consultation Hours is <strong>global</strong> — it automatically appears across <strong>all courses and semesters</strong> in the schedule. It can be added to the schedule multiple times. No course or semester selection is needed.</span>
          </div>

          <div class="fg">
            <!-- Course — hidden for Consultation -->
            <div id="field-course">
              <label class="form-label">Course <span class="req">*</span></label>
              <select name="course_id" id="f-course" class="fc">
                <option value="">— Select Course —</option>
                <?php foreach ($courses as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= h($c['code']) ?> – <?= h($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Subject Code <span class="req">*</span></label>
              <input class="fc" name="code" id="f-code" placeholder="e.g. CC102 / CONSULT" required>
            </div>

            <div class="full">
              <label class="form-label">Subject Name <span class="req">*</span></label>
              <input class="fc" name="name" id="f-name" placeholder="e.g. Computer Programming 1" required>
            </div>

            <!-- Year Level — hidden for Consultation -->
            <div id="field-year">
              <label class="form-label">Year Level <span class="req">*</span></label>
              <select name="year_level" id="f-year" class="fc">
                <?php foreach ($yearLevels as $y): ?>
                  <option value="<?= $y ?>"><?= $y ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Semester — hidden for Consultation -->
            <div id="field-sem">
              <label class="form-label">Semester <span class="req">*</span></label>
              <select name="semester" id="f-sem" class="fc">
                <?php foreach ($semesters as $s): ?>
                  <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Units</label>
              <input class="fc" name="units" id="f-units" type="number" min="0" max="6" value="3">
            </div>
            <div>
              <label class="form-label">Hours / Week</label>
              <input class="fc" name="hours_week" id="f-hours" type="number" min="1" max="10" value="3">
            </div>
            <div>
              <label class="form-label">Class Days / Week</label>
              <input class="fc" name="days_week" id="f-days" type="number" min="1" max="6" value="1">
            </div>
            <div>
              <label class="form-label">Default Professor</label>
              <select name="professor_id" id="f-prof" class="fc">
                <option value="">— None —</option>
                <?php foreach ($professors as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-save" id="btn-save">💾 Save Subject</button>
            <button type="button" class="btn btn-ghost" id="btn-cancel-modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if (empty($courses)): ?>
    <div class="empty-subj"><span class="empty-icon">🏫</span><p>No courses found. Please add a course first.</p></div>
  <?php else: ?>

    <!-- Course Tabs + Consultation tab -->
    <div class="course-tabs no-print">
      <?php foreach ($courses as $c): ?>
        <?php $cnt = count($allSubjects[$c['id']]); ?>
        <a href="subjects.php?course_id=<?= $c['id'] ?><?= $fYear?'&year_level='.urlencode($fYear):'' ?><?= $fSem?'&semester='.urlencode($fSem):'' ?>"
           class="course-tab <?= $activeCourseId==$c['id'] && !isset($_GET['view'])?'active':'' ?>">
          <?= h($c['code']) ?>
          <span class="tab-count"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
      <a href="subjects.php?view=consultation"
         class="course-tab consult-tab <?= (isset($_GET['view']) && $_GET['view']==='consultation')?'active':'' ?>">
        🕐 Consultation
        <span class="tab-count"><?= count($consultSubjects) ?></span>
      </a>
    </div>

    <?php if (isset($_GET['view']) && $_GET['view'] === 'consultation'): ?>
    <!-- ══════════ CONSULTATION VIEW ══════════ -->
    <div class="card">
      <div class="consult-section-header">
        <span class="consult-section-icon">🕐</span>
        <div>
          <div class="consult-section-title">Consultation Hours</div>
          <div class="consult-section-sub">Global subjects — appear in the schedule for all courses and all semesters</div>
        </div>
        <span class="consult-all-badge">✦ All Courses · All Semesters</span>
      </div>
      <?php if (empty($consultSubjects)): ?>
        <div class="empty-subj">
          <span class="empty-icon">🕐</span>
          <p>No consultation hours added yet. Click <strong>+ Add Subject</strong> and choose <em>Consultation Hours</em>.</p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table class="subj-table">
            <thead>
              <tr>
                <th>Code</th><th>Name</th><th>Units</th><th>Hrs/Wk</th><th>Days/Wk</th>
                <th>Professor</th><th>Availability</th><th class="no-print">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($consultSubjects as $s): ?>
              <tr id="row-<?= $s['id'] ?>" class="is-consultation">
                <td><span class="code-chip chip-consult">🕐 <?= h($s['code']) ?></span></td>
                <td><span class="subj-name"><?= h($s['name']) ?></span></td>
                <td class="td-center"><?= h($s['units']) ?></td>
                <td class="td-center"><?= h($s['hours_week']) ?> h</td>
                <td class="td-center"><?= h($s['days_week']) ?>×</td>
                <td><?= $s['prof_name'] ? h($s['prof_name']) : '<span class="prof-unassigned">Unassigned</span>' ?></td>
                <td><span class="global-info">✦ All Courses &amp; Semesters</span></td>
                <td class="no-print">
                  <div class="row-actions">
                    <button type="button" class="btn-edit" onclick="editSubject(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)">✏ Edit</button>
                    <button type="button" class="btn-del"  onclick="deleteSubject(<?= $s['id'] ?>,this)">🗑</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ══════════ REGULAR SUBJECTS VIEW ══════════ -->
    <?php
    $activeCourse   = null;
    foreach ($courses as $c) { if ((int)$c['id']===$activeCourseId) { $activeCourse=$c; break; } }
    $activeSubjects = $allSubjects[$activeCourseId] ?? [];
    $s1 = count(array_filter($activeSubjects, fn($s)=>$s['semester']==='1st Semester'));
    $s2 = count(array_filter($activeSubjects, fn($s)=>$s['semester']==='2nd Semester'));
    $totalUnits = array_sum(array_column($activeSubjects,'units'));
    ?>

    <div class="stat-pills no-print">
      <div class="stat-pill">📚 <span><?= count($activeSubjects) ?></span> Total Subjects</div>
      <div class="stat-pill">🔵 <span><?= $s1 ?></span> 1st Semester</div>
      <div class="stat-pill">🟠 <span><?= $s2 ?></span> 2nd Semester</div>
      <div class="stat-pill">⚡ <span><?= $totalUnits ?></span> Total Units</div>
      <?php if (count($consultSubjects)): ?>
      <div class="stat-pill consult-pill">🕐 <span><?= count($consultSubjects) ?></span> Consultation Slots</div>
      <?php endif; ?>
    </div>

    <div class="filters-bar no-print">
      <form method="GET" id="filter-form" style="display:contents">
        <input type="hidden" name="course_id" value="<?= $activeCourseId ?>">
        <div class="filter-item">
          <label>Year Level</label>
          <select name="year_level" onchange="document.getElementById('filter-form').submit()">
            <option value="">All Years</option>
            <?php foreach ($yearLevels as $y): ?>
              <option value="<?= $y ?>" <?= $fYear===$y?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-item">
          <label>Semester</label>
          <select name="semester" onchange="document.getElementById('filter-form').submit()">
            <option value="">All Semesters</option>
            <?php foreach ($semesters as $s): ?>
              <option value="<?= $s ?>" <?= $fSem===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($fYear || $fSem): ?>
          <div class="filter-item" style="align-self:flex-end">
            <a href="subjects.php?course_id=<?= $activeCourseId ?>" class="btn btn-ghost btn-sm">✕ Clear</a>
          </div>
        <?php endif; ?>
      </form>
    </div>

    <?php
    $yearColors = ['1st Year'=>'yr1','2nd Year'=>'yr2','3rd Year'=>'yr3','4th Year'=>'yr4'];
    $grouped = [];
    foreach ($activeSubjects as $s) { $grouped[$s['year_level']][$s['semester']][] = $s; }
    ?>

    <div class="card">
      <?php if (empty($activeSubjects)): ?>
        <div class="empty-subj">
          <span class="empty-icon">📋</span>
          <p>No subjects found<?= ($fYear||$fSem)?' for the selected filters':' for this course' ?>.</p>
        </div>
      <?php else: ?>
        <div class="card-body no-pad">
          <?php foreach ($yearLevels as $year):
            if (!isset($grouped[$year])) continue; ?>
          <div class="year-group" style="padding:20px 20px 0">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
              <span class="year-badge <?= $yearColors[$year]??'yr1' ?>"><?= $year ?></span>
            </div>
            <?php foreach ($semesters as $sem):
              if (!isset($grouped[$year][$sem])) continue;
              $rows = $grouped[$year][$sem]; ?>
            <div style="margin-bottom:18px">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <span class="sem-label"><?= $sem ?></span>
                <span style="font-size:.75rem;color:var(--text3,#aaa)"><?= count($rows) ?> subject<?= count($rows)!==1?'s':'' ?></span>
              </div>
              <div class="table-wrap" style="border-radius:10px;border:1.5px solid var(--border,#e8e8e8);overflow:hidden">
                <table class="subj-table">
                  <thead>
                    <tr>
                      <th>Code</th><th>Subject Name</th><th>Units</th>
                      <th>Hrs/Wk</th><th>Days</th><th>Professor</th>
                      <th class="no-print">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($rows as $s): ?>
                    <tr id="row-<?= $s['id'] ?>">
                      <td><span class="code-chip"><?= h($s['code']) ?></span></td>
                      <td><span class="subj-name"><?= h($s['name']) ?></span></td>
                      <td class="td-center"><?= h($s['units']) ?></td>
                      <td class="td-center"><?= h($s['hours_week']) ?> h</td>
                      <td class="td-center"><?= h($s['days_week']) ?>×</td>
                      <td><?= $s['prof_name'] ? h($s['prof_name']) : '<span class="prof-unassigned">Unassigned</span>' ?></td>
                      <td class="no-print">
                        <div class="row-actions">
                          <button type="button" class="btn-edit" onclick="editSubject(<?= htmlspecialchars(json_encode($s),ENT_QUOTES) ?>)">✏ Edit</button>
                          <button type="button" class="btn-del"  onclick="deleteSubject(<?= $s['id'] ?>,this)">🗑</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <div style="height:8px"></div>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<script>
const modal      = document.getElementById('subj-modal');
const modalTitle = document.getElementById('modal-title');
const modalBox   = document.getElementById('modal-box');

function openModal(title) {
    modalTitle.textContent = title || 'Add New Subject';
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('form-action').value = 'create';
    document.getElementById('form-id').value = '';
    document.getElementById('subj-form').reset();
    modalTitle.textContent = 'Add New Subject';
    _applyTypeUI('Regular');
}
document.getElementById('btn-close-modal').addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); closeModal(); });
document.getElementById('btn-cancel-modal').addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); closeModal(); });
modal.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });
modalBox.addEventListener('click', e=>e.stopPropagation());
document.addEventListener('keydown', e=>{ if(e.key==='Escape' && modal.classList.contains('active')) closeModal(); });

function _applyTypeUI(type) {
    const btnR    = document.getElementById('type-btn-regular');
    const btnC    = document.getElementById('type-btn-consult');
    const hint    = document.getElementById('consult-hint');
    const save    = document.getElementById('btn-save');
    const fCourse = document.getElementById('field-course');
    const fYear   = document.getElementById('field-year');
    const fSem    = document.getElementById('field-sem');
    const isEdit  = !!document.getElementById('form-id').value;

    document.getElementById('form-subject-type').value = type;

    if (type === 'Consultation') {
        btnR.className = 'type-btn';
        btnC.className = 'type-btn sel-consult';
        hint.classList.add('show');
        save.textContent = '💾 Save Consultation Hours';
        modalTitle.textContent = isEdit ? 'Edit Consultation Hours' : 'Add Consultation Hours';
        fCourse.classList.add('form-field-hidden');
        fYear.classList.add('form-field-hidden');
        fSem.classList.add('form-field-hidden');
        document.getElementById('f-course').removeAttribute('required');
        document.getElementById('f-year').removeAttribute('required');
        document.getElementById('f-sem').removeAttribute('required');
    } else {
        btnR.className = 'type-btn sel-regular';
        btnC.className = 'type-btn';
        hint.classList.remove('show');
        save.innerHTML = '💾 Save Subject';
        modalTitle.textContent = isEdit ? 'Edit Subject' : 'Add New Subject';
        fCourse.classList.remove('form-field-hidden');
        fYear.classList.remove('form-field-hidden');
        fSem.classList.remove('form-field-hidden');
        document.getElementById('f-course').setAttribute('required', '');
        document.getElementById('f-year').setAttribute('required', '');
        document.getElementById('f-sem').setAttribute('required', '');
    }
}

function setSubjectType(type) {
    const isEdit = !!document.getElementById('form-id').value;
    const codeEl = document.getElementById('f-code');
    const nameEl = document.getElementById('f-name');
    _applyTypeUI(type);
    if (!isEdit) {
        if (type === 'Consultation') {
            if (!codeEl.value) codeEl.value = 'CONSULT';
            if (!nameEl.value) nameEl.value = 'Consultation Hours';
            document.getElementById('f-units').value = '1';
            document.getElementById('f-hours').value = '1';
            document.getElementById('f-days').value  = '1';
        } else {
            if (codeEl.value === 'CONSULT')            codeEl.value = '';
            if (nameEl.value === 'Consultation Hours') nameEl.value = '';
            document.getElementById('f-units').value = '3';
            document.getElementById('f-hours').value = '3';
            document.getElementById('f-days').value  = '1';
        }
    }
}

function showToast(msg, type) {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');
    t.className = 'toast ' + (type || 'success');
    t.innerHTML = (type==='error'?'❌ ':'✅ ') + msg;
    wrap.appendChild(t);
    setTimeout(()=>{ t.style.animation='toastOut .3s ease forwards'; setTimeout(()=>t.remove(),300); },3000);
}

function editSubject(s) {
    document.getElementById('form-action').value = 'update';
    document.getElementById('form-id').value     = s.id;
    document.getElementById('f-course').value    = s.course_id || '';
    document.getElementById('f-code').value      = s.code;
    document.getElementById('f-name').value      = s.name;
    document.getElementById('f-year').value      = (s.year_level && s.year_level !== 'All') ? s.year_level : '1st Year';
    document.getElementById('f-sem').value       = (s.semester  && s.semester  !== 'All') ? s.semester  : '1st Semester';
    document.getElementById('f-units').value     = s.units;
    document.getElementById('f-hours').value     = s.hours_week;
    document.getElementById('f-days').value      = s.days_week;
    document.getElementById('f-prof').value      = s.professor_id || '';
    _applyTypeUI(s.subject_type || 'Regular');
    openModal(s.subject_type === 'Consultation' ? 'Edit Consultation Hours' : 'Edit Subject');
}

document.getElementById('subj-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save');
    btn.classList.add('loading'); btn.textContent = 'Saving…';
    try {
        const res  = await fetch('subjects.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new FormData(this) });
        const json = await res.json();
        if (json.ok) {
            showToast(json.msg, 'success');
            closeModal();
            setTimeout(()=>location.reload(), 900);
        } else {
            showToast(json.msg, 'error');
        }
    } catch(err) {
        showToast('Network error. Please try again.', 'error');
    } finally {
        btn.classList.remove('loading');
        btn.innerHTML = '💾 Save Subject';
    }
});

async function deleteSubject(id, btn) {
    if (!confirm('Delete this subject? This cannot be undone.')) return;
    btn.disabled = true; btn.textContent = '…';
    const data = new FormData(); data.append('action','delete'); data.append('id',id);
    try {
        const res  = await fetch('subjects.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:data });
        const json = await res.json();
        if (json.ok) {
            const row = document.getElementById('row-'+id);
            if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(()=>row.remove(),250); }
            showToast(json.msg, 'success');
        } else {
            showToast(json.msg, 'error');
            btn.disabled = false; btn.textContent = '🗑';
        }
    } catch(err) {
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false; btn.textContent = '🗑';
    }
}

<?php if ($edit): ?>
editSubject(<?= json_encode($edit) ?>);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>