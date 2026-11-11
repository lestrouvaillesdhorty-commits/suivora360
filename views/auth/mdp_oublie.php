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
  <?php if (!empty($envoye)): ?>
    <h2 style="margin:0 0 10px;font-size:18px;text-align:center">Vérifiez votre boîte mail</h2>
    <div class="alert alert-succes">Si cette adresse correspond à un compte, un e-mail vient de partir. Le lien est valable 1 heure.</div>
    <p style="font-size:13px;color:#5B6B7B">Rien reçu ? Regardez dans les courriers indésirables, ou demandez à votre administrateur de réinitialiser votre mot de passe.</p>
  <?php elseif (empty($disponible)): ?>
    <div class="alert alert-erreur">Cette fonction n'est pas encore active. Demandez à votre administrateur de réinitialiser votre mot de passe.</div>
  <?php else: ?>
  <h2 style="margin:0 0 6px;font-size:18px;text-align:center">Mot de passe oublié</h2>
  <p style="font-size:13px;color:#5B6B7B;margin:0 0 14px">Indiquez votre adresse e-mail. Nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
  <form method="post" action="/index.php?r=mot-de-passe-oublie">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required autofocus>
    </div>
    <button type="submit" class="btn" style="width:100%;justify-content:center;text-align:center">Envoyer le lien</button>
  </form>
  <?php endif; ?>
  <p style="text-align:center;margin:16px 0 0;font-size:14px"><a href="/index.php?r=login" style="color:#0B7C8A;font-weight:600">Retour à la connexion</a></p>
</div>
<script src="/assets/js/mdp.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/mdp.js') ?>"></script>
<script>if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); }); }</script>
</body>
</html>
