<?php use App\Core\View; ?>
<a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Modifier la consultation</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?> · pas encore envoyée</div>

<div class="card" style="max-width:640px">
  <form method="post" action="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/modifier">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label for="selFournisseur">Fournisseur consulté</label>
      <select name="fournisseur_id" id="selFournisseur">
        <?php foreach ($fournisseurs as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= (int) $consultation['fournisseur_id'] === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?><?= $f['pays'] ? ' (' . View::e($f['pays']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (!empty($articlesDemande)): ?>
    <div class="form-group">
      <input type="hidden" name="articles_choix" value="1">
      <label>Articles à envoyer à ce fournisseur (<?= count($articlesDemande) ?> dans la demande)</label>
      <?php foreach ($articlesDemande as $a): ?>
        <?php $coche = $articlesIds === null || in_array((int) $a['id'], $articlesIds, true); ?>
        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:400;padding:3px 0">
          <input type="checkbox" name="articles[]" value="<?= (int) $a['id'] ?>" <?= $coche ? 'checked' : '' ?> style="width:auto;margin-top:3px">
          <span><?= View::e(trim(($a['quantite'] !== null && $a['quantite'] !== '' ? rtrim(rtrim(number_format((float) $a['quantite'], 2, ',', ''), '0'), ',') . ' ' : '') . ($a['unite'] ?? '') . ' ' . $a['designation'])) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label>Précisions complémentaires pour le fournisseur</label>
      <textarea name="articles_demandes" rows="4"><?= View::e($consultation['articles_demandes']) ?></textarea>
    </div>
    <div class="form-group">
      <label>Notes internes</label>
      <textarea name="notes" rows="3"><?= View::e($consultation['notes']) ?></textarea>
    </div>
    <button type="submit" class="btn">Enregistrer les modifications</button>
    <a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>
