<?php
require_once __DIR__ . '/functions.php';
require_login();

$msg = '';
$msg_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $pdo = db();

    if ($act === 'add') {
        $days   = $_POST['days'] ?? '30';
        $plan   = trim($_POST['plan'] ?? '') ?: 'standard';
        $note   = trim($_POST['note'] ?? '');
        $expiry = null;

        if ($days === 'custom') {
            $d = max(1, min(9999, (int)($_POST['custom_days'] ?? 1)));
            $expiry = date('Y-m-d H:i:s', time() + $d * 86400);
        } elseif ($days !== 'lifetime') {
            $d = max(1, (int)$days);
            $expiry = date('Y-m-d H:i:s', time() + $d * 86400);
        }

        $devices = min(9999, max(1, (int)($_POST['devices'] ?? 1)));
        $key = gen_key();
        $pdo->prepare('INSERT INTO licenses (license_key, expiry, plan, note, max_devices) VALUES (?,?,?,?,?)')
            ->execute([$key, $expiry, $plan, $note, $devices]);
        $msg = "Key baru berhasil dibuat: <strong>$key</strong>";
    }

    if (in_array($act, ['ban', 'activate', 'resetdev', 'delete'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'ban')      $pdo->prepare("UPDATE licenses SET status='banned' WHERE id=?")->execute([$id]);
        if ($act === 'activate') $pdo->prepare("UPDATE licenses SET status='active' WHERE id=?")->execute([$id]);
        if ($act === 'resetdev') $pdo->prepare("DELETE FROM license_devices WHERE license_id=?")->execute([$id]);
        if ($act === 'delete')   $pdo->prepare("DELETE FROM licenses WHERE id=?")->execute([$id]);
        $msg = 'Aksi berhasil dilakukan.';
    }

    if ($act === 'extendexp') {
        $id       = (int)($_POST['id'] ?? 0);
        $ext_days = $_POST['ext_days'] ?? '30';
        $ext_custom = max(1, min(9999, (int)($_POST['ext_custom_days'] ?? 1)));

        if ($ext_days === 'custom') {
            $add = $ext_custom;
        } else {
            $add = max(1, (int)$ext_days);
        }

        // Jika lifetime, tidak perlu ditambah
        $row = $pdo->prepare("SELECT expiry FROM licenses WHERE id=?");
        $row->execute([$id]);
        $current = $row->fetchColumn();

        if ($current === null) {
            $msg = 'Key ini sudah Lifetime, tidak perlu ditambah.';
        } else {
            // Hitung base: jika sudah expired pakai sekarang, jika masih aktif lanjutkan dari expiry lama
            $base = (strtotime($current) > time()) ? strtotime($current) : time();
            $new_expiry = date('Y-m-d H:i:s', $base + $add * 86400);
            $pdo->prepare("UPDATE licenses SET expiry=? WHERE id=?")->execute([$new_expiry, $id]);
            $msg = "Durasi key #$id berhasil ditambah <strong>$add hari</strong>. Expired baru: <strong>$new_expiry</strong>";
        }
    }
}

