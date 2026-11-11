<?php
$flash = $_SESSION['flash'] ?? null;
if ($flash) unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mot de passe — Suivora360</title>
<link rel="icon" href="/favicon.ico?v=59">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=59">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0E3A4A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Suivora360">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="stylesheet" href="/assets/css/app.css">
<style>
* { box-sizing:border-box; }
body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#0E3A4A; padding:20px; }
.login-card { background:#fff; padding:36px; border-radius:12px; width:100%; max-width:440px; margin:0 auto; }
@media (max-width:480px) { .login-card { padding:26px 20px; } }
.login-logo { display:block; margin:0 auto 28px; max-width:360px; width:100%; height:auto; }
</style>
</head>
<body>
<div class="login-card">
  <img src="/assets/img/logo-full.png?v=59" alt="Suivora360 — La maîtrise de vos opérations." class="login-logo">
  <?php if (!empty($organisation)): ?>
    <div style="text-align:center;margin:-10px 0 18px;font-size:15px;color:#4b5563">Espace de <strong style="color:#111827"><?= htmlspecialchars($organisation['nom']) ?></strong></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'erreur' ? 'erreur' : 'succes' ?>"><?= htmlspecialchars($flash['message']) ?></div>
  <?php endif; ?>
  <?php if (empty($valide)): ?>
    <h2 style="margin:0 0 10px;font-size:18px;text-align:center">Lien expiré</h2>
    <div class="alert alert-erreur">Ce lien n'est plus valable (expiré ou déjà utilisé). Demandez-en un nouveau.</div>
    <a class="btn" href="/index.php?r=mot-de-passe-oublie" style="width:100%;justify-content:center;text-align:center;display:flex">Demander un nouveau lien</a>
  <?php else: ?>
  <h2 style="margin:0 0 6px;font-size:18px;text-align:center">Nouveau mot de passe</h2>
  <p style="font-size:13px;color:#5B6B7B;margin:0 0 14px">Pour <?= htmlspecialchars($email) ?></p>
  <form method="post" action="/index.php?r=mot-de-passe/reinitialiser">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="jeton" value="<?= htmlspecialchars($jeton) ?>">
    <div class="form-group">
      <label>Nouveau mot de passe (8 caractères au moins)</label>
      <input type="password" name="nouveau_mot_de_passe" required minlength="8" autocomplete="new-password" autofocus>
    </div>
    <div class="form-group">
      <label>Confirmer le mot de passe</label>
      <input type="password" name="confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn" style="width:100%;justify-content:center;text-align:center">Enregistrer</button>
  </form>
  <?php endif; ?>
</div>
<script src="/assets/js/mdp.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/mdp.js') ?>"></script>
<script>if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); }); }</script>
</body>
</html>
