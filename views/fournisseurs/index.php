<?php use App\Core\View; use App\Models\Fournisseur; ?>
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
    <tr><th>Code</th><th>Nom</th><th>Contact</th><th>Pays</th><th>Note</th><th>Statut</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($fournisseurs as $f): ?>
    <?php $wa = preg_replace('/[^0-9]/', '', $f['telephone'] ?? ''); $note = Fournisseur::noteGlobale($f); ?>
    <tr onclick="window.location='/index.php?r=fournisseurs/<?= $f['id'] ?>'" style="cursor:pointer">
      <td><?= View::e($f['code'] ?? '') ?: '—' ?></td>
      <td><strong><?= View::e($f['nom']) ?></strong></td>
      <td onclick="event.stopPropagation()">
        <?= View::e($f['email']) ?: '' ?> <?= View::e($f['telephone']) ?: '' ?>
        <?php if ($wa): ?> <a href="https://wa.me/<?= $wa ?>" target="_blank" title="WhatsApp">💬</a><?php endif; ?>
        <?php if (!empty($f['email'])): ?> <a href="mailto:<?= View::e($f['email']) ?>" title="E-mail">✉️</a><?php endif; ?>
      </td>
      <td><?= View::e($f['pays']) ?: '—' ?></td>
      <td><?= $note !== null ? $note . '/5' : '—' ?></td>
      <td>
        <?php $sc = Fournisseur::STATUT_BADGES[$f['statut'] ?? 'a_qualifier'] ?? 'badge-gray'; ?>
        <span class="badge <?= $sc ?>"><?= View::e(Fournisseur::STATUTS[$f['statut'] ?? 'a_qualifier'] ?? ucfirst($f['statut'] ?? '')) ?></span>
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
