<?php use App\Core\View; ?>
<a href="/index.php?r=fournisseurs" style="font-size:13px;color:#666">&larr; Retour aux fournisseurs</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($fournisseur['nom']) ?> <?php if ((int) $fournisseur['is_active'] !== 1): ?><span class="badge badge-gray">Inactif</span><?php endif; ?></h1>
    <div class="subtitle"><?= View::e($fournisseur['secteur']) ?: 'Fournisseur' ?><?= $fournisseur['devise'] ? ' — ' . View::e($fournisseur['devise']) : '' ?></div>
  </div>
  <div style="white-space:nowrap">
    <a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/modifier" class="btn btn-secondary">Modifier</a>
    <?php if ((int) $fournisseur['is_active'] === 1): ?>
      <form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/desactiver" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-secondary">Désactiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/activer" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn">Réactiver</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Coordonnées</h2>
  <div class="info-row"><span class="label">E-mail</span><span><?= View::e($fournisseur['email']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Téléphone</span><span><?= View::e($fournisseur['telephone']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Pays</span><span><?= View::e($fournisseur['pays']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Ville</span><span><?= View::e($fournisseur['ville']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Adresse</span><span><?= View::e($fournisseur['adresse']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Site web</span><span><?= View::e($fournisseur['site_web']) ?: '—' ?></span></div>
</div>

<?php if (!empty($fournisseur['notes'])): ?>
<div class="card">
  <h2>Notes</h2>
  <div><?= nl2br(View::e($fournisseur['notes'])) ?></div>
</div>
<?php endif; ?>
