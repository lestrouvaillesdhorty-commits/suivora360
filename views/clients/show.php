<?php use App\Core\View; ?>
<a href="/index.php?r=clients" style="font-size:13px;color:#666">&larr; Retour aux clients</a>

<h1><?= View::e($client['nom']) ?></h1>
<div class="subtitle"><?= View::e($client['secteur']) ?: 'Client' ?></div>

<div class="card">
  <h2>Coordonnées</h2>
  <div class="info-row"><span class="label">E-mail</span><span><?= View::e($client['email']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Téléphone</span><span><?= View::e($client['telephone']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Pays</span><span><?= View::e($client['pays']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Ville</span><span><?= View::e($client['ville']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Adresse</span><span><?= View::e($client['adresse']) ?: '—' ?></span></div>
</div>

<?php if (!empty($client['notes'])): ?>
<div class="card">
  <h2>Notes</h2>
  <div><?= nl2br(View::e($client['notes'])) ?></div>
</div>
<?php endif; ?>
