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
<link rel="stylesheet" href="/assets/css/app.css">
<style>
body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#1a1d29; }
.login-card { background:#fff; padding:36px; border-radius:12px; width:100%; max-width:380px; }
.login-card h1 { text-align:center; margin-bottom:24px; }
</style>
</head>
<body>
<div class="login-card">
  <h1>Suivora360</h1>
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
