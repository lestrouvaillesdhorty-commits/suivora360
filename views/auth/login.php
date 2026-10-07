<?php
$flash = $_SESSION['flash'] ?? null;
if ($flash) unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — Suivora360</title>
<link rel="icon" href="/favicon.ico">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/css/app.css">
<style>
body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#1a1d29; }
.login-card { background:#fff; padding:36px; border-radius:12px; width:100%; max-width:440px; }
.login-logo { display:block; margin:0 auto 28px; max-width:360px; width:100%; height:auto; }
</style>
</head>
<body>
<div class="login-card">
  <img src="/assets/img/logo-full.png" alt="Suivora360 — La maîtrise de vos opérations." class="login-logo">
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($flash['message']) ?></div>
  <?php endif; ?>
  <form method="post" action="/index.php?r=login">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required autofocus>
    </div>
    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn" style="width:100%">Se connecter</button>
  </form>
</div>
</body>
</html>
