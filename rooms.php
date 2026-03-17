<?php
require_once __DIR__ . '/includes/config.php';
$db = getDB();

// ══════════════════════════════════════════════════════
// AJAX HANDLER
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');

    $action   = $_POST['action']    ?? '';
    $name     = sanitize($_POST['name']      ?? '');
    $building = sanitize($_POST['building']  ?? '');
    $capacity = (int)($_POST['capacity']     ?? 0);
    $type     = sanitize($_POST['room_type'] ?? 'Lecture');
    $id       = (int)($_POST['id']           ?? 0);

    try {
        if ($action === 'create') {
            if (!$name) { echo json_encode(['ok'=>false,'msg'=>'Room name is required.']); exit; }
            $db->prepare("INSERT INTO rooms (name,building,capacity,room_type) VALUES (?,?,?,?)")
               ->execute([$name,$building,$capacity,$type]);
            echo json_encode(['ok'=>true,'msg'=>'Room added successfully.']);

        } elseif ($action === 'update') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("UPDATE rooms SET name=?,building=?,capacity=?,room_type=? WHERE id=?")
               ->execute([$name,$building,$capacity,$type,$id]);
            echo json_encode(['ok'=>true,'msg'=>'Room updated successfully.']);

        } elseif ($action === 'delete') {
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID.']); exit; }
            $db->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
            echo json_encode(['ok'=>true,'msg'=>'Room deleted.']);

        } else {
            echo json_encode(['ok'=>false,'msg'=>'Unknown action.']);
        }
    } catch (PDOException $e) {
        $msg = str_contains($e->getMessage(),'Duplicate') ? 'Room name already exists.' : 'Database error: '.$e->getMessage();
        echo json_encode(['ok'=>false,'msg'=>$msg]);
    }
    exit;
}

// ══════════════════════════════════════════════════════
// PAGE LOAD
// ══════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = 'Rooms';
require_once __DIR__ . '/includes/header.php';

$rooms     = $db->query("SELECT * FROM rooms ORDER BY building, name")->fetchAll();
$roomTypes = ['Lecture','Computer Lab','Gym','Laboratory','Auditorium'];

// Stats
$buildings    = array_filter(array_unique(array_column($rooms,'building')));
$totalCap     = array_sum(array_column($rooms,'capacity'));
$inUse        = (int)$db->query("SELECT COUNT(DISTINCT room_id) FROM schedules")->fetchColumn();

// Type meta — icon, colors
$typeMeta = [
    'Lecture'      => ['icon'=>'📖','bg'=>'#eff6ff','color'=>'#1d4ed8','border'=>'#bfdbfe'],
    'Computer Lab' => ['icon'=>'💻','bg'=>'#f0fdf4','color'=>'#166534','border'=>'#bbf7d0'],
    'Gym'          => ['icon'=>'🏋️','bg'=>'#fff7ed','color'=>'#c2410c','border'=>'#fed7aa'],
    'Laboratory'   => ['icon'=>'🔬','bg'=>'#faf5ff','color'=>'#6d28d9','border'=>'#e9d5ff'],
    'Auditorium'   => ['icon'=>'🎭','bg'=>'#fef2f2','color'=>'#dc2626','border'=>'#fecaca'],
];
?>

