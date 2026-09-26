<?php use App\Core\View; ?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <div>
    <h1>Fournisseurs</h1>
    <div class="subtitle"><?= count($fournisseurs) ?> fournisseur<?= count($fournisseurs) > 1 ? 's' : '' ?></div>
  </div>
  <a href="/index.php?r=fournisseurs/nouveau" class="btn">+ Nouveau fournisseur</a>
</div>

<?php if (empty($fournisseurs)): ?>
  <div class="card"><div class="empty-state">Aucun fournisseur pour le moment.</div></div>
<?php else: ?>
<table>
  <thead>
    <tr><th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Pays</th><th>Devise</th></tr>
  </thead>
  <tbody>
    <?php foreach ($fournisseurs as $f): ?>
    <tr onclick="window.location='/index.php?r=fournisseurs/<?= $f['id'] ?>'" style="cursor:pointer">
      <td><strong><?= View::e($f['nom']) ?></strong></td>
      <td><?= View::e($f['email']) ?: '—' ?></td>
      <td><?= View::e($f['telephone']) ?: '—' ?></td>
      <td><?= View::e($f['pays']) ?: '—' ?></td>
      <td><?= View::e($f['devise']) ?: '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
