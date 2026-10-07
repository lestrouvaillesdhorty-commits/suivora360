<?php
/**
 * Formulaire de filtres commun aux listes transverses.
 * Variables attendues : $route, $filters, $filiales, $selects (liste de [nom, libellé, options clé=>libellé, libellé du « tout »]),
 * $placeholderRecherche.
 */
use App\Core\View;
?>
<form method="get" action="/index.php" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="r" value="<?= View::e($route) ?>">
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Recherche</label>
    <input type="text" name="q" placeholder="<?= View::e($placeholderRecherche ?? 'Référence, dossier...') ?>" value="<?= View::e($filters['q'] ?? '') ?>" style="max-width:220px">
  </div>
  <?php foreach ($selects as [$nom, $libelle, $options, $tout]): ?>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px"><?= View::e($libelle) ?></label>
    <select name="<?= View::e($nom) ?>">
      <option value=""><?= View::e($tout) ?></option>
      <?php foreach ($options as $cle => $lib): ?>
        <option value="<?= View::e((string) $cle) ?>" <?= ($filters[$nom] ?? '') === (string) $cle ? 'selected' : '' ?>><?= View::e($lib) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endforeach; ?>
  <?php if (count($filiales) > 1): ?>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Filiale</label>
    <select name="filiale_id">
      <option value="">Toutes</option>
      <?php foreach ($filiales as $fi): ?>
        <option value="<?= (int) $fi['id'] ?>" <?= ($filters['filiale_id'] ?? '') === (string) $fi['id'] ? 'selected' : '' ?>><?= View::e($fi['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Créé du</label>
    <input type="date" name="date_debut" value="<?= View::e($filters['date_debut'] ?? '') ?>">
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">au</label>
    <input type="date" name="date_fin" value="<?= View::e($filters['date_fin'] ?? '') ?>">
  </div>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
  <a href="/index.php?r=<?= View::e($route) ?>" class="btn btn-secondary">Réinitialiser</a>
</form>
