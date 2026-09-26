<?php use App\Core\View; ?>
<a href="/index.php?r=clients" style="font-size:13px;color:#666">&larr; Retour aux clients</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($client['nom']) ?> <?php if ((int) $client['is_active'] !== 1): ?><span class="badge badge-gray">Inactif</span><?php endif; ?></h1>
    <div class="subtitle"><?= View::e($client['secteur']) ?: 'Client' ?></div>
  </div>
  <div style="white-space:nowrap">
    <a href="/index.php?r=clients/<?= $client['id'] ?>/modifier" class="btn btn-secondary">Modifier</a>
    <?php if ((int) $client['is_active'] === 1): ?>
      <form method="post" action="/index.php?r=clients/<?= $client['id'] ?>/desactiver" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-secondary">Désactiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="/index.php?r=clients/<?= $client['id'] ?>/activer" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn">Réactiver</button>
      </form>
    <?php endif; ?>
  </div>
</div>

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