$cnt  = db()->query("SELECT COUNT(*) c, SUM(status='active') a, SUM(status='banned') b FROM licenses")->fetch(PDO::FETCH_ASSOC);
$keys = db()->query('SELECT l.*, (SELECT COUNT(*) FROM license_devices d WHERE d.license_id = l.id) dev_used FROM licenses l ORDER BY l.id DESC')->fetchAll(PDO::FETCH_ASSOC);
$logs = db()->query('SELECT * FROM verify_logs ORDER BY id DESC LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>License Admin Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:         #0d0f14;
    --surface:    #161a23;
    --surface2:   #1c2130;
    --border:     #252b38;
    --border-hi:  #3a4256;
    --text:       #e2e6f0;
    --muted:      #6b7591;
    --muted2:     #8b95b0;
    --accent:     #5b8af5;
    --accent-dk:  #4070e0;
    --green:      #3ecf6e;
    --green-bg:   rgba(62,207,110,.1);
    --green-bd:   rgba(62,207,110,.25);
    --red:        #e05555;
    --red-bg:     rgba(224,85,85,.1);
    --red-bd:     rgba(224,85,85,.25);
    --yellow:     #f5a623;
    --yellow-bg:  rgba(245,166,35,.1);
    --yellow-bd:  rgba(245,166,35,.25);
    --radius:     8px;
    --radius-lg:  12px;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 14px;
    line-height: 1.5;
    min-height: 100vh;
  }

  /* ── Topbar ── */
  .topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    height: 56px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 10;
  }

  .topbar-logo {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-right: auto;
  }

  .topbar-icon {
    width: 32px; height: 32px;
    background: var(--accent);
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }

  .topbar-name {
    font-size: 14px;
    font-weight: 600;
  }

  .topbar-badge {
    font-size: 11px;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--muted2);
    border-radius: 5px;
    padding: 2px 7px;
    font-weight: 500;
  }

  .topbar-logout {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px;
    color: var(--muted2);
    text-decoration: none;
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    transition: color .15s, border-color .15s;
  }
  .topbar-logout:hover { color: var(--text); border-color: var(--border-hi); }

  /* ── Layout ── */
  .page { max-width: 1200px; margin: 0 auto; padding: 28px 24px; }

  /* ── Stats ── */
  .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }

  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 18px 20px;
  }

  .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 8px; font-weight: 500; }
  .stat-val { font-size: 28px; font-weight: 600; line-height: 1; }
  .stat-val.green { color: var(--green); }
  .stat-val.red   { color: var(--red); }
  .stat-val.blue  { color: var(--accent); }

  /* ── Toast ── */
  .toast {
    background: var(--surface);
    border: 1px solid var(--green-bd);
    color: var(--green);
    border-radius: var(--radius);
    padding: 12px 16px;
    margin-bottom: 20px;
    font-size: 13px;
    display: flex; align-items: center; gap: 8px;
  }

  /* ── Section ── */
  .section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    overflow: hidden;
  }

  .section-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 8px;
  }

  .section-title {
    font-size: 14px;
    font-weight: 600;
  }

  .section-body { padding: 20px; }

  /* ── Generate Form ── */
  .gen-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
    align-items: end;
  }

  .field { display: flex; flex-direction: column; gap: 5px; }
  .field label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 500; }

  input[type=text], input[type=number], select {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: var(--radius);
    padding: 8px 11px;
    font-size: 13px;
    font-family: inherit;
    width: 100%;
    outline: none;
    transition: border-color .15s;
  }
  input:focus, select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(91,138,245,.12); }
  select option { background: var(--surface); }

  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    border: none; border-radius: var(--radius);
    font-size: 13px; font-weight: 600; font-family: inherit;
    cursor: pointer; transition: background .15s, opacity .15s;
    white-space: nowrap;
  }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: var(--accent-dk); }
  .btn-ghost  { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
  .btn-ghost:hover { border-color: var(--border-hi); }
  .btn-ban    { background: var(--red-bg); color: var(--red); border: 1px solid var(--red-bd); }
  .btn-ban:hover { background: rgba(224,85,85,.2); }
  .btn-del    { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-del:hover { color: var(--red); border-color: var(--red-bd); }
  .btn-sm     { padding: 5px 10px; font-size: 12px; }

  /* ── Table ── */
  .table-wrap { overflow-x: auto; }

  table { border-collapse: collapse; width: 100%; font-size: 13px; }
  thead th {
    text-align: left;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--muted);
    font-weight: 500;
    white-space: nowrap;
  }
  tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(255,255,255,.025); }
  td { padding: 11px 14px; vertical-align: middle; }

  .key-mono {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--muted2);
    background: var(--bg);
    padding: 3px 7px;
    border-radius: 5px;
    border: 1px solid var(--border);
    user-select: all;
    cursor: copy;
  }

  .badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .badge-green { background: var(--green-bg); color: var(--green); border: 1px solid var(--green-bd); }
  .badge-red   { background: var(--red-bg);   color: var(--red);   border: 1px solid var(--red-bd);   }
  .badge-yellow{ background: var(--yellow-bg);color: var(--yellow);border: 1px solid var(--yellow-bd); }

  .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

  .actions { display: flex; gap: 5px; flex-wrap: wrap; }
  form.act { display: contents; }

  .text-muted { color: var(--muted); }
  .dev-bar { display: flex; align-items: center; gap: 6px; }
  .dev-text { font-size: 12px; color: var(--muted2); white-space: nowrap; }

  /* ── Log table ── */
  .log-ok  { color: var(--green); }
  .log-bad { color: var(--red); }

  /* ── Modal ── */
  .modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.6);
    backdrop-filter: blur(3px);
    z-index: 100;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .modal-overlay.open { display: flex; }

  .modal {
    background: var(--surface);
    border: 1px solid var(--border-hi);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 400px;
    box-shadow: 0 32px 80px rgba(0,0,0,.6);
    animation: modal-in .15s ease;
  }

  @keyframes modal-in {
    from { transform: scale(.95); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
  }

  .modal-head {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
  }

  .modal-icon {
    width: 32px; height: 32px;
    background: rgba(91,138,245,.12);
    border: 1px solid rgba(91,138,245,.3);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: var(--accent);
    flex-shrink: 0;
  }

  .modal-title { font-size: 14px; font-weight: 600; }
  .modal-sub   { font-size: 12px; color: var(--muted); margin-top: 1px; }

  .modal-close {
    margin-left: auto;
    background: none; border: none; cursor: pointer;
    color: var(--muted); padding: 4px;
    border-radius: 5px;
    transition: color .15s;
  }
  .modal-close:hover { color: var(--text); }

  .modal-body { padding: 20px; display: flex; flex-direction: column; gap: 14px; }

  .modal-info {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 10px 14px;
    font-size: 12px;
    color: var(--muted2);
  }
  .modal-info span { font-family: 'JetBrains Mono', monospace; color: var(--text); }

  .modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

  .modal-foot {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    display: flex; gap: 8px; justify-content: flex-end;
  }

  .btn-extend {
    background: rgba(91,138,245,.15);
    color: var(--accent);
    border: 1px solid rgba(91,138,245,.3);
    font-size: 12px;
    padding: 5px 10px;
  }
  .btn-extend:hover { background: rgba(91,138,245,.25); }

  /* ── Search Bar ── */
  .search-wrap {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .search-icon {
    color: var(--muted);
    flex-shrink: 0;
    display: flex;
    align-items: center;
  }

  .search-input {
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: var(--radius);
    padding: 7px 11px 7px 32px;
    font-size: 13px;
    font-family: inherit;
    width: 100%;
    outline: none;
    transition: border-color .15s;
  }
  .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(91,138,245,.12); }
  .search-input::placeholder { color: var(--muted); }

  .search-box {
    position: relative;
    flex: 1;
    max-width: 360px;
  }
  .search-box .search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
  }

  .search-count {
    font-size: 11px;
    color: var(--muted);
    white-space: nowrap;
  }

  .no-result-row td {
    text-align: center;
    padding: 32px;
    color: var(--muted);
  }

  @media (max-width: 640px) {
    .stats { grid-template-columns: 1fr 1fr; }
    .gen-grid { grid-template-columns: 1fr 1fr; }
    .topbar-badge { display: none; }
    .page { padding: 16px 14px; }
    .modal-row { grid-template-columns: 1fr; }
    .search-box { max-width: 100%; }
  }
