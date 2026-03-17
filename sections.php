<?php
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// ══════════════════════════════════════════════════════
// AJAX HANDLER
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    $action    = $_POST['action']     ?? '';
    $courseId  = (int)($_POST['course_id']   ?? 0);
    $yearLevel = sanitize($_POST['year_level'] ?? '');
    $name      = sanitize($_POST['name']      ?? '');
    $id        = (int)($_POST['id']           ?? 0);

    try {
        if ($action === 'create') {
            if (!$courseId || !$yearLevel || !$name) {
                echo json_encode(['ok'=>false,'msg'=>'All fields are required.']); exit;
            }
            $db->prepare("INSERT INTO sections (course_id,year_level,name) VALUES (?,?,?)")
               ->execute([$courseId,$yearLevel,$name]);
            echo json_encode(['ok'=>true,'msg'=>'Section added successfully.']);

        } elseif ($action === 'update') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("UPDATE sections SET course_id=?,year_level=?,name=? WHERE id=?")
               ->execute([$courseId,$yearLevel,$name,$id]);
            echo json_encode(['ok'=>true,'msg'=>'Section updated successfully.']);

        } elseif ($action === 'delete') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("DELETE FROM sections WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true,'msg'=>'Section deleted.']);

        } else {
            echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
        }
    } catch (PDOException $e) {
        $msg = str_contains($e->getMessage(),'Duplicate') ? 'Section name already exists.' : 'Database error: '.$e->getMessage();
        echo json_encode(['ok'=>false,'msg'=>$msg]);
    }
    exit;
}

// ══════════════════════════════════════════════════════
// PAGE LOAD
// ══════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Sections';
require_once __DIR__ . '/includes/header.php';

$courses = $db->query("SELECT id,code,name FROM courses ORDER BY code")->fetchAll();

$filterCourse = (int)($_GET['course_id']    ?? 0);
$filterYear   = sanitize($_GET['year_level'] ?? '');

$where = ['1=1']; $params = [];
if ($filterCourse) { $where[] = 's.course_id = ?'; $params[] = $filterCourse; }
if ($filterYear)   { $where[] = 's.year_level = ?'; $params[] = $filterYear; }

$stmt = $db->prepare("
    SELECT s.*, c.code AS c_code, c.name AS c_name
    FROM sections s
    JOIN courses c ON c.id = s.course_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.year_level, s.name
");
$stmt->execute($params);
$sections = $stmt->fetchAll();

$yearLevels = ['1st Year','2nd Year','3rd Year','4th Year'];
$yChipMap   = ['1st Year'=>'chip-green','2nd Year'=>'chip-blue','3rd Year'=>'chip-amber','4th Year'=>'chip-purple'];
?>

<style>
/* ── Toast ─────────────────────────────────────────── */
#sec-toasts {
    position:fixed; top:20px; right:20px; z-index:9999;
    display:flex; flex-direction:column; gap:8px; pointer-events:none;
}
.sec-toast {
    display:flex; align-items:center; gap:10px;
    padding:12px 18px; border-radius:11px;
    font-size:.875rem; font-weight:600; max-width:360px;
    box-shadow:0 8px 30px rgba(0,0,0,.13);
    animation:secTIn .25s cubic-bezier(.16,1,.3,1);
    pointer-events:auto;
}
.sec-toast.ok  { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }
.sec-toast.err { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; }
@keyframes secTIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes secTOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }

/* ── Modal overlay ─────────────────────────────────── */
#sec-modal {
    display:none;
    position:fixed; inset:0;
    background:rgba(15,23,42,.48);
    z-index:600;
    align-items:center; justify-content:center;
    padding:20px;
    backdrop-filter:blur(2px);
}
#sec-modal.open { display:flex; }

