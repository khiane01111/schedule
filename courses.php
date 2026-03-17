<?php
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// ══════════════════════════════════════════════════════
// AJAX HANDLER
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $code   = sanitize($_POST['code']       ?? '');
    $name   = sanitize($_POST['name']       ?? '');
    $dept   = sanitize($_POST['department'] ?? '');
    $id     = (int)($_POST['id']            ?? 0);

    try {
        if ($action === 'create') {
            if (!$code || !$name || !$dept) {
                echo json_encode(['ok'=>false,'msg'=>'All fields are required.']); exit;
            }
            $db->prepare("INSERT INTO courses (code,name,department) VALUES (?,?,?)")
               ->execute([$code,$name,$dept]);
            echo json_encode(['ok'=>true,'msg'=>'Course added successfully.']);
        } elseif ($action === 'update') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("UPDATE courses SET code=?,name=?,department=? WHERE id=?")
               ->execute([$code,$name,$dept,$id]);
            echo json_encode(['ok'=>true,'msg'=>'Course updated successfully.']);
        } elseif ($action === 'delete') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true,'msg'=>'Course deleted.']);
        } else {
            echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
        }
    } catch (PDOException $e) {
        $msg = str_contains($e->getMessage(),'Duplicate') ? 'Course code already exists.' : 'Database error: '.$e->getMessage();
        echo json_encode(['ok'=>false,'msg'=>$msg]);
    }
    exit;
}

// ══════════════════════════════════════════════════════
// PAGE LOAD
// ══════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Courses';
require_once __DIR__ . '/includes/header.php';