<style>
/* ── Base ─────────────────────────────────────────── */
.rm { font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Header ───────────────────────────────────────── */
.rm-hdr {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom:24px; gap:16px; flex-wrap:wrap;
}
.rm-hdr h2 { font-size:1.5rem; font-weight:800; color:var(--text,#0f172a); margin:0 0 2px; letter-spacing:-.5px; }
.rm-hdr p  { margin:0; color:var(--text3,#94a3b8); font-size:.85rem; }
.btn-add-rm {
    display:inline-flex; align-items:center; gap:7px;
    padding:10px 20px; border-radius:10px;
    background:#1e3a8a; color:#fff; font-size:.875rem; font-weight:700;
    border:none; cursor:pointer;
    box-shadow:0 2px 8px rgba(30,58,138,.25);
    transition:background .15s, transform .15s, box-shadow .15s;
}
.btn-add-rm:hover { background:#1e40af; transform:translateY(-1px); box-shadow:0 4px 14px rgba(30,58,138,.3); }

/* ── Stats ────────────────────────────────────────── */
.rm-stats {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:12px; margin-bottom:22px;
}
.rm-stat {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:12px; padding:14px 17px;
    display:flex; align-items:center; gap:11px;
    transition:box-shadow .15s, transform .15s;
}
.rm-stat:hover { box-shadow:0 6px 20px rgba(0,0,0,.07); transform:translateY(-1px); }
.rm-stat-ico { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
.rm-stat-val { font-size:1.4rem; font-weight:900; color:var(--text,#0f172a); line-height:1; }
.rm-stat-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3,#94a3b8); margin-top:2px; }

/* ── Search & filter bar ──────────────────────────── */
.rm-toolbar {
    display:flex; gap:10px; align-items:center;
    margin-bottom:18px; flex-wrap:wrap;
}
.rm-search-box {
    display:flex; align-items:center;
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:10px; overflow:hidden;
    flex:1; min-width:200px; max-width:380px;
    transition:border-color .15s, box-shadow .15s;
}
.rm-search-box:focus-within { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
.rm-search-ico { padding:0 12px; color:var(--text3,#94a3b8); display:flex; align-items:center; flex-shrink:0; }
.rm-search-input { border:none; outline:none; background:transparent; padding:10px 12px 10px 0; font-size:.875rem; color:var(--text,#1e293b); width:100%; }
.rm-search-input::placeholder { color:var(--text3,#94a3b8); }
.rm-search-clear { padding:0 12px; color:var(--text3,#94a3b8); background:none; border:none; cursor:pointer; display:none; align-items:center; font-size:1rem; transition:color .15s; }
.rm-search-clear:hover { color:var(--text,#0f172a); }
.rm-search-clear.visible { display:flex; }

/* Type filter pills */
.rm-type-filters { display:flex; gap:6px; flex-wrap:wrap; }
.rm-type-pill {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 13px; border-radius:20px; font-size:.77rem; font-weight:700;
    border:1.5px solid var(--border,#e2e8f0);
    background:var(--card-bg,#fff); color:var(--text3,#64748b);
    cursor:pointer; transition:all .15s; white-space:nowrap;
}
.rm-type-pill:hover { border-color:#3b82f6; color:#1d4ed8; }
.rm-type-pill.active { border-color:transparent; }

.rm-result-count { font-size:.78rem; color:var(--text3,#94a3b8); font-weight:600; margin-left:auto; align-self:center; white-space:nowrap; }

/* ── Table card ───────────────────────────────────── */
.rm-card {
    background:var(--card-bg,#fff);
    border:1.5px solid var(--border,#e2e8f0);
    border-radius:14px; overflow:hidden;
}
.rm-card-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:15px 20px; border-bottom:1.5px solid var(--border,#e2e8f0);
}
.rm-card-title { font-size:.95rem; font-weight:800; color:var(--text,#0f172a); }
.rm-card-count { font-size:.75rem; font-weight:700; color:#1d4ed8; background:#dbeafe; border-radius:20px; padding:3px 10px; }

.rm-table { width:100%; border-collapse:collapse; }
.rm-table thead tr { background:var(--hover,#f8fafc); }
.rm-table th { padding:10px 16px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--text3,#94a3b8); text-align:left; border-bottom:1.5px solid var(--border,#e2e8f0); white-space:nowrap; }
.rm-table td { padding:13px 16px; font-size:.875rem; border-bottom:1px solid var(--border,#f1f5f9); vertical-align:middle; color:var(--text,#334155); }
.rm-table tbody tr { transition:background .1s; }
.rm-table tbody tr:hover { background:var(--hover,#f8fafc); }
.rm-table tbody tr:last-child td { border-bottom:none; }
.rm-table tbody tr.hidden { display:none; }

/* Room name cell */
.rm-name-cell { display:flex; align-items:center; gap:11px; }
.rm-room-ico {
    width:38px; height:38px; border-radius:9px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; flex-shrink:0;
}
.rm-name-txt { font-weight:700; color:var(--text,#0f172a); font-size:.9rem; }
.rm-bldg-sub { font-size:.72rem; color:var(--text3,#94a3b8); margin-top:1px; }

/* Type badge */
.rm-type-badge {
    display:inline-flex; align-items:center; gap:5px;
    font-size:.75rem; font-weight:700; padding:4px 11px; border-radius:20px;
    border:1px solid;
}

/* Capacity bar */
.rm-cap-cell { display:flex; align-items:center; gap:8px; }
.rm-cap-val  { font-size:.875rem; font-weight:600; color:var(--text,#0f172a); white-space:nowrap; }
.rm-cap-bar  { flex:1; min-width:50px; max-width:80px; height:5px; background:var(--border,#e2e8f0); border-radius:10px; overflow:hidden; }
.rm-cap-fill { height:100%; background:#3b82f6; border-radius:10px; transition:width .3s; }

.no-val { color:var(--text3,#94a3b8); font-size:.8rem; }

/* Actions */
.rm-acts { display:flex; gap:6px; align-items:center; justify-content:center; }
.btn-rm-edit { padding:5px 13px; border-radius:7px; font-size:.75rem; font-weight:700; border:1.5px solid var(--border,#e2e8f0); background:var(--card-bg,#fff); color:var(--text2,#475569); cursor:pointer; display:inline-flex; align-items:center; gap:4px; transition:border-color .15s, color .15s, background .15s; }
.btn-rm-edit:hover { border-color:#3b82f6; color:#1d4ed8; background:#eff6ff; }
.btn-rm-del  { padding:5px 10px; border-radius:7px; font-size:.75rem; font-weight:700; border:1.5px solid #fecaca; background:#fff5f5; color:#dc2626; cursor:pointer; display:inline-flex; align-items:center; gap:3px; transition:background .15s; }
.btn-rm-del:hover { background:#fee2e2; }
.btn-rm-del.busy  { opacity:.5; pointer-events:none; }

/* No results */
.rm-no-results { text-align:center; padding:48px 20px; color:var(--text3,#94a3b8); display:none; }
.rm-no-results .ei { font-size:2.5rem; display:block; margin-bottom:10px; }
.rm-no-results p { margin:0; font-size:.9rem; }

/* Empty */
.rm-empty { text-align:center; padding:60px 24px; color:var(--text3,#94a3b8); }
.rm-empty .ei { font-size:3rem; display:block; margin-bottom:14px; }
.rm-empty p { margin:0; font-size:.9rem; }

/* ── Modal ────────────────────────────────────────── */
#rm-modal {
    display:none; position:fixed; inset:0;
    background:rgba(15,23,42,.5); z-index:600;
    align-items:center; justify-content:center; padding:20px;
    backdrop-filter:blur(2px);
}
#rm-modal.open { display:flex; }
.rm-modal-box {
    background:var(--card-bg,#fff); border-radius:16px;
    width:100%; max-width:520px;
    box-shadow:0 32px 80px rgba(0,0,0,.22);
    animation:rmUp .22s cubic-bezier(.16,1,.3,1);
}
@keyframes rmUp { from{transform:translateY(18px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }

.rm-mhdr { display:flex; align-items:center; justify-content:space-between; padding:20px 24px 15px; border-bottom:1.5px solid var(--border,#e2e8f0); border-radius:16px 16px 0 0; }
.rm-mhdr-left { display:flex; align-items:center; gap:12px; }
.rm-mhdr-ico  { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#dbeafe,#eff6ff); display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.rm-mhdr-ttl  { font-size:1rem; font-weight:800; color:var(--text,#0f172a); }
.rm-mhdr-sub  { font-size:.73rem; color:var(--text3,#94a3b8); margin-top:1px; }
.rm-mhdr-cls  { background:none; border:none; width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-size:1rem; color:var(--text3,#94a3b8); cursor:pointer; border-radius:8px; transition:background .15s, color .15s; }
.rm-mhdr-cls:hover { background:var(--hover,#f1f5f9); color:var(--text,#0f172a); }
.rm-mbody { padding:20px 24px 26px; }

.rm-fg { display:grid; grid-template-columns:1fr 1fr; gap:13px 16px; }
.rm-fg .full { grid-column:1/-1; }
.rm-lbl { display:block; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.4px; color:var(--text2,#475569); margin-bottom:5px; }
.rm-lbl .req { color:#ef4444; margin-left:2px; }
.rm-fc { width:100%; padding:9px 12px; border:1.5px solid var(--border,#e2e8f0); border-radius:9px; font-size:.875rem; color:var(--text,#1e293b); background:var(--input-bg,#f8fafc); transition:border-color .15s, box-shadow .15s; box-sizing:border-box; }
.rm-fc:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); background:#fff; }

/* Type selector in modal */
.rm-type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
.rm-type-opt  {
    display:flex; flex-direction:column; align-items:center; gap:4px;
    padding:10px 6px; border-radius:9px; cursor:pointer;
    border:1.5px solid var(--border,#e2e8f0);
    background:var(--card-bg,#fff);
    transition:all .15s; font-size:.78rem; font-weight:600;
    color:var(--text2,#475569); text-align:center;
}
.rm-type-opt:hover { border-color:#3b82f6; }
.rm-type-opt.selected { border-color:#3b82f6; background:#eff6ff; color:#1d4ed8; }
.rm-type-opt .tipo-icon { font-size:1.3rem; }
.rm-type-hidden { display:none; }

.rm-mftr { display:flex; align-items:center; gap:10px; margin-top:18px; padding-top:15px; border-top:1.5px solid var(--border,#e2e8f0); }
.btn-rm-save { padding:10px 22px; border-radius:9px; font-size:.875rem; font-weight:800; background:#1e3a8a; color:#fff; border:none; cursor:pointer; display:flex; align-items:center; gap:7px; box-shadow:0 2px 8px rgba(30,58,138,.2); transition:background .15s, transform .15s, opacity .15s; }
.btn-rm-save:hover { background:#1e40af; transform:translateY(-1px); }
.btn-rm-save.busy { opacity:.65; pointer-events:none; }
.btn-rm-cncl { padding:10px 16px; border-radius:9px; font-size:.875rem; font-weight:600; background:var(--hover,#f1f5f9); color:var(--text2,#475569); border:1.5px solid var(--border,#e2e8f0); cursor:pointer; transition:background .15s; }
.btn-rm-cncl:hover { background:#e2e8f0; }

/* ── Toast ────────────────────────────────────────── */
#rm-toasts { position:fixed; top:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
.rm-toast { display:flex; align-items:center; gap:10px; padding:12px 18px; border-radius:11px; font-size:.875rem; font-weight:600; max-width:360px; box-shadow:0 8px 30px rgba(0,0,0,.13); animation:rmTIn .25s cubic-bezier(.16,1,.3,1); pointer-events:auto; }
.rm-toast.ok  { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }
.rm-toast.err { background:#fef2f2; color:#991b1b; border:1.5px solid #fecaca; }
@keyframes rmTIn  { from{transform:translateX(60px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes rmTOut { from{transform:translateX(0);opacity:1}    to{transform:translateX(60px);opacity:0} }
@keyframes rmSpin { from{transform:rotate(0)} to{transform:rotate(360deg)} }

@media(max-width:860px){ .rm-stats{grid-template-columns:1fr 1fr} }
@media(max-width:540px){ .rm-fg{grid-template-columns:1fr} .rm-fg .full{grid-column:1} .rm-type-grid{grid-template-columns:repeat(2,1fr)} .rm-hdr{flex-direction:column;align-items:flex-start} .rm-stats{grid-template-columns:1fr 1fr} }
</style>

<!-- Toast container -->
<div id="rm-toasts"></div>

<div class="rm">

  <!-- Header -->
  <div class="rm-hdr">
    <div>
      <h2>Room Management</h2>
      <p>Manage classrooms, laboratories, and other facilities</p>
    </div>
    <button class="btn-add-rm" onclick="rmOpenModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Room
    </button>
  </div>

  <!-- Stats -->
  <div class="rm-stats">
    <div class="rm-stat">
      <div class="rm-stat-ico" style="background:#eff6ff">🏫</div>
      <div><div class="rm-stat-val"><?= count($rooms) ?></div><div class="rm-stat-lbl">Total Rooms</div></div>
    </div>
    <div class="rm-stat">
      <div class="rm-stat-ico" style="background:#faf5ff">🏢</div>
      <div><div class="rm-stat-val"><?= count($buildings) ?></div><div class="rm-stat-lbl">Buildings</div></div>
    </div>
    <div class="rm-stat">
      <div class="rm-stat-ico" style="background:#f0fdf4">👥</div>
      <div><div class="rm-stat-val"><?= number_format($totalCap) ?></div><div class="rm-stat-lbl">Total Capacity</div></div>
    </div>
    <div class="rm-stat">
      <div class="rm-stat-ico" style="background:#fff7ed">📅</div>
      <div><div class="rm-stat-val"><?= $inUse ?></div><div class="rm-stat-lbl">In Schedules</div></div>
    </div>
  </div>

  <!-- Toolbar: Search + Type Filter -->
  <div class="rm-toolbar no-print">
    <!-- Search -->
    <div class="rm-search-box">
      <span class="rm-search-ico">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </span>
      <input type="text" id="rm-search" class="rm-search-input"
             placeholder="Search by name, building, or type…"
             oninput="rmSearch()" autocomplete="off">
      <button class="rm-search-clear" id="rm-clear" onclick="rmClearSearch()">✕</button>
    </div>

    <!-- Type filter pills -->
    <div class="rm-type-filters">
      <button class="rm-type-pill active" data-type="" onclick="rmSetType(this,'')">All</button>
      <?php foreach ($roomTypes as $rt):
        $tm = $typeMeta[$rt] ?? ['icon'=>'🏫','bg'=>'#eff6ff','color'=>'#1d4ed8','border'=>'#bfdbfe'];
      ?>
      <button class="rm-type-pill" data-type="<?= $rt ?>"
              style="--pill-bg:<?= $tm['bg'] ?>;--pill-color:<?= $tm['color'] ?>;--pill-border:<?= $tm['border'] ?>"
              onclick="rmSetType(this,'<?= $rt ?>')">
        <?= $tm['icon'] ?> <?= $rt ?>
      </button>
      <?php endforeach; ?>
    </div>

    <span class="rm-result-count" id="rm-result-count"><?= count($rooms) ?> room<?= count($rooms)!==1?'s':'' ?></span>
  </div>

  <!-- Table Card -->
  <div class="rm-card">
    <div class="rm-card-head">
      <span class="rm-card-title">All Rooms</span>
      <span class="rm-card-count" id="rm-table-count"><?= count($rooms) ?> total</span>
    </div>

    <?php if (empty($rooms)): ?>
      <div class="rm-empty">
        <span class="ei">🏫</span>
        <p>No rooms yet. <a href="#" onclick="rmOpenModal();return false" style="color:#3b82f6;font-weight:700;text-decoration:none">Add your first room →</a></p>
      </div>
    <?php else: ?>
      <?php $maxCap = max(array_column($rooms,'capacity') ?: [1]); ?>
      <div style="overflow-x:auto">
        <table class="rm-table">
          <thead>
            <tr>
              <th>Room</th>
              <th>Type</th>
              <th>Capacity</th>
              <th class="no-print" style="text-align:center">Actions</th>
            </tr>
          </thead>
          <tbody id="rm-tbody">
          <?php foreach ($rooms as $r):
            $tm      = $typeMeta[$r['room_type']] ?? $typeMeta['Lecture'];
            $capPct  = $r['capacity'] && $maxCap ? round($r['capacity']/$maxCap*100) : 0;
            $payload = htmlspecialchars(json_encode([
                'id'        => $r['id'],
                'name'      => $r['name'],
                'building'  => $r['building']  ?? '',
                'capacity'  => $r['capacity']  ?? 0,
                'room_type' => $r['room_type'] ?? 'Lecture',
            ]), ENT_QUOTES);
          ?>
          <tr id="rm-row-<?= $r['id'] ?>"
              data-name="<?= strtolower(h($r['name'])) ?>"
              data-bldg="<?= strtolower(h($r['building'] ?? '')) ?>"
              data-type="<?= h($r['room_type']) ?>">
            <td>
              <div class="rm-name-cell">
                <div class="rm-room-ico" style="background:<?= $tm['bg'] ?>"><?= $tm['icon'] ?></div>
                <div>
                  <div class="rm-name-txt"><?= h($r['name']) ?></div>
                  <?php if ($r['building']): ?>
                    <div class="rm-bldg-sub">
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                      <?= h($r['building']) ?>
                    </div>
                  <?php else: ?>
                    <div class="rm-bldg-sub no-val">No building</div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <span class="rm-type-badge"
                    style="background:<?= $tm['bg'] ?>;color:<?= $tm['color'] ?>;border-color:<?= $tm['border'] ?>">
                <?= $tm['icon'] ?> <?= h($r['room_type']) ?>
              </span>
            </td>
            <td>
              <?php if ($r['capacity']): ?>
              <div class="rm-cap-cell">
                <span class="rm-cap-val"><?= h($r['capacity']) ?> pax</span>
                <div class="rm-cap-bar"><div class="rm-cap-fill" style="width:<?= $capPct ?>%"></div></div>
              </div>
              <?php else: ?>
                <span class="no-val">—</span>
              <?php endif; ?>
            </td>
            <td class="no-print">
              <div class="rm-acts">
                <button class="btn-rm-edit" onclick="rmEdit(<?= $payload ?>)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                  Edit
                </button>
                <button class="btn-rm-del" onclick="rmDelete(<?= $r['id'] ?>, this)">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="rm-no-results" id="rm-no-results">
        <span class="ei">🔍</span>
        <p>No rooms match your search.</p>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /rm -->

<!-- ═══════════════════════
     MODAL
═══════════════════════ -->
<div id="rm-modal" role="dialog" aria-modal="true">
  <div class="rm-modal-box" id="rm-modal-box">
    <div class="rm-mhdr">
      <div class="rm-mhdr-left">
        <div class="rm-mhdr-ico">🏫</div>
        <div>
          <div class="rm-mhdr-ttl" id="rm-modal-ttl">Add New Room</div>
          <div class="rm-mhdr-sub">Room name and type are required</div>
        </div>
      </div>
      <button type="button" class="rm-mhdr-cls" id="rm-cls">✕</button>
    </div>
    <div class="rm-mbody">
      <form id="rm-form">
        <input type="hidden" id="rm-action" name="action"    value="create">
        <input type="hidden" id="rm-id"     name="id"        value="">
        <!-- Hidden actual room_type field -->
        <input type="hidden" id="rm-type-val" name="room_type" value="Lecture">

        <div class="rm-fg">
          <!-- Room Name -->
          <div>
            <label class="rm-lbl">Room Name <span class="req">*</span></label>
            <input id="rm-name" class="rm-fc" name="name" placeholder="e.g. Room 305" required>
          </div>
          <!-- Building -->
          <div>
            <label class="rm-lbl">Building</label>
            <input id="rm-building" class="rm-fc" name="building" placeholder="e.g. Main Building">
          </div>
          <!-- Capacity -->
          <div>
            <label class="rm-lbl">Capacity</label>
            <input id="rm-capacity" class="rm-fc" name="capacity" type="number" min="0" placeholder="e.g. 40">
          </div>
          <!-- Spacer -->
          <div></div>
          <!-- Room Type — visual card picker (full width) -->
          <div class="full">
            <label class="rm-lbl">Room Type <span class="req">*</span></label>
            <div class="rm-type-grid" id="rm-type-picker">
              <?php foreach ($roomTypes as $rt):
                $tm = $typeMeta[$rt] ?? ['icon'=>'🏫','bg'=>'#eff6ff','color'=>'#1d4ed8'];
              ?>
              <div class="rm-type-opt <?= $rt==='Lecture'?'selected':'' ?>"
                   data-type="<?= $rt ?>"
                   onclick="rmSelectType('<?= $rt ?>')"
                   style="<?= $rt==='Lecture'?"border-color:#3b82f6;background:#eff6ff;color:#1d4ed8":'' ?>">
                <span class="tipo-icon"><?= $tm['icon'] ?></span>
                <span><?= $rt ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="rm-mftr">
          <button type="submit" class="btn-rm-save" id="rm-save">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Room
          </button>
          <button type="button" class="btn-rm-cncl" id="rm-cncl">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ── Toast ──────────────────────────────────────── */
function rmToast(msg, type) {
    const w = document.getElementById('rm-toasts');
    const t = document.createElement('div');
    t.className = 'rm-toast ' + (type==='ok'?'ok':'err');
    t.innerHTML = (type==='ok'?'✅ ':'❌ ') + msg;
    w.appendChild(t);
    setTimeout(()=>{ t.style.animation='rmTOut .3s ease forwards'; setTimeout(()=>t.remove(),300); }, 3500);
}

/* ── Modal ──────────────────────────────────────── */
const rmModal = document.getElementById('rm-modal');
const rmBox   = document.getElementById('rm-modal-box');
const rmTtl   = document.getElementById('rm-modal-ttl');

function rmOpenModal(title) {
    rmTtl.textContent = title || 'Add New Room';
    rmModal.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(()=>document.getElementById('rm-name').focus(), 80);
}
function rmCloseModal() {
    rmModal.classList.remove('open');
    document.body.style.overflow = '';
    rmResetForm();
}
function rmResetForm() {
    document.getElementById('rm-action').value   = 'create';
    document.getElementById('rm-id').value       = '';
    document.getElementById('rm-name').value     = '';
    document.getElementById('rm-building').value = '';
    document.getElementById('rm-capacity').value = '';
    rmSelectType('Lecture');
    rmTtl.textContent = 'Add New Room';
}

document.getElementById('rm-cls').addEventListener('click',  e=>{ e.preventDefault(); e.stopPropagation(); rmCloseModal(); });
document.getElementById('rm-cncl').addEventListener('click', e=>{ e.preventDefault(); e.stopPropagation(); rmCloseModal(); });
rmModal.addEventListener('click', e=>{ if(e.target===rmModal) rmCloseModal(); });
rmBox.addEventListener('click',   e=>e.stopPropagation());
document.addEventListener('keydown', e=>{ if(e.key==='Escape'&&rmModal.classList.contains('open')) rmCloseModal(); });

/* ── Type picker ────────────────────────────────── */
function rmSelectType(type) {
    document.getElementById('rm-type-val').value = type;
    document.querySelectorAll('.rm-type-opt').forEach(el => {
        const isSelected = el.dataset.type === type;
        el.classList.toggle('selected', isSelected);
        el.style.borderColor = isSelected ? '#3b82f6' : '';
        el.style.background  = isSelected ? '#eff6ff' : '';
        el.style.color       = isSelected ? '#1d4ed8' : '';
    });
}

/* ── Edit ───────────────────────────────────────── */
function rmEdit(r) {
    document.getElementById('rm-action').value   = 'update';
    document.getElementById('rm-id').value       = r.id;
    document.getElementById('rm-name').value     = r.name;
    document.getElementById('rm-building').value = r.building;
    document.getElementById('rm-capacity').value = r.capacity || '';
    rmSelectType(r.room_type || 'Lecture');
    rmOpenModal('Edit Room');
}

/* ── AJAX Save ──────────────────────────────────── */
document.getElementById('rm-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('rm-save');
    btn.classList.add('busy');
    btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:rmSpin 1s linear infinite"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-6.49"/></svg> Saving…';

    try {
        const res  = await fetch('rooms.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:new FormData(this) });
        const json = await res.json();
        if (json.ok) {
            rmToast(json.msg, 'ok');
            rmCloseModal();
            setTimeout(()=>location.reload(), 700);
        } else {
            rmToast(json.msg, 'err');
        }
    } catch {
        rmToast('Network error. Please try again.', 'err');
    } finally {
        btn.classList.remove('busy');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Room';
    }
});

/* ── AJAX Delete ────────────────────────────────── */
async function rmDelete(id, btn) {
    if (!confirm('Delete this room? It may be used in existing schedules.')) return;
    btn.classList.add('busy');
    const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
    try {
        const res  = await fetch('rooms.php', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const json = await res.json();
        if (json.ok) {
            const row = document.getElementById('rm-row-'+id);
            if (row) { row.style.transition='opacity .25s'; row.style.opacity='0'; setTimeout(()=>{ row.remove(); rmUpdateCount(); },250); }
            rmToast(json.msg,'ok');
        } else {
            rmToast(json.msg,'err');
            btn.classList.remove('busy');
        }
    } catch { rmToast('Network error.','err'); btn.classList.remove('busy'); }
}

/* ── Live search + type filter ──────────────────── */
let activeType = '';

function rmSetType(btn, type) {
    activeType = type;
    document.querySelectorAll('.rm-type-pill').forEach(p => {
        const isActive = p.dataset.type === type;
        p.classList.toggle('active', isActive);
        p.style.background   = isActive && type ? p.style.getPropertyValue('--pill-bg')   || '#eff6ff' : '';
        p.style.color        = isActive && type ? p.style.getPropertyValue('--pill-color') || '#1d4ed8' : '';
        p.style.borderColor  = isActive && type ? p.style.getPropertyValue('--pill-border')|| '#bfdbfe' : '';
    });
    // Re-apply pill bg using CSS vars
    document.querySelectorAll('.rm-type-pill').forEach(p => {
        if (p.classList.contains('active') && p.dataset.type) {
            const bg = getComputedStyle(p).getPropertyValue('--pill-bg').trim();
            const clr= getComputedStyle(p).getPropertyValue('--pill-color').trim();
            const brd= getComputedStyle(p).getPropertyValue('--pill-border').trim();
            if (bg) { p.style.background=bg; p.style.color=clr; p.style.borderColor=brd; }
        } else if (!p.classList.contains('active')) {
            p.style.background=''; p.style.color=''; p.style.borderColor='';
        }
    });
    rmSearch();
}

function rmSearch() {
    const q    = document.getElementById('rm-search').value.trim().toLowerCase();
    const rows = document.querySelectorAll('#rm-tbody tr');
    const clr  = document.getElementById('rm-clear');
    clr.classList.toggle('visible', q.length > 0);
    let visible = 0;

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const bldg = row.dataset.bldg || '';
        const type = row.dataset.type || '';
        const matchQ    = !q           || name.includes(q) || bldg.includes(q) || type.toLowerCase().includes(q);
        const matchType = !activeType  || type === activeType;
        if (matchQ && matchType) { row.classList.remove('hidden'); visible++; }
        else                     { row.classList.add('hidden'); }
    });

    document.getElementById('rm-no-results').style.display = visible===0 ? 'block' : 'none';
    rmUpdateCount(visible);
}

function rmClearSearch() {
    document.getElementById('rm-search').value = '';
    rmSearch();
    document.getElementById('rm-search').focus();
}

function rmUpdateCount(n) {
    if (n === undefined) n = document.querySelectorAll('#rm-tbody tr:not(.hidden)').length;
    document.getElementById('rm-result-count').textContent = n + ' room' + (n!==1?'s':'');
    document.getElementById('rm-table-count').textContent  = n + ' total';
}

rmUpdateCount();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>