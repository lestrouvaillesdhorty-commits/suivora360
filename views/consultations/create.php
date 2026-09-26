<?php use App\Core\View; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Nouvelle consultation fournisseur</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<div class="card" style="max-width:640px">
  <?php if (empty($fournisseurs)): ?>
    <div class="empty-state">Aucun fournisseur enregistré. <a href="/index.php?r=fournisseurs/nouveau">Ajouter un fournisseur</a> avant de continuer.</div>
  <?php else: ?>
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/consultations">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label>Fournisseur consulté</label>
      <select name="fournisseur_id" required>
        <option value="">— Sélectionner —</option>
        <?php foreach ($fournisseurs as $f): ?>
          <option value="<?= $f['id'] ?>"><?= View::e($f['nom']) ?><?= $f['pays'] ? ' (' . View::e($f['pays']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Date d'envoi</label><input type="date" name="date_envoi" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="form-group">
      <label>Articles / besoins communiqués au fournisseur</label>
      <textarea name="articles_demandes" rows="4" placeholder="Décrivez les articles, quantités et spécifications transmises pour consultation..."></textarea>
    </div>
    <div class="form-group">
      <label>Notes internes</label>
      <textarea name="notes" rows="3"></textarea>
    </div>
    <button type="submit" class="btn">Enregistrer la consultation</button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
  <?php endif; ?>
</div>