$courses = $db->query("
    SELECT c.*,
           COUNT(DISTINCT s.id)   AS section_count,
           COUNT(DISTINCT sub.id) AS subject_count
    FROM courses c
    LEFT JOIN sections s   ON s.course_id   = c.id
    LEFT JOIN subjects sub ON sub.course_id = c.id
    GROUP BY c.id ORDER BY c.code
")->fetchAll();

$totalSections = array_sum(array_column($courses,'section_count'));
$totalSubjects = array_sum(array_column($courses,'subject_count'));
$depts = array_unique(array_column($courses,'department'));
?>

<style>
/* ── Base ─────────────────────────────────────────── */
.cp { font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Header ───────────────────────────────────────── */
.cp-hdr {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:24px; gap:16px; flex-wrap:wrap;
}
.cp-hdr h2 { font-size:1.5rem; font-weight:800; color:var(--text,#0f172a); margin:0 0 2px; letter-spacing:-.5px; }
.cp-hdr p  { margin:0; color:var(--text3,#94a3b8); font-size:.85rem; }
.btn-add-cp {
    display:inline-flex; align-items:center; gap:7px;
    padding:10px 20px; border-radius:10px;
    background:#1e3a8a; color:#fff; font-size:.875rem; font-weight:700;
    border:none; cursor:pointer;
    box-shadow:0 2px 8px rgba(30,58,138,.25);
    transition:background .15s, transform .15s, box-shadow .15s;
}
.btn-add-cp:hover { background:#1e40af; transform:translateY(-1px); box-shadow:0 4px 14px rgba(30,58,138,.3); }

/* ── Stats ────────────────────────────────────────── */
.cp-stats {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:12px; margin-bottom:22px;
}
.cp-stat {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:12px; padding:14px 17px;
    display:flex; align-items:center; gap:11px;
    transition:box-shadow .15s, transform .15s;
}
.cp-stat:hover { box-shadow:0 6px 20px rgba(0,0,0,.07); transform:translateY(-1px); }
.cp-stat-ico { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.cp-stat-val { font-size:1.4rem; font-weight:900; color:var(--text,#0f172a); line-height:1; }
.cp-stat-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3,#94a3b8); margin-top:2px; }

/* ── Table card ───────────────────────────────────── */
.cp-card {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:14px; overflow:hidden;
}
.cp-card-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:15px 20px; border-bottom:1.5px solid var(--border,#e2e8f0);
}
.cp-card-title { font-size:.95rem; font-weight:800; color:var(--text,#0f172a); }
.cp-card-count {
    font-size:.75rem; font-weight:700; color:#1d4ed8;
    background:#dbeafe; border-radius:20px; padding:3px 10px;
}

.cp-table { width:100%; border-collapse:collapse; }
.cp-table thead tr { background:var(--hover,#f8fafc); }
.cp-table th {
    padding:10px 16px; font-size:.68rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.7px;
    color:var(--text3,#94a3b8); text-align:left;
    border-bottom:1.5px solid var(--border,#e2e8f0); white-space:nowrap;
}
.cp-table td {
    padding:14px 16px; font-size:.875rem;
    border-bottom:1px solid var(--border,#f1f5f9);
    vertical-align:middle; color:var(--text,#334155);
}
.cp-table tbody tr { transition:background .1s; }
.cp-table tbody tr:hover { background:var(--hover,#f8fafc); }
.cp-table tbody tr:last-child td { border-bottom:none; }

/* Code chip */
.code-chip {
    display:inline-flex; align-items:center;
    background:#eff6ff; color:#1d4ed8;
    border:1px solid #bfdbfe; border-radius:7px;
    font-size:.8rem; font-weight:800; padding:4px 11px;
    letter-spacing:.4px;
}

/* Course name + dept stacked */
.course-name { font-weight:700; color:var(--text,#0f172a); font-size:.9rem; }
.course-dept { font-size:.73rem; color:var(--text3,#94a3b8); margin-top:2px; display:flex; align-items:center; gap:4px; }

/* Count pills */
.count-pill {
    display:inline-flex; align-items:center; gap:5px;
    font-size:.75rem; font-weight:700; padding:4px 11px;
    border-radius:20px;
}
.cp-sections { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.cp-subjects { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }

/* Actions */
.cp-acts { display:flex; gap:6px; align-items:center; }
.btn-cp-edit {
    padding:5px 13px; border-radius:7px; font-size:.75rem; font-weight:700;
    border:1.5px solid var(--border,#e2e8f0); background:var(--card-bg,#fff);
    color:var(--text2,#475569); cursor:pointer;
    display:inline-flex; align-items:center; gap:4px;
    transition:border-color .15s, color .15s, background .15s;
}
.btn-cp-edit:hover { border-color:#3b82f6; color:#1d4ed8; background:#eff6ff; }
.btn-cp-del {
    padding:5px 10px; border-radius:7px; font-size:.75rem; font-weight:700;
    border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626;
    cursor:pointer; display:inline-flex; align-items:center; gap:3px;
    transition:background .15s;
}
.btn-cp-del:hover { background:#fee2e2; }
.btn-cp-del.busy { opacity:.5; pointer-events:none; }

/* ── Empty ────────────────────────────────────────── */
.cp-empty { text-align:center; padding:60px 24px; color:var(--text3,#94a3b8); }
.cp-empty .ei { font-size:3rem; display:block; margin-bottom:14px; }
.cp-empty p { margin:0; font-size:.9rem; }

/* ── Modal ────────────────────────────────────────── */
#cp-modal {
    display:none; position:fixed; inset:0;
    background:rgba(15,23,42,.5); z-index:600;
    align-items:center; justify-content:center; padding:20px;
    backdrop-filter:blur(2px);
}
#cp-modal.open { display:flex; }
.cp-modal-box {
    background:var(--card-bg,#fff); border-radius:16px;
    width:100%; max-width:560px;
    box-shadow:0 32px 80px rgba(0,0,0,.22);
    animation:cpUp .22s cubic-bezier(.16,1,.3,1);
}
@keyframes cpUp {
    from { transform:translateY(18px) scale(.97); opacity:0; }
    to   { transform:translateY(0)    scale(1);   opacity:1; }
}
.cp-modal-hdr {
    display:flex; align-items:center; justify-content:space-between;
    padding:19px 22px 14px;
    border-bottom:1.5px solid var(--border,#e2e8f0);
    border-radius:16px 16px 0 0;
}
.cp-modal-hdr-l { display:flex; align-items:center; gap:11px; }
.cp-modal-ico {
    width:36px; height:36px; border-radius:9px;
    background:linear-gradient(135deg,#dbeafe,#eff6ff);
    display:flex; align-items:center; justify-content:center; font-size:1rem;
}
.cp-modal-ttl { font-size:.95rem; font-weight:800; color:var(--text,#0f172a); }
.cp-modal-sub { font-size:.73rem; color:var(--text3,#94a3b8); margin-top:1px; }
.cp-modal-cls {
    background:none; border:none; width:32px; height:32px;
    display:flex; align-items:center; justify-content:center;
    font-size:1rem; color:var(--text3,#94a3b8); cursor:pointer;
    border-radius:8px; transition:background .15s, color .15s;
}
.cp-modal-cls:hover { background:var(--hover,#f1f5f9); color:var(--text,#0f172a); }
.cp-modal-body { padding:20px 22px 24px; }

.cp-fg { display:grid; grid-template-columns:1fr 1fr; gap:13px 16px; }
.cp-fg .span2 { grid-column:1/-1; }
.cp-lbl {
    display:block; font-size:.7rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.4px;
    color:var(--text2,#475569); margin-bottom:5px;
}
.cp-lbl .req { color:#ef4444; margin-left:2px; }
.cp-fc {
    width:100%; padding:9px 12px;
    border:1.5px solid var(--border,#e2e8f0); border-radius:9px;
    font-size:.875rem; color:var(--text,#1e293b);
    background:var(--input-bg,#f8fafc);
    transition:border-color .15s, box-shadow .15s; box-sizing:border-box;
}
.cp-fc:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.13); background:#fff; }

.cp-modal-ftr {
    display:flex; align-items:center; gap:10px;
    margin-top:18px; padding-top:15px;
    border-top:1.5px solid var(--border,#e2e8f0);
}
.btn-cp-save {
    padding:10px 22px; border-radius:9px; font-size:.875rem; font-weight:800;
    background:#1e3a8a; color:#fff; border:none; cursor:pointer;
    display:flex; align-items:center; gap:7px;
    box-shadow:0 2px 8px rgba(30,58,138,.2);
    transition:background .15s, transform .15s, opacity .15s;
}
.btn-cp-save:hover { background:#1e40af; transform:translateY(-1px); }
.btn-cp-save.busy  { opacity:.65; pointer-events:none; }
.btn-cp-cncl {
    padding:10px 16px; border-radius:9px; font-size:.875rem; font-weight:600;
    background:var(--hover,#f1f5f9); color:var(--text2,#475569);
    border:1.5px solid var(--border,#e2e8f0); cursor:pointer;
    transition:background .15s;
}
.btn-cp-cncl:hover { background:#e2e8f0; }

/* ── Toast ────────────────────────────────────────── */
#cp-toasts {
    position:fixed; top:20px; right:20px; z-index:9999;
    display:flex; flex-direction:column; gap:8px; pointer-events:none;
}
.cp-toast {
    display:flex; align-items:center; gap:10px;
    padding:12px 18px; border-radius:11px;
    font-size:.875rem; font-weight:600; max-width:360px;
    box-shadow:0 8px 30px rgba(0,0,0,.13);
    animation:cpTIn .25s cubic-bezier(.16,1,.3,1);
    pointer-events:auto;
}
.cp-toast.ok  { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }
.cp-toast.err { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; }
@keyframes cpTIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes cpTOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }

@media(max-width:860px){ .cp-stats{grid-template-columns:1fr 1fr} }
@media(max-width:540px){
    .cp-stats{grid-template-columns:1fr 1fr}
    .cp-fg{grid-template-columns:1fr}
    .cp-fg .span2{grid-column:1}
    .cp-hdr{flex-direction:column;align-items:flex-start}
}
</style>

<!-- Toast container -->
<div id="cp-toasts"></div>

<div class="cp">

  <!-- Header -->
  <div class="cp-hdr">
    <div>
      <h2>Course Management</h2>
      <p>Manage academic programs, departments, and their linked sections and subjects</p>
    </div>
    <button class="btn-add-cp" onclick="cpOpenModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Course
    </button>
  </div>

  <!-- Stats -->
  <div class="cp-stats">
    <div class="cp-stat">
      <div class="cp-stat-ico" style="background:#eff6ff">📚</div>
      <div><div class="cp-stat-val"><?= count($courses) ?></div><div class="cp-stat-lbl">Total Courses</div></div>
    </div>
    <div class="cp-stat">
      <div class="cp-stat-ico" style="background:#f0fdf4">🏫</div>
      <div><div class="cp-stat-val"><?= $totalSections ?></div><div class="cp-stat-lbl">Sections</div></div>
    </div>
    <div class="cp-stat">
      <div class="cp-stat-ico" style="background:#fff7ed">📋</div>
      <div><div class="cp-stat-val"><?= $totalSubjects ?></div><div class="cp-stat-lbl">Subjects</div></div>
    </div>
    <div class="cp-stat">
      <div class="cp-stat-ico" style="background:#faf5ff">🏛️</div>
      <div><div class="cp-stat-val"><?= count($depts) ?></div><div class="cp-stat-lbl">Departments</div></div>
    </div>
  </div>

  <!-- Table card -->
  <div class="cp-card">
    <div class="cp-card-head">
      <span class="cp-card-title">All Courses</span>
      <span class="cp-card-count"><?= count($courses) ?> course<?= count($courses)!=1?'s':'' ?></span>
    </div>
    <?php if (empty($courses)): ?>
      <div class="cp-empty">
        <span class="ei">📚</span>
        <p>No courses yet. <a href="#" onclick="cpOpenModal();return false" style="color:#3b82f6;font-weight:700;text-decoration:none">Add your first course →</a></p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="cp-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Course Name</th>
              <th style="text-align:center">Sections</th>
              <th style="text-align:center">Subjects</th>
              <th class="no-print" style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody id="cp-tbody">
          <?php foreach ($courses as $c): ?>
            <tr id="cp-row-<?= $c['id'] ?>">
              <td><span class="code-chip"><?= h($c['code']) ?></span></td>
              <td>
                <div class="course-name"><?= h($c['name']) ?></div>
                <div class="course-dept">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                  <?= h($c['department']) ?>
                </div>
              </td>
              <td style="text-align:center">
                <span class="count-pill cp-sections">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  <?= $c['section_count'] ?>
                </span>
              </td>
              <td style="text-align:center">
                <span class="count-pill cp-subjects">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                  <?= $c['subject_count'] ?>
                </span>
              </td>
              <td class="no-print">
                <div class="cp-acts" style="justify-content:center">
                  <button class="btn-cp-edit"
                    onclick="cpEditCourse(<?= htmlspecialchars(json_encode(['id'=>$c['id'],'code'=>$c['code'],'name'=>$c['name'],'department'=>$c['department']]),ENT_QUOTES) ?>)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                    Edit
                  </button>
                  <button class="btn-cp-del" onclick="cpDelete(<?= $c['id'] ?>,this)">
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

</div><!-- /cp -->

<!-- ═══════════════════════
     MODAL
═══════════════════════ -->
<div id="cp-modal" role="dialog" aria-modal="true">
  <div class="cp-modal-box" id="cp-modal-box">
    <div class="cp-modal-hdr">
      <div class="cp-modal-hdr-l">
        <div class="cp-modal-ico">📚</div>
        <div>
          <div class="cp-modal-ttl" id="cp-modal-ttl">Add New Course</div>
          <div class="cp-modal-sub">All fields are required</div>
        </div>
      </div>
      <button type="button" class="cp-modal-cls" id="cp-cls">✕</button>
    </div>
    <div class="cp-modal-body">
      <form id="cp-form">
        <input type="hidden" id="cp-action" name="action" value="create">
        <input type="hidden" id="cp-id"     name="id"     value="">

        <div class="cp-fg">
          <div>
            <label class="cp-lbl">Course Code <span class="req">*</span></label>
            <input id="cp-code" class="cp-fc" name="code" placeholder="e.g. BSIS" required maxlength="20">
          </div>
          <div>
            <label class="cp-lbl">Department <span class="req">*</span></label>
            <input id="cp-dept" class="cp-fc" name="department" placeholder="e.g. College of Computing" required>
          </div>
          <div class="span2">
            <label class="cp-lbl">Course Name <span class="req">*</span></label>
            <input id="cp-name" class="cp-fc" name="name" placeholder="e.g. Bachelor of Science in Information Systems" required>
          </div>
        </div>

        <div class="cp-modal-ftr">
          <button type="submit" class="btn-cp-save" id="cp-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Course
          </button>
          <button type="button" class="btn-cp-cncl" id="cp-cncl">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ── Toast ──────────────────────────────────────── */
function cpToast(msg, type) {
    const w = document.getElementById('cp-toasts');
    const t = document.createElement('div');
    t.className = 'cp-toast ' + (type==='ok'?'ok':'err');
    t.innerHTML = (type==='ok'?'✅ ':'❌ ') + msg;
    w.appendChild(t);
    setTimeout(()=>{ t.style.animation='cpTOut .3s ease forwards'; setTimeout(()=>t.remove(),300); }, 3500);
}

/* ── Modal ──────────────────────────────────────── */
const cpModal = document.getElementById('cp-modal');
const cpBox   = document.getElementById('cp-modal-box');
const cpTtl   = document.getElementById('cp-modal-ttl');

function cpOpenModal(title) {
    cpTtl.textContent = title || 'Add New Course';
    cpModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(()=>document.getElementById('cp-code').focus(), 80);
}
function cpCloseModal() {
    cpModal.classList.remove('open');
    document.body.style.overflow = '';
    cpResetForm();
}
function cpResetForm() {
    document.getElementById('cp-action').value = 'create';
    document.getElementById('cp-id').value     = '';
    document.getElementById('cp-code').value   = '';
    document.getElementById('cp-name').value   = '';
    document.getElementById('cp-dept').value   = '';
    cpTtl.textContent = 'Add New Course';
}

document.getElementById('cp-cls').addEventListener('click',  e=>{ e.preventDefault(); e.stopPropagation(); cpCloseModal(); });
document.getElementById('cp-cncl').addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); cpCloseModal(); });
cpModal.addEventListener('click', e=>{ if(e.target===cpModal) cpCloseModal(); });
cpBox.addEventListener('click',   e=>e.stopPropagation());
document.addEventListener('keydown', e=>{ if(e.key==='Escape'&&cpModal.classList.contains('open')) cpCloseModal(); });

/* ── Edit ───────────────────────────────────────── */
function cpEditCourse(c) {
    document.getElementById('cp-action').value = 'update';
    document.getElementById('cp-id').value     = c.id;
    document.getElementById('cp-code').value   = c.code;
    document.getElementById('cp-name').value   = c.name;
    document.getElementById('cp-dept').value   = c.department;
    cpOpenModal('Edit Course');
}

/* ── AJAX Save ──────────────────────────────────── */
document.getElementById('cp-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('cp-save');
    btn.classList.add('busy');
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:cpSpin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-6.49"/></svg> Saving…';

    try {
        const res  = await fetch('courses.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new FormData(this) });
        const json = await res.json();
        if (json.ok) {
            cpToast(json.msg, 'ok');
            cpCloseModal();
            setTimeout(()=>location.reload(), 700);
        } else {
            cpToast(json.msg, 'err');
        }
    } catch {
        cpToast('Network error. Please try again.', 'err');
    } finally {
        btn.classList.remove('busy');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Course';
    }
});

/* ── AJAX Delete ────────────────────────────────── */
async function cpDelete(id, btn) {
    if (!confirm('Delete this course? All linked sections and subjects may also be affected.')) return;
    btn.classList.add('busy');

    const fd = new FormData();
    fd.append('action','delete'); fd.append('id',id);

    try {
        const res  = await fetch('courses.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const json = await res.json();
        if (json.ok) {
            const row = document.getElementById('cp-row-'+id);
            if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(()=>row.remove(),250); }
            cpToast(json.msg,'ok');
        } else {
            cpToast(json.msg,'err');
            btn.classList.remove('busy');
        }
    } catch {
        cpToast('Network error.','err');
        btn.classList.remove('busy');
    }
}

/* Spinner keyframe */
const _s=document.createElement('style');
_s.textContent='@keyframes cpSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}';
document.head.appendChild(_s);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>