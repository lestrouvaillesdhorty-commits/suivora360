<?php use App\Core\View;
$paysListe = ['Cameroun', 'France', "Côte d'Ivoire", 'Sénégal', 'Mali', 'Togo', 'Bénin', 'Gabon', 'Congo (Brazzaville)', 'RD Congo', 'Nigeria', 'Ghana', 'Maroc', 'Tunisie', 'Algérie', 'Belgique', 'Allemagne', 'Chine', 'Émirats arabes unis', 'Inde', 'Turquie', 'États-Unis'];
?>
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
      <select name="pays">
        <option value="">—</option>
        <?php if ($client['pays'] && !in_array($client['pays'], $paysListe, true)): ?>
          <option selected><?= View::e($client['pays']) ?></option>
        <?php endif; ?>
        <?php foreach ($paysListe as $p): ?>
          <option <?= $client['pays'] === $p ? 'selected' : '' ?>><?= View::e($p) ?></option>
        <?php endforeach; ?>
      </select>
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
