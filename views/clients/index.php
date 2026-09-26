<?php use App\Core\View; use App\Models\Client; ?>
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
    <tr><th>Code</th><th>Nom</th><th>Type</th><th>Contact</th><th>Pays</th><th>Statut</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($clients as $c): ?>
    <?php $wa = preg_replace('/[^0-9]/', '', $c['telephone'] ?? ''); ?>
    <tr onclick="window.location='/index.php?r=clients/<?= $c['id'] ?>'" style="cursor:pointer">
      <td><?= View::e($c['code'] ?? '') ?: '—' ?></td>
      <td><strong><?= View::e($c['nom']) ?></strong></td>
      <td><?= !empty($c['type']) ? View::e(Client::TYPES[$c['type']] ?? $c['type']) : '—' ?></td>
      <td onclick="event.stopPropagation()">
        <?= View::e($c['email']) ?: '' ?> <?= View::e($c['telephone']) ?: '' ?>
        <?php if ($wa): ?> <a href="https://wa.me/<?= $wa ?>" target="_blank" title="WhatsApp">💬</a><?php endif; ?>
        <?php if (!empty($c['email'])): ?> <a href="mailto:<?= View::e($c['email']) ?>" title="E-mail">✉️</a><?php endif; ?>
      </td>
      <td><?= View::e($c['pays']) ?: '—' ?></td>
      <td>
        <?php $sc = Client::STATUT_BADGES[$c['statut'] ?? 'actif'] ?? 'badge-gray'; ?>
        <span class="badge <?= $sc ?>"><?= View::e(Client::STATUTS[$c['statut'] ?? 'actif'] ?? ucfirst($c['statut'] ?? '')) ?></span>
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
