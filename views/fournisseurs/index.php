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
    <tr><th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Pays</th><th>Devise</th><th>Statut</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($fournisseurs as $f): ?>
    <tr onclick="window.location='/index.php?r=fournisseurs/<?= $f['id'] ?>'" style="cursor:pointer">
      <td><strong><?= View::e($f['nom']) ?></strong></td>
      <td><?= View::e($f['email']) ?: '—' ?></td>
      <td><?= View::e($f['telephone']) ?: '—' ?></td>
      <td><?= View::e($f['pays']) ?: '—' ?></td>
      <td><?= View::e($f['devise']) ?: '—' ?></td>
      <td>
        <?php if ((int) $f['is_active'] === 1): ?>
          <span class="badge badge-green">Actif</span>
        <?php else: ?>
          <span class="badge badge-gray">Inactif</span>
        <?php endif; ?>
      </td>
      <td onclick="event.stopPropagation()" style="white-space:nowrap">
        <a href="/index.php?r=fournisseurs/<?= $f['id'] ?>/modifier" class="btn btn-sm btn-secondary">Modifier</a>
        <?php if ((int) $f['is_active'] === 1): ?>
          <form method="post" action="/index.php?r=fournisseurs/<?= $f['id'] ?>/desactiver" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm btn-secondary">Désactiver</button>
          </form>
        <?php else: ?>
          <form method="post" action="/index.php?r=fournisseurs/<?= $f['id'] ?>/activer" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm">Réactiver</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
