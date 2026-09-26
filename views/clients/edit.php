<?php use App\Core\View; ?>
<a href="/index.php?r=clients/<?= $client['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au client</a>

<h1>Modifier <?= View::e($client['nom']) ?></h1>
<div class="subtitle">Mettre à jour les informations du client</div>

<div class="card">
<form method="post" action="/index.php?r=clients/<?= $client['id'] ?>/modifier">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <div class="form-group">
    <label>Nom *</label>
    <input type="text" name="nom" value="<?= View::e($client['nom']) ?>" required>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= View::e($client['email']) ?>"></div>
    <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= View::e($client['telephone']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Pays</label>
      <input type="text" name="pays" list="pays-list" value="<?= View::e($client['pays']) ?>">
    </div>
    <div class="form-group"><label>Ville</label><input type="text" name="ville" value="<?= View::e($client['ville']) ?>"></div>
  </div>

  <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= View::e($client['adresse']) ?>"></div>
  <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur" value="<?= View::e($client['secteur']) ?>"></div>
  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($client['notes']) ?></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Enregistrer</button>
    <a href="/index.php?r=clients/<?= $client['id'] ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
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
