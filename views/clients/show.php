<?php
use App\Core\Auth;
use App\Core\View;
use App\Models\Client;

/**
 * Fiche client à 5 onglets (07/10) : Vue d'ensemble, Contacts et adresses,
 * Demandes et dossiers, Documents, Finances (selon les droits).
 */
$actif = (int) $client['is_active'] === 1;
$relation = $client['relation'] ?? 'client';
$base = '/index.php?r=clients/' . (int) $client['id'];
$onglets = [
    'vue' => "Vue d'ensemble",
    'contacts' => 'Contacts et adresses',
    'operations' => 'Demandes et dossiers',
    'documents' => 'Documents',
];
if ($financesAutorisees) {
    $onglets['finances'] = 'Finances';
}
$contactPrincipal = trim(($client['contact_prenom'] ?? '') . ' ' . ($client['contact_nom'] ?? ''));
?>
<a href="<?= View::e($retour) ?>" style="font-size:13px;color:#666">&larr; Retour aux clients</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-top:8px">
  <div>
    <h1 style="margin-bottom:4px"><?= View::e($client['nom']) ?></h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span style="color:#666;font-size:14px"><?= View::e($client['code'] ?? '') ?></span>
      <?php if (!empty($client['type'])): ?><span class="badge badge-gray"><?= View::e(Client::TYPES[$client['type']] ?? $client['type']) ?></span><?php endif; ?>
      <?php if ($schemaPret): ?><span class="badge <?= Client::RELATIONS_BADGES[$relation] ?? 'badge-gray' ?>"><?= View::e(Client::RELATIONS[$relation] ?? $relation) ?></span><?php endif; ?>
      <span class="badge <?= $actif ? 'badge-green' : 'badge-gray' ?>"><?= $actif ? 'Actif' : 'Inactif' ?></span>
    </div>
  </div>
  <?php if ($peutEcrire): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= $base ?>/modifier" class="btn btn-secondary">Modifier</a>
    <?php if ($actif): ?>
      <a href="/index.php?r=demandes/nouvelle&client_id=<?= (int) $client['id'] ?>" class="btn">Nouvelle demande</a>
      <form method="post" action="<?= $base ?>/desactiver" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="retour" value="fiche">
        <button type="submit" class="btn btn-secondary">Désactiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= $base ?>/activer" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="retour" value="fiche">
        <button type="submit" class="btn">Réactiver</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!$actif): ?>
  <div class="alert" style="background:#f1f2f5;color:#4b5563;margin-top:12px">Ce client est inactif : ses demandes, dossiers et documents restent consultables. Réactivez-le pour créer de nouvelles demandes.</div>
<?php endif; ?>

<div class="tabs" style="margin-top:16px;overflow-x:auto;white-space:nowrap">
  <?php foreach ($onglets as $code => $lib): ?>
    <a href="<?= $base . ($code === 'vue' ? '' : '&onglet=' . $code) ?>" class="<?= $onglet === $code ? 'active' : '' ?>"><?= View::e($lib) ?><?php
      if ($code === 'operations' && count($operations) > 0): ?> (<?= count($operations) ?>)<?php endif;
      if ($code === 'documents' && count($pieces) > 0): ?> (<?= count($pieces) ?>)<?php endif; ?></a>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/_tab_' . $onglet . '.php'; ?>
