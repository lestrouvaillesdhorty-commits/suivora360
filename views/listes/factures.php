<?php use App\Core\View; use App\Models\Facture; use App\Models\Pilotage;
$route = 'factures';
$selects = [
  ['statut', 'Statut', Facture::STATUTS + ['en_retard' => 'Échéance dépassée'], 'Tous'],
  ['type', 'Type', Facture::TYPES, 'Tous'],
];
$placeholderRecherche = 'Référence, dossier...';
$aEncaisser = [];
foreach ($lignes as $fa) {
    if ($fa['statut'] === 'emise') {
        $dv = (string) ($fa['devise'] ?: 'EUR');
        $aEncaisser[$dv] = ($aEncaisser[$dv] ?? 0) + (float) $fa['montant'];
    }
}
$badges = ['emise' => 'blue', 'payee' => 'green', 'annulee' => 'gray'];
?>
<h1>Factures</h1>
<div class="subtitle"><?= count($lignes) ?> facture<?= count($lignes) > 1 ? 's' : '' ?> — tous dossiers confondus.<?php if ($aEncaisser): ?> À encaisser dans cette liste :
  <?php foreach ($aEncaisser as $dv => $tot): ?><strong><?= View::e(Pilotage::fmt($tot, $dv)) ?></strong> <?php endforeach; ?><?php endif; ?></div>
<?php include __DIR__ . '/_filtres.php'; ?>
<?php if (empty($lignes)): ?>
  <div class="card"><div class="empty-state">Aucune facture pour ces critères.</div></div>
<?php else: ?>
<table class="responsive-cards liste-cartes">
  <thead><tr><th>Facture</th><th>Dossier</th><th>Type</th><th class="num">Montant</th><th>Émission</th><th>Échéance</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $fa): $retard = $fa['statut'] === 'emise' && $fa['date_echeance'] && $fa['date_echeance'] < date('Y-m-d'); ?>
    <tr>
      <td data-label="Facture"><a href="/index.php?r=dossiers/<?= (int) $fa['dossier_id'] ?>/commande"><?= View::e($fa['reference']) ?></a></td>
      <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $fa['dossier_id'] ?>"><?= View::e($fa['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($fa['dossier_objet']) ?></span></td>
      <td data-label="Type"><?= View::e(Facture::TYPES[$fa['type']] ?? $fa['type']) ?></td>
      <td data-label="Montant" class="num"><?= Pilotage::fmt((float) $fa['montant'], (string) $fa['devise']) ?></td>
      <td data-label="Émission"><?= $fa['date_emission'] ? date('d/m/Y', strtotime($fa['date_emission'])) : '—' ?></td>
      <td data-label="Échéance"><?= $fa['date_echeance'] ? date('d/m/Y', strtotime($fa['date_echeance'])) : '—' ?><?php if ($retard): ?> <span class="badge badge-red">En retard</span><?php endif; ?></td>
      <td data-label="Statut"><span class="badge badge-<?= $badges[$fa['statut']] ?? 'gray' ?>"><?= View::e(Facture::STATUTS[$fa['statut']] ?? $fa['statut']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