/* ── Modal box ─────────────────────────────────────── */
.sec-modal-box {
    background:var(--card-bg,#fff);
    border-radius:16px;
    width:100%; max-width:520px;
    box-shadow:0 32px 80px rgba(0,0,0,.22);
    animation:secMUp .22s cubic-bezier(.16,1,.3,1);
    position:relative;
}
@keyframes secMUp {
    from { transform:translateY(18px) scale(.97); opacity:0; }
    to   { transform:translateY(0)    scale(1);   opacity:1; }
}

/* ── Modal header ──────────────────────────────────── */
.sec-mhdr {
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 24px 15px;
    border-bottom:1.5px solid var(--border,#e2e8f0);
    border-radius:16px 16px 0 0;
}
.sec-mhdr-left { display:flex; align-items:center; gap:12px; }
.sec-mhdr-ico {
    width:38px; height:38px; border-radius:10px;
    background:linear-gradient(135deg,#dbeafe,#eff6ff);
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem;
}
.sec-mhdr-title { font-size:1rem; font-weight:800; color:var(--text,#0f172a); }
.sec-mhdr-sub   { font-size:.73rem; color:var(--text3,#94a3b8); margin-top:1px; }
.sec-mhdr-cls {
    background:none; border:none;
    width:32px; height:32px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.05rem; color:var(--text3,#94a3b8); cursor:pointer;
    transition:background .15s, color .15s;
}
.sec-mhdr-cls:hover { background:var(--hover,#f1f5f9); color:var(--text,#0f172a); }

/* ── Modal body ────────────────────────────────────── */
.sec-mbody { padding:22px 24px 26px; }

.sec-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; }
.sec-form-full { grid-column:1 / -1; }

.sec-lbl {
    display:block; font-size:.72rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px;
    color:var(--text2,#475569); margin-bottom:5px;
}
.sec-lbl .req { color:#ef4444; margin-left:2px; }

.sec-ctrl {
    width:100%; padding:9px 12px;
    border:1.5px solid var(--border,#e2e8f0); border-radius:9px;
    font-size:.875rem; color:var(--text,#1e293b);
    background:var(--input-bg,#f8fafc);
    transition:border-color .15s, box-shadow .15s;
    box-sizing:border-box;
}
.sec-ctrl:focus {
    outline:none; border-color:#3b82f6;
    box-shadow:0 0 0 3px rgba(59,130,246,.12);
    background:#fff;
}

/* ── Modal footer ──────────────────────────────────── */
.sec-mftr {
    display:flex; align-items:center; gap:10px;
    margin-top:20px; padding-top:16px;
    border-top:1.5px solid var(--border,#e2e8f0);
}
.btn-sec-save {
    padding:10px 22px; border-radius:9px; font-size:.875rem; font-weight:800;
    background:#1e3a8a; color:#fff; border:none; cursor:pointer;
    display:flex; align-items:center; gap:7px;
    box-shadow:0 2px 8px rgba(30,58,138,.22);
    transition:background .15s, transform .15s, opacity .15s;
}
.btn-sec-save:hover { background:#1e40af; transform:translateY(-1px); }
.btn-sec-save.busy  { opacity:.65; pointer-events:none; }
.btn-sec-cncl {
    padding:10px 16px; border-radius:9px; font-size:.875rem; font-weight:600;
    background:var(--hover,#f1f5f9); color:var(--text2,#475569);
    border:1.5px solid var(--border,#e2e8f0); cursor:pointer;
    transition:background .15s;
}
.btn-sec-cncl:hover { background:#e2e8f0; }

/* ── Row delete button ─────────────────────────────── */
.btn-del-row {
    padding:5px 10px; border-radius:7px; font-size:.75rem; font-weight:600;
    border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626;
    cursor:pointer; display:inline-flex; align-items:center; gap:3px;
    transition:background .15s;
}
.btn-del-row:hover { background:#fee2e2; }
.btn-del-row.busy  { opacity:.5; pointer-events:none; }

@keyframes secSpin { from{transform:rotate(0)} to{transform:rotate(360deg)} }
</style>

<!-- Toast container -->
<div id="sec-toasts"></div>

<!-- ═══════════════════════════════════
     PAGE HEADER
═══════════════════════════════════ -->
<div class="page-header">
  <div><h2>Section Management</h2><p>Manage course sections by year level</p></div>
  <button class="btn btn-primary" onclick="secOpenModal()">+ Add Section</button>
</div>

<!-- ═══════════════════════════════════
     FILTERS
═══════════════════════════════════ -->
<div class="filter-bar no-print">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="filter-group">
      <label>Course</label>
      <select name="course_id" class="form-control" onchange="this.form.submit()">
        <option value="">All Courses</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filterCourse==$c['id']?'selected':'' ?>><?= h($c['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-group">
      <label>Year Level</label>
      <select name="year_level" class="form-control" onchange="this.form.submit()">
        <option value="">All Years</option>
        <?php foreach ($yearLevels as $y): ?>
          <option value="<?= $y ?>" <?= $filterYear===$y?'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <a href="sections.php" class="btn btn-ghost btn-sm">Clear</a>
  </form>
</div>

<!-- ═══════════════════════════════════
     TABLE
═══════════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Sections (<?= count($sections) ?>)</span>
  </div>
  <div class="card-body no-pad">
    <?php if (empty($sections)): ?>
      <div class="empty-state">
        <span class="empty-icon">👥</span>
        <p>No sections found.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Section Name</th>
              <th>Course</th>
              <th>Year Level</th>
              <th class="no-print">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($sections as $s):
            $chip = $yChipMap[$s['year_level']] ?? 'chip-blue';
            $payload = htmlspecialchars(json_encode([
                'id'         => $s['id'],
                'course_id'  => $s['course_id'],
                'year_level' => $s['year_level'],
                'name'       => $s['name'],
            ]), ENT_QUOTES);
          ?>
          <tr id="sec-row-<?= $s['id'] ?>">
            <td style="color:var(--text);font-weight:600"><?= h($s['name']) ?></td>
            <td><span class="chip chip-blue"><?= h($s['c_code']) ?></span> <?= h($s['c_name']) ?></td>
            <td><span class="chip <?= $chip ?>"><?= h($s['year_level']) ?></span></td>
            <td class="no-print">
              <div class="actions">
                <button class="btn btn-ghost btn-sm"
                  onclick="secEdit(<?= $payload ?>)">
                  ✏ Edit
                </button>
                <button class="btn btn-danger btn-sm btn-del-row"
                  onclick="secDelete(<?= $s['id'] ?>, this)">
                  🗑 Delete
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

<!-- ═══════════════════════════════════
     MODAL
═══════════════════════════════════ -->
<div id="sec-modal" role="dialog" aria-modal="true">
  <div class="sec-modal-box" id="sec-modal-box">

    <!-- Header -->
    <div class="sec-mhdr">
      <div class="sec-mhdr-left">
        <div class="sec-mhdr-ico">👥</div>
        <div>
          <div class="sec-mhdr-title" id="sec-modal-title">Add New Section</div>
          <div class="sec-mhdr-sub">All fields are required</div>
        </div>
      </div>
      <button type="button" class="sec-mhdr-cls" id="sec-cls-btn" aria-label="Close">✕</button>
    </div>

    <!-- Body -->
    <div class="sec-mbody">
      <form id="sec-form">
        <input type="hidden" id="sec-action" name="action"    value="create">
        <input type="hidden" id="sec-id"     name="id"        value="">

        <div class="sec-form-grid">
          <!-- Course -->
          <div>
            <label class="sec-lbl">Course <span class="req">*</span></label>
            <select id="sec-course" name="course_id" class="sec-ctrl" required>
              <option value="">— Select Course —</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= h($c['code']) ?> – <?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Year Level -->
          <div>
            <label class="sec-lbl">Year Level <span class="req">*</span></label>
            <select id="sec-year" name="year_level" class="sec-ctrl" required>
              <?php foreach ($yearLevels as $y): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Section Name (full width) -->
          <div class="sec-form-full">
            <label class="sec-lbl">Section Name <span class="req">*</span></label>
            <input id="sec-name" class="sec-ctrl" name="name"
                   placeholder="e.g. BSIS 1101" required>
          </div>
        </div>

        <!-- Footer -->
        <div class="sec-mftr">
          <button type="submit" class="btn-sec-save" id="sec-save-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Section
          </button>
          <button type="button" class="btn-sec-cncl" id="sec-cncl-btn">Cancel</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
/* ─── Toast ────────────────────────────────────────── */
function secToast(msg, type) {
    const w = document.getElementById('sec-toasts');
    const t = document.createElement('div');
    t.className = 'sec-toast ' + (type === 'ok' ? 'ok' : 'err');
    t.innerHTML = (type === 'ok' ? '✅ ' : '❌ ') + msg;
    w.appendChild(t);
    setTimeout(() => {
        t.style.animation = 'secTOut .3s ease forwards';
        setTimeout(() => t.remove(), 300);
    }, 3500);
}

/* ─── Modal open / close ───────────────────────────── */
const secModal = document.getElementById('sec-modal');
const secBox   = document.getElementById('sec-modal-box');
const secTitle = document.getElementById('sec-modal-title');

function secOpenModal(title) {
    secTitle.textContent = title || 'Add New Section';
    secModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('sec-name').focus(), 80);
}

function secCloseModal() {
    secModal.classList.remove('open');
    document.body.style.overflow = '';
    // Reset to "Add" state
    document.getElementById('sec-action').value = 'create';
    document.getElementById('sec-id').value     = '';
    document.getElementById('sec-course').value = '';
    document.getElementById('sec-year').value   = '1st Year';
    document.getElementById('sec-name').value   = '';
    secTitle.textContent = 'Add New Section';
}

// Wire up close triggers
document.getElementById('sec-cls-btn').addEventListener('click',  function(e){ e.preventDefault(); e.stopPropagation(); secCloseModal(); });
document.getElementById('sec-cncl-btn').addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); secCloseModal(); });
secModal.addEventListener('click', function(e){ if (e.target === secModal) secCloseModal(); });
secBox.addEventListener('click',   function(e){ e.stopPropagation(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && secModal.classList.contains('open')) secCloseModal(); });

/* ─── Edit — populate modal ────────────────────────── */
function secEdit(s) {
    document.getElementById('sec-action').value = 'update';
    document.getElementById('sec-id').value     = s.id;
    document.getElementById('sec-course').value = s.course_id;
    document.getElementById('sec-year').value   = s.year_level;
    document.getElementById('sec-name').value   = s.name;
    secOpenModal('Edit Section');
}

/* ─── AJAX Save ────────────────────────────────────── */
document.getElementById('sec-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('sec-save-btn');
    btn.classList.add('busy');
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:secSpin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-6.49"/></svg> Saving…';

    try {
        const res  = await fetch('sections.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(this)
        });
        const json = await res.json();

        if (json.ok) {
            secToast(json.msg, 'ok');
            secCloseModal();
            setTimeout(() => location.reload(), 750);
        } else {
            secToast(json.msg, 'err');
        }
    } catch {
        secToast('Network error. Please try again.', 'err');
    } finally {
        btn.classList.remove('busy');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Section';
    }
});

/* ─── AJAX Delete ──────────────────────────────────── */
async function secDelete(id, btn) {
    if (!confirm('Delete this section? This may affect linked schedules.')) return;
    btn.classList.add('busy');

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    try {
        const res  = await fetch('sections.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const json = await res.json();

        if (json.ok) {
            const row = document.getElementById('sec-row-' + id);
            if (row) {
                row.style.transition = 'opacity .25s';
                row.style.opacity    = '0';
                setTimeout(() => row.remove(), 250);
            }
            secToast(json.msg, 'ok');
        } else {
            secToast(json.msg, 'err');
            btn.classList.remove('busy');
        }
    } catch {
        secToast('Network error.', 'err');
        btn.classList.remove('busy');
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>