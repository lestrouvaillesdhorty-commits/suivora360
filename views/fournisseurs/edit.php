<?php use App\Core\View;
$paysListe = ['Cameroun', 'France', "Côte d'Ivoire", 'Sénégal', 'Mali', 'Togo', 'Bénin', 'Gabon', 'Congo (Brazzaville)', 'RD Congo', 'Nigeria', 'Ghana', 'Maroc', 'Tunisie', 'Algérie', 'Belgique', 'Allemagne', 'Chine', 'Émirats arabes unis', 'Inde', 'Turquie', 'États-Unis'];
?>
<a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au fournisseur</a>

<h1>Modifier <?= View::e($fournisseur['nom']) ?></h1>
<div class="subtitle">Mettre à jour les informations du fournisseur</div>

<div class="card">
<form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/modifier">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <div class="form-group">
    <label>Nom *</label>
    <input type="text" name="nom" value="<?= View::e($fournisseur['nom']) ?>" required>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= View::e($fournisseur['email']) ?>"></div>
    <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= View::e($fournisseur['telephone']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Pays</label>
      <select name="pays">
        <option value="">—</option>
        <?php if ($fournisseur['pays'] && !in_array($fournisseur['pays'], $paysListe, true)): ?>
          <option selected><?= View::e($fournisseur['pays']) ?></option>
        <?php endif; ?>
        <?php foreach ($paysListe as $p): ?>
          <option <?= $fournisseur['pays'] === $p ? 'selected' : '' ?>><?= View::e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Ville</label><input type="text" name="ville" value="<?= View::e($fournisseur['ville']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Devise</label>
      <select name="devise">
        <option value="">—</option>
        <?php foreach (['EUR', 'USD', 'XAF', 'GBP', 'CHF'] as $d): ?>
          <option <?= $fournisseur['devise'] === $d ? 'selected' : '' ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Site web</label><input type="text" name="site_web" value="<?= View::e($fournisseur['site_web']) ?>"></div>
  </div>

  <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= View::e($fournisseur['adresse']) ?>"></div>
  <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur" value="<?= View::e($fournisseur['secteur']) ?>"></div>
  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($fournisseur['notes']) ?></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Enregistrer</button>
    <a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
