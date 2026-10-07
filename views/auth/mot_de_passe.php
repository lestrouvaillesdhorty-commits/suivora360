<?php use App\Core\View; ?>
<h1>Mon mot de passe</h1>
<?php if (!empty($obligatoire)): ?>
<div class="subtitle">Votre mot de passe actuel est provisoire. Choisissez-en un nouveau, que vous seul connaîtrez, pour accéder à l'application.</div>
<?php else: ?>
<div class="subtitle">Changez votre mot de passe de connexion.</div>
<?php endif; ?>

<div class="card" style="max-width:480px">
  <form method="post" action="/index.php?r=mon-mot-de-passe" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group"><label>Mot de passe actuel *</label><input type="password" name="mot_de_passe_actuel" required autocomplete="current-password"></div>
    <div class="form-group"><label>Nouveau mot de passe * (8 caractères minimum)</label><input type="password" name="nouveau_mot_de_passe" required minlength="8" autocomplete="new-password"></div>
    <div class="form-group"><label>Confirmer le nouveau mot de passe *</label><input type="password" name="confirmation" required minlength="8" autocomplete="new-password"></div>
    <button type="submit" class="btn">Changer mon mot de passe</button>
  </form>
</div>
