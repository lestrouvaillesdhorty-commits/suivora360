<?php use App\Core\View; ?>
<a href="/index.php?r=fournisseurs" style="font-size:13px;color:#666">&larr; Retour aux fournisseurs</a>

<h1><?= View::e($fournisseur['nom']) ?></h1>
<div class="subtitle"><?= View::e($fournisseur['secteur']) ?: 'Fournisseur' ?><?= $fournisseur['devise'] ? ' — ' . View::e($fournisseur['devise']) : '' ?></div>

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
