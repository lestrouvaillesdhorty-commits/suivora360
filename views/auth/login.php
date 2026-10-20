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
* { box-sizing:border-box; }
body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#1a1d29; padding:20px; }
.login-card { background:#fff; padding:36px; border-radius:12px; width:100%; max-width:440px; margin:0 auto; }
@media (max-width:480px) { .login-card { padding:26px 20px; } }
.login-logo { display:block; margin:0 auto 28px; max-width:360px; width:100%; height:auto; }
</style>
</head>
<body>
<div class="login-card">
  <img src="/assets/img/logo-full.png" alt="Suivora360 — La maîtrise de vos opérations." class="login-logo">
  <?php if (!empty($organisation)): ?>
    <div style="text-align:center;margin:-10px 0 18px;font-size:15px;color:#4b5563">Espace de <strong style="color:#111827"><?= htmlspecialchars($organisation['nom']) ?></strong></div>
  <?php endif; ?>
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
    <button type="submit" class="btn" style="width:100%;justify-content:center;text-align:center">Se connecter</button>
  </form>
</div>
</body>
</html>
