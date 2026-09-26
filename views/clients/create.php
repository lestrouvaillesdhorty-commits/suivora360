<?php use App\Core\View; ?>
<h1>Nouveau client</h1>
<div class="subtitle">Enregistrer un client</div>

<div class="card">
<form method="post" action="/index.php?r=clients">
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
    <div class="alert alert-erreur">Aucune filiale ne vous est assignée.</div>
  <?php endif; ?>

  <div class="form-group">
    <label>Nom *</label>
    <input type="text" name="nom" required>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
    <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Pays</label><input type="text" name="pays" list="pays-list"></div>
    <div class="form-group"><label>Ville</label><input type="text" name="ville"></div>
  </div>

  <div class="form-group"><label>Adresse</label><input type="text" name="adresse"></div>
  <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur"></div>
  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Créer le client</button>
    <a href="/index.php?r=clients" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>

<datalist id="pays-list">
  <option value="Cameroun">
  <option value="France">
  <option value="Côte d'Ivoire">
  <option value="Sénégal">
  <option value="Mali">
  <option value="Togo">
  <option value="Bénin">
  <option value="Gabon">
  <option value="Congo (Brazzaville)">
  <option value="RD Congo">
  <option value="Nigeria">
  <option value="Ghana">
  <option value="Maroc">
  <option value="Tunisie">
  <option value="Algérie">
  <option value="Belgique">
  <option value="Allemagne">
  <option value="Chine">
  <option value="Émirats arabes unis">
  <option value="Inde">
  <option value="Turquie">
  <option value="États-Unis">
</datalist>
