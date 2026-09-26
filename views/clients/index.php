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
    <tr><th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Pays</th><th>Secteur</th><th>Statut</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($clients as $c): ?>
    <tr onclick="window.location='/index.php?r=clients/<?= $c['id'] ?>'" style="cursor:pointer">
      <td><strong><?= View::e($c['nom']) ?></strong></td>
      <td><?= View::e($c['email']) ?: '—' ?></td>
      <td><?= View::e($c['telephone']) ?: '—' ?></td>
      <td><?= View::e($c['pays']) ?: '—' ?></td>
      <td><?= View::e($c['secteur']) ?: '—' ?></td>
      <td>
        <?php if ((int) $c['is_active'] === 1): ?>
          <span class="badge badge-green">Actif</span>
        <?php else: ?>
          <span class="badge badge-gray">Inactif</span>
        <?php endif; ?>
      </td>
      <td onclick="event.stopPropagation()" style="white-space:nowrap">
        <a href="/index.php?r=clients/<?= $c['id'] ?>/modifier" class="btn btn-sm btn-secondary">Modifier</a>
        <?php if ((int) $c['is_active'] === 1): ?>
          <form method="post" action="/index.php?r=clients/<?= $c['id'] ?>/desactiver" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm btn-secondary">Désactiver</button>
          </form>
        <?php else: ?>
          <form method="post" action="/index.php?r=clients/<?= $c['id'] ?>/activer" style="display:inline">
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
