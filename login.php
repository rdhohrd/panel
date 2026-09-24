<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['user'] ?? '';
    $p = $_POST['pass'] ?? '';
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>License Admin — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0d0f14;
    --surface:   #161a23;
    --border:    #252b38;
    --border-hi: #3a4256;
    --text:      #e2e6f0;
    --muted:     #6b7591;
    --accent:    #5b8af5;
    --accent-dk: #4070e0;
    --danger:    #e05555;
    --radius:    10px;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', system-ui, sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 40px 36px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 24px 64px rgba(0,0,0,.45);
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 28px;
  }

  .logo-icon {
    width: 38px;
    height: 38px;
    background: var(--accent);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .logo-icon svg { display: block; }

  .logo-text {
    font-size: 15px;
    font-weight: 600;
    letter-spacing: .01em;
    color: var(--text);
  }

  .logo-sub {
    font-size: 11px;
    color: var(--muted);
    margin-top: 1px;
  }

  h1 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 6px;
  }

  .subtitle {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 28px;
  }

  .field {
    margin-bottom: 16px;
  }

  label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: var(--muted);
    margin-bottom: 6px;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  input[type=text], input[type=password] {
    width: 100%;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: var(--radius);
    padding: 10px 14px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color .15s;
    outline: none;
  }

  input[type=text]:focus, input[type=password]:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(91,138,245,.15);
  }

  .btn {
    width: 100%;
    padding: 11px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: var(--radius);
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    margin-top: 8px;
    transition: background .15s;
  }

  .btn:hover { background: var(--accent-dk); }

  .error {
    background: rgba(224,85,85,.12);
    border: 1px solid rgba(224,85,85,.3);
    color: var(--danger);
    border-radius: var(--radius);
    padding: 10px 14px;
    font-size: 13px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
        <rect x="3" y="8" width="14" height="9" rx="2" stroke="#fff" stroke-width="1.5"/>
        <path d="M7 8V6a3 3 0 0 1 6 0v2" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="10" cy="12.5" r="1.5" fill="#fff"/>
      </svg>
    </div>
    <div>
      <div class="logo-text">License Admin</div>
      <div class="logo-sub">Management Panel</div>
    </div>
  </div>

  <h1>Masuk ke Panel</h1>
  <p class="subtitle">Masukkan kredensial administrator Anda.</p>

  <?php if ($error): ?>
  <div class="error">
    <svg width="15" height="15" viewBox="0 0 15 15" fill="none"><circle cx="7.5" cy="7.5" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M7.5 4.5v3M7.5 10h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
    <?= e($error) ?>
  </div>
  <?php endif; ?>

  <form method="post">
    <div class="field">
      <label for="user">Username</label>
      <input type="text" id="user" name="user" placeholder="admin" required autofocus>
    </div>
    <div class="field">
      <label for="pass">Password</label>
      <input type="password" id="pass" name="pass" placeholder="••••••••" required>
    </div>
    <button class="btn" type="submit">Masuk</button>
  </form>
</div>
</body>
</html>