</style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
  <div class="topbar-logo">
    <div class="topbar-icon">
      <svg width="17" height="17" viewBox="0 0 20 20" fill="none">
        <rect x="3" y="8" width="14" height="9" rx="2" stroke="#fff" stroke-width="1.5"/>
        <path d="M7 8V6a3 3 0 0 1 6 0v2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="10" cy="12.5" r="1.5" fill="#fff"/>
      </svg>
    </div>
    <span class="topbar-name">License Admin</span>
    <span class="topbar-badge">Panel</span>
  </div>
  <a href="logout.php" class="topbar-logout">
    <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 2H2a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h3M9 10l3-3-3-3M5 7h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    Logout
  </a>
</header>

<main class="page">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-label">Total Keys</div>
      <div class="stat-val blue"><?= (int)$cnt['c'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Active</div>
      <div class="stat-val green"><?= (int)$cnt['a'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Banned</div>
      <div class="stat-val red"><?= (int)$cnt['b'] ?></div>
    </div>
  </div>

  <!-- Toast -->
  <?php if ($msg): ?>
  <div class="toast">
    <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="7.5" cy="7.5" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7.5l2 2 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <?= $msg ?>
  </div>
  <?php endif; ?>

  <!-- Generate Key -->
  <div class="section">
    <div class="section-head">
      <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="5.5" cy="9.5" r="3" stroke="var(--accent)" stroke-width="1.4"/><path d="M8 7l5-5M11 2l1.5 1.5M9.5 3.5l1.5 1.5" stroke="var(--accent)" stroke-width="1.4" stroke-linecap="round"/></svg>
      <span class="section-title">Generate Key Baru</span>
    </div>
    <div class="section-body">
      <form method="post">
        <input type="hidden" name="act" value="add">
        <div class="gen-grid">
          <div class="field">
            <label>Durasi</label>
            <select name="days">
              <option value="1">1 hari</option>
              <option value="7">7 hari</option>
              <option value="30" selected>30 hari</option>
              <option value="90">90 hari</option>
              <option value="365">365 hari</option>
              <option value="lifetime">Lifetime</option>
              <option value="custom">Custom</option>
            </select>
          </div>
          <div class="field">
            <label>Custom (hari)</label>
            <input type="number" name="custom_days" min="1" max="9999" value="" placeholder="1-9999">
          </div>
          <div class="field">
            <label>Max Devices</label>
            <input type="number" name="devices" min="1" max="9999" value="1" placeholder="1-9999">
          </div>
          <div class="field">
            <label>Catatan</label>
            <input type="text" name="note" placeholder="">
          </div>
          <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M6.5 1v11M1 6.5h11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
              Generate Key
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- License List -->
  <div class="section">
    <div class="section-head">
      <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><rect x="1.5" y="2.5" width="12" height="10" rx="1.5" stroke="var(--muted2)" stroke-width="1.4"/><path d="M4 6h7M4 9h5" stroke="var(--muted2)" stroke-width="1.3" stroke-linecap="round"/></svg>
      <span class="section-title">Daftar License</span>
      <span id="lic-count" style="margin-left:auto;font-size:11px;color:var(--muted)"><?= count($keys) ?> key</span>
    </div>
    <div class="search-wrap">
      <div class="search-box">
        <span class="search-icon">
          <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="5.5" cy="5.5" r="4" stroke="currentColor" stroke-width="1.4"/><path d="M9 9l2.5 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        </span>
        <input
          type="text"
          id="licSearchInput"
          class="search-input"
          placeholder=""
          oninput="filterLicenses(this.value)"
          autocomplete="off"
        >
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>License Key</th><th>Status</th><th>Expired</th>
            <th>Devices</th><th>Catatan</th><th>Last Used</th><th>IP</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody id="licTbody">
        <?php foreach ($keys as $k): ?>
        <?php
          $search_data = implode(' ', array_map('strtolower', [
            $k['id'],
            $k['license_key'],
            $k['status'],
            $k['note'] ?? '',
            $k['expiry'] ?? 'lifetime',
            $k['last_ip'] ?? '',
          ]));
        ?>
        <tr class="lic-row" data-search="<?= htmlspecialchars($search_data, ENT_QUOTES) ?>">
          <td class="text-muted"><?= (int)$k['id'] ?></td>
          <td><span class="key-mono" title="Klik untuk copy"><?= e($k['license_key']) ?></span></td>
          <td>
            <?php if ($k['status'] === 'active'): ?>
              <span class="badge badge-green"><span class="dot"></span>Active</span>
            <?php elseif ($k['status'] === 'banned'): ?>
              <span class="badge badge-red"><span class="dot"></span>Banned</span>
            <?php else: ?>
              <span class="badge badge-yellow"><?= e($k['status']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= $k['expiry'] ? '<span style="font-size:12px">'.e($k['expiry']).'</span>' : '<span class="badge badge-green">Lifetime</span>' ?></td>
          <td>
            <span class="dev-text"><?= (int)$k['dev_used'] ?>/<?= (int)$k['max_devices'] ?></span>
          </td>
          <td><span class="text-muted"><?= e($k['note']) ?: '—' ?></span></td>
          <td><span style="font-size:12px;color:var(--muted)"><?= e($k['last_used']) ?: '—' ?></span></td>
          <td><span style="font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace"><?= e($k['last_ip']) ?: '—' ?></span></td>
          <td>
            <div class="actions">
              <?php if ($k['status'] === 'active'): ?>
                <form class="act" method="post">
                  <input type="hidden" name="act" value="ban">
                  <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                  <button class="btn btn-sm btn-ban" type="submit">Ban</button>
                </form>
              <?php else: ?>
                <form class="act" method="post">
                  <input type="hidden" name="act" value="activate">
                  <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                  <button class="btn btn-sm btn-ghost" type="submit">Aktifkan</button>
                </form>
              <?php endif; ?>
              <?php if ($k['expiry'] !== null): ?>
              <button class="btn btn-sm btn-extend"
                onclick="openExtend(<?= (int)$k['id'] ?>, '<?= e($k['license_key']) ?>', '<?= e($k['expiry']) ?>')"
                type="button" title="Tambah Durasi">
                <svg width="11" height="11" viewBox="0 0 11 11" fill="none"><circle cx="5.5" cy="5.5" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M5.5 3v2.5l1.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                +Durasi
              </button>
              <?php endif; ?>
              <form class="act" method="post">
                <input type="hidden" name="act" value="resetdev">
                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit" title="Reset Devices">
                  <svg width="11" height="11" viewBox="0 0 11 11" fill="none"><path d="M1 5.5A4.5 4.5 0 0 1 9.5 3M10 1v2H8M10 5.5A4.5 4.5 0 0 1 1.5 8M1 10V8h2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Reset
                </button>
              </form>
              <form class="act" method="post" onsubmit="return confirm('Hapus key ini? Tindakan tidak bisa dibatalkan.')">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                <button class="btn btn-sm btn-del" type="submit">Hapus</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($keys)): ?>
        <tr><td colspan="10" style="text-align:center;padding:32px;color:var(--muted)">Belum ada license. Generate key pertama di atas.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Verify Logs -->
  <div class="section">
    <div class="section-head">
      <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><path d="M2 4h11M2 8h7M2 12h5" stroke="var(--muted2)" stroke-width="1.4" stroke-linecap="round"/></svg>
      <span class="section-title">Log Verifikasi</span>
      <span style="margin-left:auto;font-size:11px;color:var(--muted)">25 terakhir</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Waktu</th><th>License Key</th><th>HWID</th><th>IP</th><th>Hasil</th></tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td style="font-size:12px;color:var(--muted)"><?= e($l['created_at']) ?></td>
          <td><span class="key-mono"><?= e($l['license_key']) ?></span></td>
          <td style="font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--muted)"><?= e($l['hwid']) ?: '—' ?></td>
          <td style="font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--muted)"><?= e($l['ip']) ?></td>
          <td>
            <?php if ($l['result'] === 'valid'): ?>
              <span class="badge badge-green">Valid</span>
            <?php else: ?>
              <span class="badge badge-red"><?= e($l['result']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--muted)">Belum ada log verifikasi.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<!-- Modal Tambah Durasi -->
<div class="modal-overlay" id="extendModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-icon">
        <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 4.5v3l2 2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </div>
      <div>
        <div class="modal-title">Tambah Durasi Key</div>
        <div class="modal-sub" id="modal-key-label">—</div>
      </div>
      <button class="modal-close" onclick="closeExtend()" type="button">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </button>
    </div>
    <form method="post" id="extendForm">
      <input type="hidden" name="act" value="extendexp">
      <input type="hidden" name="id" id="modal-id">
      <div class="modal-body">
        <div class="modal-info">
          Expired sekarang: <span id="modal-expiry">—</span>
        </div>
        <div class="modal-row">
          <div class="field">
            <label>Tambah Durasi</label>
            <select name="ext_days" id="ext_days" onchange="toggleCustomExt(this.value)">
              <option value="7">7 hari</option>
              <option value="30" selected>30 hari</option>
              <option value="60">60 hari</option>
              <option value="90">90 hari</option>
              <option value="180">180 hari</option>
              <option value="365">365 hari</option>
              <option value="custom">Custom...</option>
            </select>
          </div>
          <div class="field" id="ext-custom-wrap" style="display:none">
            <label>Jumlah Hari</label>
            <input type="number" name="ext_custom_days" id="ext_custom_days" min="1" max="9999" value="14" placeholder="1–9999">
          </div>
        </div>
        <div style="font-size:12px;color:var(--muted);line-height:1.6">
          Durasi ditambahkan dari tanggal expired terakhir (atau hari ini jika sudah expired).
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" onclick="closeExtend()">Batal</button>
        <button type="submit" class="btn btn-primary">
          <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><circle cx="6.5" cy="6.5" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M6.5 4v2.5l1.5 1.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
          Tambah Durasi
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Copy license key on click
document.querySelectorAll('.key-mono').forEach(el => {
  el.addEventListener('click', () => {
    navigator.clipboard?.writeText(el.textContent.trim()).then(() => {
      const orig = el.style.color;
      el.style.color = 'var(--green)';
      setTimeout(() => el.style.color = orig, 900);
    });
  });
});

// Modal extend
function openExtend(id, key, expiry) {
  document.getElementById('modal-id').value = id;
  document.getElementById('modal-key-label').textContent = key;
  document.getElementById('modal-expiry').textContent = expiry;
  document.getElementById('extendModal').classList.add('open');
  document.getElementById('ext_days').value = '30';
  toggleCustomExt('30');
}

function closeExtend() {
  document.getElementById('extendModal').classList.remove('open');
}

function toggleCustomExt(val) {
  const wrap = document.getElementById('ext-custom-wrap');
  const inp  = document.getElementById('ext_custom_days');
  if (val === 'custom') {
    wrap.style.display = 'flex';
    inp.required = true;
  } else {
    wrap.style.display = 'none';
    inp.required = false;
  }
}

// Close modal on overlay click
document.getElementById('extendModal').addEventListener('click', function(e) {
  if (e.target === this) closeExtend();
});

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeExtend();
});

