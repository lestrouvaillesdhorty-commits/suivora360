<?php
use App\Core\View;
use App\Models\Demande;
$activiteListe = Demande::ACTIVITES;
?>
<h1>Nouvelle demande</h1>
<div class="subtitle">Enregistrer une nouvelle demande client</div>

<div class="card">
<form method="post" action="/index.php?r=demandes">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <?php if (count($filiales) > 1): ?>
  <div class="form-group">
    <label>Filiale</label>
    <select name="filiale_id" required>
      <?php foreach ($filiales as $f): ?>
        <option value="<?= $f['id'] ?>"><?= View::e($f['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php elseif (count($filiales) === 1): ?>
    <input type="hidden" name="filiale_id" value="<?= $filiales[0]['id'] ?>">
  <?php else: ?>
    <div class="alert alert-erreur">Aucune filiale ne vous est assignée. Contactez votre administrateur.</div>
  <?php endif; ?>

  <div class="form-group">
    <label>Objet *</label>
    <input type="text" name="objet" required>
  </div>

  <div class="form-group">
    <label>Message</label>
    <textarea name="message" rows="3"></textarea>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Canal</label>
      <select name="canal">
        <option>Formulaire</option>
        <option>WhatsApp</option>
        <option>Email</option>
        <option>Téléphone</option>
      </select>
    </div>
    <div class="form-group">
      <label>Reçue le</label>
      <input type="date" name="recue_le" value="<?= date('Y-m-d') ?>">
    </div>
  </div>

  <h2 style="font-size:15px;margin-top:24px">Expéditeur</h2>
  <div class="form-row">
    <div class="form-group"><label>Nom</label><input type="text" name="expediteur_nom"></div>
    <div class="form-group"><label>Entreprise</label><input type="text" name="expediteur_entreprise"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="expediteur_email"></div>
    <div class="form-group"><label>Téléphone</label><input type="tel" name="expediteur_telephone"></div>
  </div>

  <h2 style="font-size:15px;margin-top:24px">Suivi</h2>
  <div class="form-row">
    <div class="form-group">
      <label>Activité</label>
      <select name="activite">
        <option value="">—</option>
        <?php foreach ($activiteListe as $a): ?>
          <option><?= View::e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Urgence</label>
      <select name="priorite">
        <?php foreach (Demande::PRIORITES as $code => $label): ?>
          <option value="<?= $code ?>" <?= $code === 'normale' ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Responsable</label>
      <select name="responsable_id">
        <option value="">— Non assigné —</option>
        <?php foreach ($utilisateurs as $u): ?>
          <option value="<?= $u['id'] ?>"><?= View::e($u['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Échéance</label>
      <input type="date" name="echeance">
    </div>
  </div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Créer la demande</button>
    <a href="/index.php?r=demandes" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
