<?php use App\Core\View; ?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <div>
    <h1>Clients</h1>
    <div class="subtitle"><?= count($clients) ?> client<?= count($clients) > 1 ? 's' : '' ?></div>
  </div>
  <a href="/index.php?r=clients/nouveau" class="btn">+ Nouveau client</a>
</div>

<?php if (empty($clients)): ?>
  <div class="card"><div class="empty-state">Aucun client pour le moment.</div></div>
<?php else: ?>
<table>
  <thead>
    <tr><th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Pays</th><th>Secteur</th></tr>
  </thead>
  <tbody>
    <?php foreach ($clients as $c): ?>
    <tr onclick="window.location='/index.php?r=clients/<?= $c['id'] ?>'" style="cursor:pointer">
      <td><strong><?= View::e($c['nom']) ?></strong></td>
      <td><?= View::e($c['email']) ?: '—' ?></td>
      <td><?= View::e($c['telephone']) ?: '—' ?></td>
      <td><?= View::e($c['pays']) ?: '—' ?></td>
      <td><?= View::e($c['secteur']) ?: '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