// License search / filter
function filterLicenses(query) {
  const q = query.trim().toLowerCase();
  const tbody = document.getElementById('licTbody');
  const rows  = tbody.querySelectorAll('tr.lic-row');
  let visible = 0;

  rows.forEach(row => {
    const text = row.dataset.search || '';
    const match = !q || text.includes(q);
    row.style.display = match ? '' : 'none';
    if (match) visible++;
  });

  // Update counter
  const total = rows.length;
  const countEl = document.getElementById('lic-count');
  if (countEl) {
    countEl.textContent = q ? `${visible} / ${total} key` : `${total} key`;
  }

  // Show/hide empty state row
  let emptyRow = document.getElementById('licEmptySearch');
  if (visible === 0 && q) {
    if (!emptyRow) {
      emptyRow = document.createElement('tr');
      emptyRow.id = 'licEmptySearch';
      emptyRow.className = 'no-result-row';
      const cols = tbody.closest('table').querySelectorAll('thead th').length;
      emptyRow.innerHTML = `<td colspan="${cols}">Tidak ada license yang cocok dengan "<strong>${query.trim()}</strong>".</td>`;
      tbody.appendChild(emptyRow);
    }
    emptyRow.style.display = '';
  } else if (emptyRow) {
    emptyRow.style.display = 'none';
  }
}
</script>
</body>
</html>
