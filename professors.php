<?php
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// ══════════════════════════════════════════════════════
// AJAX HANDLER
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    $action     = $_POST['action']          ?? '';
    $name       = sanitize($_POST['name']           ?? '');
    $empId      = sanitize($_POST['employee_id']    ?? '');
    $dept       = sanitize($_POST['department']     ?? '');
    $spec       = sanitize($_POST['specialization'] ?? '');
    $empType    = sanitize($_POST['employment_type'] ?? 'Full Time');
    $id         = (int)($_POST['id']                ?? 0);

    // Validate employment type
    if (!in_array($empType, ['Full Time', 'Part Time'])) $empType = 'Full Time';

    try {
        if ($action === 'create') {
            if (!$name) { echo json_encode(['ok'=>false,'msg'=>'Full name is required.']); exit; }
            $db->prepare("INSERT INTO professors (name,employee_id,department,specialization,employment_type) VALUES (?,?,?,?,?)")
               ->execute([$name,$empId,$dept,$spec,$empType]);
            echo json_encode(['ok'=>true,'msg'=>'Professor added successfully.']);

        } elseif ($action === 'update') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("UPDATE professors SET name=?,employee_id=?,department=?,specialization=?,employment_type=? WHERE id=?")
               ->execute([$name,$empId,$dept,$spec,$empType,$id]);
            echo json_encode(['ok'=>true,'msg'=>'Professor updated successfully.']);

        } elseif ($action === 'delete') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("DELETE FROM professors WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true,'msg'=>'Professor deleted.']);

        } else {
            echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Database error: '.$e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════
// PAGE LOAD
// ══════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Professors';
require_once __DIR__ . '/includes/header.php';

/* ── Auto-add employment_type column if missing ── */
try {
    $db->exec("ALTER TABLE professors ADD COLUMN employment_type VARCHAR(20) NOT NULL DEFAULT 'Full Time' AFTER specialization");
} catch (Exception $e) { /* already exists */ }

$professors = $db->query("SELECT * FROM professors ORDER BY employment_type, name")->fetchAll();

/* Active tab from URL: all | fulltime | parttime */
$activeTab = sanitize($_GET['tab'] ?? 'all');
if (!in_array($activeTab, ['all','fulltime','parttime'])) $activeTab = 'all';

// Stats
$depts      = array_filter(array_unique(array_column($professors,'department')));
$totalDepts = count($depts);
$withSched  = (int)$db->query("SELECT COUNT(DISTINCT professor_id) FROM schedules")->fetchColumn();
$fullTime   = count(array_filter($professors, fn($p) => ($p['employment_type'] ?? 'Full Time') === 'Full Time'));
$partTime   = count(array_filter($professors, fn($p) => ($p['employment_type'] ?? 'Full Time') === 'Part Time'));
?>

<style>
/* ── Base ─────────────────────────────────────────── */
.pp { font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Header ───────────────────────────────────────── */
.pp-hdr {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:24px; gap:16px; flex-wrap:wrap;
}
.pp-hdr h2 { font-size:1.5rem; font-weight:800; color:var(--text,#0f172a); margin:0 0 2px; letter-spacing:-.5px; }
.pp-hdr p  { margin:0; color:var(--text3,#94a3b8); font-size:.85rem; }
.btn-add-pp {
    display:inline-flex; align-items:center; gap:7px;
    padding:10px 20px; border-radius:10px;
    background:#1e3a8a; color:#fff; font-size:.875rem; font-weight:700;
    border:none; cursor:pointer;
    box-shadow:0 2px 8px rgba(30,58,138,.25);
    transition:background .15s, transform .15s, box-shadow .15s;
}
.btn-add-pp:hover { background:#1e40af; transform:translateY(-1px); box-shadow:0 4px 14px rgba(30,58,138,.3); }

/* ── Stats ────────────────────────────────────────── */
.pp-stats {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:12px; margin-bottom:22px;
}
.pp-stat {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:12px; padding:14px 17px;
    display:flex; align-items:center; gap:11px;
    transition:box-shadow .15s, transform .15s;
}
.pp-stat:hover { box-shadow:0 6px 20px rgba(0,0,0,.07); transform:translateY(-1px); }
.pp-stat-ico { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.pp-stat-val { font-size:1.4rem; font-weight:900; color:var(--text,#0f172a); line-height:1; }
.pp-stat-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3,#94a3b8); margin-top:2px; }

/* ── Type tabs ────────────────────────────────────── */
.pp-tabs {
    display:flex; gap:4px;
    border-bottom:2px solid var(--border,#e2e8f0);
    margin-bottom:20px; overflow-x:auto;
}
.pp-tab {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 18px; border-radius:8px 8px 0 0;
    font-size:.875rem; font-weight:600;
    color:var(--text3,#94a3b8); text-decoration:none;
    white-space:nowrap; border:1.5px solid transparent;
    border-bottom:none; margin-bottom:-2px;
    transition:color .15s, background .15s;
}
.pp-tab:hover { color:var(--text,#0f172a); background:var(--hover,#f5f5f5); }
.pp-tab.active {
    color:#1d4ed8; background:var(--card-bg,#fff);
    border-color:var(--border,#e2e8f0);
    border-bottom-color:var(--card-bg,#fff);
}
.pp-tab-count {
    font-size:.7rem; font-weight:800;
    background:var(--hover,#eee); border-radius:20px;
    padding:1px 7px; color:var(--text3,#888);
}
.pp-tab.active .pp-tab-count { background:#dbeafe; color:#1d4ed8; }
.pp-tab.tab-ft.active  { color:#166534; }
.pp-tab.tab-ft.active .pp-tab-count { background:#dcfce7; color:#166534; }
.pp-tab.tab-pt.active  { color:#c2410c; }
.pp-tab.tab-pt.active .pp-tab-count { background:#ffedd5; color:#c2410c; }

/* ── Search bar ───────────────────────────────────── */
.pp-search-wrap {
    display:flex; gap:10px; align-items:center;
    margin-bottom:18px; flex-wrap:wrap;
}
.pp-search-box {
    display:flex; align-items:center;
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:10px; overflow:hidden;
    flex:1; min-width:220px; max-width:400px;
    transition:border-color .15s, box-shadow .15s;
}
.pp-search-box:focus-within { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.pp-search-ico { padding:0 12px; color:var(--text3,#94a3b8); display:flex; align-items:center; flex-shrink:0; }
.pp-search-input { border:none; outline:none; background:transparent; padding:10px 12px 10px 0; font-size:.875rem; color:var(--text,#1e293b); width:100%; }
.pp-search-input::placeholder { color:var(--text3,#94a3b8); }
.pp-search-clear { padding:0 12px; color:var(--text3,#94a3b8); background:none; border:none; cursor:pointer; display:none; align-items:center; font-size:1rem; transition:color .15s; }
.pp-search-clear:hover { color:var(--text,#0f172a); }
.pp-search-clear.visible { display:flex; }
.pp-filter-dept { padding:9px 12px; border:1.5px solid var(--border,#e2e8f0); border-radius:9px; font-size:.85rem; color:var(--text,#1e293b); background:var(--card-bg,#fff); cursor:pointer; transition:border-color .15s; min-width:160px; }
.pp-filter-dept:focus { outline:none; border-color:#3b82f6; }
.pp-result-count { font-size:.78rem; color:var(--text3,#94a3b8); font-weight:600; margin-left:auto; align-self:center; }

/* ── Table card ───────────────────────────────────── */
.pp-card { background:var(--card-bg,#fff); border:1.5px solid var(--border,#e2e8f0); border-radius:14px; overflow:hidden; }
.pp-card-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1.5px solid var(--border,#e2e8f0); }
.pp-card-title { font-size:.95rem; font-weight:800; color:var(--text,#0f172a); }
.pp-card-count { font-size:.75rem; font-weight:700; color:#1d4ed8; background:#dbeafe; border-radius:20px; padding:3px 10px; }

/* Table */
.pp-table { width:100%; border-collapse:collapse; }
.pp-table thead tr { background:var(--hover,#f8fafc); }
.pp-table th { padding:10px 16px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--text3,#94a3b8); text-align:left; border-bottom:1.5px solid var(--border,#e2e8f0); white-space:nowrap; }
.pp-table td { padding:13px 16px; font-size:.875rem; border-bottom:1px solid var(--border,#f1f5f9); vertical-align:middle; color:var(--text,#334155); }
.pp-table tbody tr { transition:background .1s; }
.pp-table tbody tr:hover { background:var(--hover,#f8fafc); }
.pp-table tbody tr:last-child td { border-bottom:none; }
.pp-table tbody tr.hidden { display:none; }

/* Avatar */
.pp-name-cell { display:flex; align-items:center; gap:11px; }
.pp-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:800; flex-shrink:0; letter-spacing:.3px; font-family:'DM Mono','Courier New',monospace; }
.pp-avatar.ft { background:linear-gradient(135deg,#dcfce7,#f0fdf4); color:#166534; }
.pp-avatar.pt { background:linear-gradient(135deg,#ffedd5,#fff7ed); color:#c2410c; }
.pp-name    { font-weight:700; color:var(--text,#0f172a); font-size:.9rem; }
.pp-emp-sub { font-size:.72rem; color:var(--text3,#94a3b8); margin-top:1px; }

/* Employment type badge */
.emp-badge {
    display:inline-flex; align-items:center; gap:4px;
    font-size:.68rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.5px; padding:3px 9px; border-radius:20px;
    white-space:nowrap;
}
.emp-badge.ft { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.emp-badge.pt { background:#ffedd5; color:#c2410c; border:1px solid #fed7aa; }

/* Dept / spec chips */
.dept-chip { display:inline-flex; align-items:center; gap:5px; background:#f5f3ff; color:#5b21b6; border:1px solid #ede9fe; border-radius:6px; font-size:.75rem; font-weight:700; padding:3px 10px; }
.spec-text { font-size:.82rem; color:var(--text2,#475569); font-style:italic; }
.no-val    { color:var(--text3,#94a3b8); font-size:.8rem; }

/* Actions */
.pp-acts { display:flex; gap:6px; align-items:center; justify-content:center; }
.btn-pp-edit { padding:5px 13px; border-radius:7px; font-size:.75rem; font-weight:700; border:1.5px solid var(--border,#e2e8f0); background:var(--card-bg,#fff); color:var(--text2,#475569); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:border-color .15s, color .15s, background .15s; }
.btn-pp-edit:hover { border-color:#3b82f6; color:#1d4ed8; background:#eff6ff; }
.btn-pp-del { padding:5px 10px; border-radius:7px; font-size:.75rem; font-weight:700; border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626; cursor:pointer; display:inline-flex; align-items:center; gap:3px; transition:background .15s; }
.btn-pp-del:hover { background:#fee2e2; }
.btn-pp-del.busy { opacity:.5; pointer-events:none; }

/* No results / empty */
.pp-no-results { text-align:center; padding:48px 20px; color:var(--text3,#94a3b8); display:none; }
.pp-no-results .ei { font-size:2.5rem; display:block; margin-bottom:10px; }
.pp-no-results p { margin:0; font-size:.9rem; }
.pp-empty { text-align:center; padding:60px 24px; color:var(--text3,#94a3b8); }
.pp-empty .ei { font-size:3rem; display:block; margin-bottom:14px; }
.pp-empty p { margin:0; font-size:.9rem; }

/* ── Modal ────────────────────────────────────────── */
#pp-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:600; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(2px); }
#pp-modal.open { display:flex; }
.pp-modal-box { background:var(--card-bg,#fff); border-radius:16px; width:100%; max-width:560px; box-shadow:0 32px 80px rgba(0,0,0,.22); animation:ppUp .22s cubic-bezier(.16,1,.3,1); }
@keyframes ppUp { from{transform:translateY(18px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
.pp-mhdr { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 15px; border-bottom:1.5px solid var(--border,#e2e8f0); border-radius:16px 16px 0 0; }
.pp-mhdr-left { display:flex; align-items:center; gap:12px; }
.pp-mhdr-ico { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#dbeafe,#eff6ff); display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.pp-mhdr-ttl { font-size:1rem; font-weight:800; color:var(--text,#0f172a); }
.pp-mhdr-sub { font-size:.73rem; color:var(--text3,#94a3b8); margin-top:1px; }
.pp-mhdr-cls { background:none; border:none; width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-size:1rem; color:var(--text3,#94a3b8); cursor:pointer; border-radius:8px; transition:background .15s,color .15s; }
.pp-mhdr-cls:hover { background:var(--hover,#f1f5f9); color:var(--text,#0f172a); }
.pp-mbody { padding:20px 24px 26px; }

.pp-fg { display:grid; grid-template-columns:1fr 1fr; gap:13px 16px; }
.pp-fg .full { grid-column:1/-1; }
.pp-lbl { display:block; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.4px; color:var(--text2,#475569); margin-bottom:5px; }
.pp-lbl .req { color:#ef4444; margin-left:2px; }
.pp-fc { width:100%; padding:9px 12px; border:1.5px solid var(--border,#e2e8f0); border-radius:9px; font-size:.875rem; color:var(--text,#1e293b); background:var(--input-bg,#f8fafc); transition:border-color .15s, box-shadow .15s; box-sizing:border-box; }
.pp-fc:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); background:#fff; }

/* Employment type toggle inside modal */
.emp-type-section { margin-bottom:0; }
.emp-type-label { display:block; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.4px; color:var(--text2,#475569); margin-bottom:7px; }
.emp-type-toggle { display:flex; gap:8px; }
.emp-type-btn {
    flex:1; padding:10px 12px; border-radius:9px; font-size:.82rem; font-weight:700;
    border:1.5px solid var(--border,#e2e8f0); background:var(--card-bg,#fff);
    color:var(--text2,#555); cursor:pointer; transition:all .15s;
    text-align:center; display:flex; align-items:center; justify-content:center; gap:6px;
}
.emp-type-btn:hover { border-color:#3b82f6; }
.emp-type-btn.sel-ft { background:#f0fdf4; color:#166534; border-color:#86efac; box-shadow:0 0 0 3px rgba(134,239,172,.2); }
.emp-type-btn.sel-pt { background:#fff7ed; color:#c2410c; border-color:#fdba74; box-shadow:0 0 0 3px rgba(253,186,116,.2); }

.pp-mftr { display:flex; align-items:center; gap:10px; margin-top:18px; padding-top:15px; border-top:1.5px solid var(--border,#e2e8f0); }
.btn-pp-save { padding:10px 22px; border-radius:9px; font-size:.875rem; font-weight:800; background:#1e3a8a; color:#fff; border:none; cursor:pointer; display:flex; align-items:center; gap:7px; box-shadow:0 2px 8px rgba(30,58,138,.2); transition:background .15s,transform .15s,opacity .15s; }
.btn-pp-save:hover { background:#1e40af; transform:translateY(-1px); }
.btn-pp-save.busy  { opacity:.65; pointer-events:none; }
.btn-pp-cncl { padding:10px 16px; border-radius:9px; font-size:.875rem; font-weight:600; background:var(--hover,#f1f5f9); color:var(--text2,#475569); border:1.5px solid var(--border,#e2e8f0); cursor:pointer; transition:background .15s; }
.btn-pp-cncl:hover { background:#e2e8f0; }

/* ── Toast ────────────────────────────────────────── */
#pp-toasts { position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.pp-toast { display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:11px; font-size:.875rem; font-weight:600; max-width:360px; box-shadow:0 8px 30px rgba(0,0,0,.13); animation:ppTIn .25s cubic-bezier(.16,1,.3,1); pointer-events:auto; }
.pp-toast.ok  { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }
.pp-toast.err { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; }
@keyframes ppTIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes ppTOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }

.pp-hl { background:#fef9c3; color:#854d0e; border-radius:2px; padding:0 1px; }

@media(max-width:860px){ .pp-stats{grid-template-columns:1fr 1fr} }
@media(max-width:540px){
    .pp-fg{grid-template-columns:1fr} .pp-fg .full{grid-column:1}
    .pp-hdr{flex-direction:column;align-items:flex-start}
    .pp-stats{grid-template-columns:1fr 1fr}
    .emp-type-toggle{flex-direction:column}
}
@keyframes ppSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
</style>

<div id="pp-toasts"></div>

<div class="pp">

  <!-- Header -->
  <div class="pp-hdr">
    <div>
      <h2>Professor Management</h2>
      <p>Manage faculty members, departments, and employment type</p>
    </div>
    <button class="btn-add-pp" onclick="ppOpenModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Professor
    </button>
  </div>

  <!-- Stats -->
  <div class="pp-stats">
    <div class="pp-stat">
      <div class="pp-stat-ico" style="background:#eff6ff">👨‍🏫</div>
      <div><div class="pp-stat-val"><?= count($professors) ?></div><div class="pp-stat-lbl">Total Professors</div></div>
    </div>
    <div class="pp-stat">
      <div class="pp-stat-ico" style="background:#f0fdf4">🟢</div>
      <div><div class="pp-stat-val"><?= $fullTime ?></div><div class="pp-stat-lbl">Full Time</div></div>
    </div>
    <div class="pp-stat">
      <div class="pp-stat-ico" style="background:#fff7ed">🟠</div>
      <div><div class="pp-stat-val"><?= $partTime ?></div><div class="pp-stat-lbl">Part Time</div></div>
    </div>
    <div class="pp-stat">
      <div class="pp-stat-ico" style="background:#faf5ff">🏛️</div>
      <div><div class="pp-stat-val"><?= $totalDepts ?></div><div class="pp-stat-lbl">Departments</div></div>
    </div>
  </div>

  <!-- Employment Type Tabs -->
  <div class="pp-tabs no-print">
    <a href="professors.php?tab=all"
       class="pp-tab <?= $activeTab==='all' ? 'active' : '' ?>">
      👨‍🏫 All Professors
      <span class="pp-tab-count"><?= count($professors) ?></span>
    </a>
    <a href="professors.php?tab=fulltime"
       class="pp-tab tab-ft <?= $activeTab==='fulltime' ? 'active' : '' ?>">
      🟢 Full Time
      <span class="pp-tab-count"><?= $fullTime ?></span>
    </a>
    <a href="professors.php?tab=parttime"
       class="pp-tab tab-pt <?= $activeTab==='parttime' ? 'active' : '' ?>">
      🟠 Part Time
      <span class="pp-tab-count"><?= $partTime ?></span>
    </a>
  </div>

  <?php
  /* Filter professors by active tab */
  $displayProfessors = $professors;
  if ($activeTab === 'fulltime') {
      $displayProfessors = array_filter($professors, fn($p) => ($p['employment_type'] ?? 'Full Time') === 'Full Time');
  } elseif ($activeTab === 'parttime') {
      $displayProfessors = array_filter($professors, fn($p) => ($p['employment_type'] ?? 'Full Time') === 'Part Time');
  }
  $displayProfessors = array_values($displayProfessors);
  ?>

  <!-- Search bar -->
  <div class="pp-search-wrap no-print">
    <div class="pp-search-box">
      <span class="pp-search-ico">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </span>
      <input type="text" id="pp-search" class="pp-search-input"
             placeholder="Search by name, ID, department, or specialization…"
             oninput="ppSearch(this.value)" autocomplete="off">
      <button class="pp-search-clear" id="pp-clear" onclick="ppClearSearch()" title="Clear search">✕</button>
    </div>
    <select id="pp-dept-filter" class="pp-filter-dept" onchange="ppSearch(document.getElementById('pp-search').value)">
      <option value="">All Departments</option>
      <?php foreach ($depts as $d): ?>
        <option value="<?= h($d) ?>"><?= h($d) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="pp-result-count" id="pp-result-count"><?= count($displayProfessors) ?> professor<?= count($displayProfessors)!==1?'s':'' ?></span>
  </div>

  <!-- Table card -->
  <div class="pp-card">
    <div class="pp-card-head">
      <span class="pp-card-title">
        <?php
        if ($activeTab === 'fulltime') echo 'Full Time Faculty';
        elseif ($activeTab === 'parttime') echo 'Part Time Faculty';
        else echo 'Faculty List';
        ?>
      </span>
      <span class="pp-card-count" id="pp-table-count"><?= count($displayProfessors) ?> total</span>
    </div>

    <?php if (empty($displayProfessors)): ?>
      <div class="pp-empty">
        <span class="ei">👨‍🏫</span>
        <p>
          <?php if ($activeTab === 'fulltime'): ?>No full time professors yet.
          <?php elseif ($activeTab === 'parttime'): ?>No part time professors yet.
          <?php else: ?>No professors yet. <a href="#" onclick="ppOpenModal();return false" style="color:#3b82f6;font-weight:700;text-decoration:none">Add your first professor →</a><?php endif; ?>
        </p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table class="pp-table">
          <thead>
            <tr>
              <th>Professor</th>
              <th>Employment</th>
              <th>Department</th>
              <th>Specialization</th>
              <th class="no-print" style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody id="pp-tbody">
          <?php foreach ($displayProfessors as $p):
            $words    = explode(' ', $p['name']);
            $initials = strtoupper(substr($words[0],0,1) . (isset($words[1]) ? substr($words[1],0,1) : ''));
            $empType  = $p['employment_type'] ?? 'Full Time';
            $isFT     = $empType === 'Full Time';
            $payload  = htmlspecialchars(json_encode([
                'id'              => $p['id'],
                'name'            => $p['name'],
                'employee_id'     => $p['employee_id']      ?? '',
                'department'      => $p['department']       ?? '',
                'specialization'  => $p['specialization']   ?? '',
                'employment_type' => $empType,
            ]), ENT_QUOTES);
          ?>
          <tr id="pp-row-<?= $p['id'] ?>"
              data-name="<?= strtolower(h($p['name'])) ?>"
              data-empid="<?= strtolower(h($p['employee_id'] ?? '')) ?>"
              data-dept="<?= strtolower(h($p['department'] ?? '')) ?>"
              data-spec="<?= strtolower(h($p['specialization'] ?? '')) ?>"
              data-emptype="<?= strtolower(h($empType)) ?>">
            <td>
              <div class="pp-name-cell">
                <div class="pp-avatar <?= $isFT ? 'ft' : 'pt' ?>"><?= $initials ?></div>
                <div>
                  <div class="pp-name" data-field="name"><?= h($p['name']) ?></div>
                  <?php if ($p['employee_id']): ?>
                    <div class="pp-emp-sub" data-field="empid">ID: <?= h($p['employee_id']) ?></div>
                  <?php else: ?>
                    <div class="pp-emp-sub no-val">No Employee ID</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <span class="emp-badge <?= $isFT ? 'ft' : 'pt' ?>">
                <?= $isFT ? '🟢 Full Time' : '🟠 Part Time' ?>
              </span>
            </td>
            <td>
              <?php if ($p['department']): ?>
                <span class="dept-chip" data-field="dept">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                  <?= h($p['department']) ?>
                </span>
              <?php else: ?>
                <span class="no-val">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($p['specialization']): ?>
                <span class="spec-text" data-field="spec"><?= h($p['specialization']) ?></span>
              <?php else: ?>
                <span class="no-val">—</span>
              <?php endif; ?>
            </td>
            <td class="no-print">
              <div class="pp-acts">
                <button class="btn-pp-edit" onclick="ppEdit(<?= $payload ?>)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                  Edit
                </button>
                <button class="btn-pp-del" onclick="ppDelete(<?= $p['id'] ?>, this)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="pp-no-results" id="pp-no-results">
        <span class="ei">🔍</span>
        <p>No professors match your search.</p>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ═══════════════════════
     MODAL
═══════════════════════ -->
<div id="pp-modal" role="dialog" aria-modal="true">
  <div class="pp-modal-box" id="pp-modal-box">
    <div class="pp-mhdr">
      <div class="pp-mhdr-left">
        <div class="pp-mhdr-ico">👨‍🏫</div>
        <div>
          <div class="pp-mhdr-ttl" id="pp-modal-ttl">Add New Professor</div>
          <div class="pp-mhdr-sub">Full name is required</div>
        </div>
      </div>
      <button type="button" class="pp-mhdr-cls" id="pp-cls">✕</button>
    </div>
    <div class="pp-mbody">
      <form id="pp-form">
        <input type="hidden" id="pp-action"   name="action"          value="create">
        <input type="hidden" id="pp-id"       name="id"              value="">
        <input type="hidden" id="pp-emp-type" name="employment_type" value="Full Time">

        <div class="pp-fg">
          <!-- Full Name -->
          <div class="full">
            <label class="pp-lbl">Full Name <span class="req">*</span></label>
            <input id="pp-name" class="pp-fc" name="name" placeholder="e.g. Prof. Juan Cruz" required>
          </div>

          <!-- Employee ID -->
          <div>
            <label class="pp-lbl">Employee ID</label>
            <input id="pp-empid" class="pp-fc" name="employee_id" placeholder="e.g. EMP-001">
          </div>

          <!-- Department -->
          <div>
            <label class="pp-lbl">Department</label>
            <input id="pp-dept" class="pp-fc" name="department" placeholder="e.g. IT Department">
          </div>

          <!-- Specialization -->
          <div class="full">
            <label class="pp-lbl">Specialization</label>
            <input id="pp-spec" class="pp-fc" name="specialization" placeholder="e.g. Programming, Networking, Database">
          </div>

          <!-- Employment Type -->
          <div class="full emp-type-section">
            <span class="emp-type-label">Employment Type</span>
            <div class="emp-type-toggle">
              <button type="button" class="emp-type-btn sel-ft" id="emp-btn-ft" onclick="setEmpType('Full Time')">
                🟢 Full Time
              </button>
              <button type="button" class="emp-type-btn" id="emp-btn-pt" onclick="setEmpType('Part Time')">
                🟠 Part Time
              </button>
            </div>
          </div>
        </div>

        <div class="pp-mftr">
          <button type="submit" class="btn-pp-save" id="pp-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Professor
          </button>
          <button type="button" class="btn-pp-cncl" id="pp-cncl">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ── Toast ── */
function ppToast(msg, type) {
    const w = document.getElementById('pp-toasts');
    const t = document.createElement('div');
    t.className = 'pp-toast ' + (type==='ok'?'ok':'err');
    t.innerHTML = (type==='ok'?'✅ ':'❌ ') + msg;
    w.appendChild(t);
    setTimeout(()=>{ t.style.animation='ppTOut .3s ease forwards'; setTimeout(()=>t.remove(),300); }, 3500);
}

/* ── Employment type toggle ── */
function setEmpType(type) {
    document.getElementById('pp-emp-type').value = type;
    const btnFT = document.getElementById('emp-btn-ft');
    const btnPT = document.getElementById('emp-btn-pt');
    if (type === 'Full Time') {
        btnFT.className = 'emp-type-btn sel-ft';
        btnPT.className = 'emp-type-btn';
    } else {
        btnFT.className = 'emp-type-btn';
        btnPT.className = 'emp-type-btn sel-pt';
    }
}

/* ── Modal ── */
const ppModal = document.getElementById('pp-modal');
const ppBox   = document.getElementById('pp-modal-box');
const ppTtl   = document.getElementById('pp-modal-ttl');

function ppOpenModal(title) {
    ppTtl.textContent = title || 'Add New Professor';
    ppModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(()=>document.getElementById('pp-name').focus(), 80);
}
function ppCloseModal() {
    ppModal.classList.remove('open');
    document.body.style.overflow = '';
    ppResetForm();
}
function ppResetForm() {
    document.getElementById('pp-action').value  = 'create';
    document.getElementById('pp-id').value      = '';
    document.getElementById('pp-name').value    = '';
    document.getElementById('pp-empid').value   = '';
    document.getElementById('pp-dept').value    = '';
    document.getElementById('pp-spec').value    = '';
    setEmpType('Full Time');
    ppTtl.textContent = 'Add New Professor';
}

document.getElementById('pp-cls').addEventListener('click',  e=>{ e.preventDefault(); e.stopPropagation(); ppCloseModal(); });
document.getElementById('pp-cncl').addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); ppCloseModal(); });
ppModal.addEventListener('click', e=>{ if(e.target===ppModal) ppCloseModal(); });
ppBox.addEventListener('click',   e=>e.stopPropagation());
document.addEventListener('keydown', e=>{ if(e.key==='Escape'&&ppModal.classList.contains('open')) ppCloseModal(); });

/* ── Edit ── */
function ppEdit(p) {
    document.getElementById('pp-action').value = 'update';
    document.getElementById('pp-id').value     = p.id;
    document.getElementById('pp-name').value   = p.name;
    document.getElementById('pp-empid').value  = p.employee_id;
    document.getElementById('pp-dept').value   = p.department;
    document.getElementById('pp-spec').value   = p.specialization;
    setEmpType(p.employment_type || 'Full Time');
    ppOpenModal('Edit Professor');
}

/* ── AJAX Save ── */
document.getElementById('pp-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('pp-save');
    btn.classList.add('busy');
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:ppSpin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-6.49"/></svg> Saving…';
    try {
        const res  = await fetch('professors.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new FormData(this) });
        const json = await res.json();
        if (json.ok) {
            ppToast(json.msg, 'ok');
            ppCloseModal();
            setTimeout(()=>location.reload(), 700);
        } else {
            ppToast(json.msg, 'err');
        }
    } catch {
        ppToast('Network error. Please try again.', 'err');
    } finally {
        btn.classList.remove('busy');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Professor';
    }
});

/* ── AJAX Delete ── */
async function ppDelete(id, btn) {
    if (!confirm('Delete this professor? Their schedules may be affected.')) return;
    btn.classList.add('busy');
    const fd = new FormData();
    fd.append('action','delete'); fd.append('id',id);
    try {
        const res  = await fetch('professors.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const json = await res.json();
        if (json.ok) {
            const row = document.getElementById('pp-row-'+id);
            if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(()=>{ row.remove(); ppUpdateCount(); },250); }
            ppToast(json.msg,'ok');
        } else {
            ppToast(json.msg,'err');
            btn.classList.remove('busy');
        }
    } catch {
        ppToast('Network error.','err');
        btn.classList.remove('busy');
    }
}

/* ── Live Search ── */
function ppSearch(query) {
    const q    = query.trim().toLowerCase();
    const dept = document.getElementById('pp-dept-filter').value.toLowerCase();
    const rows = document.querySelectorAll('#pp-tbody tr');
    const clr  = document.getElementById('pp-clear');
    let visible = 0;

    clr.classList.toggle('visible', q.length > 0);

    rows.forEach(row => {
        const name  = row.dataset.name    || '';
        const empid = row.dataset.empid   || '';
        const rdept = row.dataset.dept    || '';
        const spec  = row.dataset.spec    || '';

        const matchQ    = !q    || name.includes(q) || empid.includes(q) || rdept.includes(q) || spec.includes(q);
        const matchDept = !dept || rdept === dept;

        if (matchQ && matchDept) {
            row.classList.remove('hidden');
            visible++;
            if (q) highlightRow(row, q); else clearHighlight(row);
        } else {
            row.classList.add('hidden');
            clearHighlight(row);
        }
    });

    document.getElementById('pp-no-results').style.display = visible === 0 ? 'block' : 'none';
    ppUpdateCount(visible);
}

function ppClearSearch() {
    document.getElementById('pp-search').value = '';
    document.getElementById('pp-dept-filter').value = '';
    ppSearch('');
    document.getElementById('pp-search').focus();
}

function ppUpdateCount(count) {
    const total = document.querySelectorAll('#pp-tbody tr:not(.hidden)').length;
    const n = count !== undefined ? count : total;
    document.getElementById('pp-result-count').textContent = n + ' professor' + (n!==1?'s':'');
    document.getElementById('pp-table-count').textContent  = n + ' total';
}

function highlightRow(row, q) {
    row.querySelectorAll('[data-field]').forEach(el => {
        const original = el.textContent;
        const regex = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
        el.innerHTML = original.replace(regex, '<mark class="pp-hl">$1</mark>');
    });
}
function clearHighlight(row) {
    row.querySelectorAll('[data-field]').forEach(el => { el.innerHTML = el.textContent; });
}

ppUpdateCount();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>