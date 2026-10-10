<?php use App\Core\View; use App\Models\Offre; use App\Models\Pilotage;
$route = 'offres';
$selects = [
  ['statut', 'Statut', Offre::STATUTS, 'Offres actuelles'],
  ['validite', 'Validité', ['expiree' => 'Expirée (non tranchée)'], 'Toutes'],
];
$placeholderRecherche = 'Référence, dossier, fournisseur...';
$badges = ['recue' => 'blue', 'retenue' => 'green', 'rejetee' => 'gray', 'remplacee' => 'gray'];
?>
<h1>Offres</h1>
<div class="subtitle"><?= count($lignes) ?> offre<?= count($lignes) > 1 ? 's' : '' ?> — tous dossiers confondus. Pour répondre ou comparer, ouvrez le dossier.</div>
<?php include __DIR__ . '/_filtres.php'; ?>
<?php if (empty($lignes)): ?>
  <div class="card"><div class="empty-state">Aucune offre pour ces critères.</div></div>
<?php else: ?>
<table class="responsive-cards liste-cartes">
  <thead><tr><th>Offre</th><th>Dossier</th><th>Fournisseur</th><th class="num">Montant</th><th>Incoterm</th><th>Délai</th><th>Validité</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $o): $expiree = $o['statut'] === 'recue' && $o['validite_offre'] && $o['validite_offre'] < date('Y-m-d'); ?>
    <tr>
      <td data-label="Offre"><a href="/index.php?r=consultations/<?= (int) $o['consultation_id'] ?>"><?= View::e($o['reference']) ?></a><?php if (!empty($o['version']) && (int) $o['version'] > 1): ?> <span style="font-size:12px;color:#888">v<?= (int) $o['version'] ?></span><?php endif; ?></td>
      <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $o['dossier_id'] ?>"><?= View::e($o['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($o['dossier_objet']) ?></span></td>
      <td data-label="Fournisseur"><?= View::e($o['fournisseur_nom']) ?></td>
      <td data-label="Montant" class="num"><?= $o['montant_total'] !== null ? Pilotage::fmt((float) $o['montant_total'], (string) $o['devise']) : '—' ?></td>
      <td data-label="Incoterm"><?= View::e($o['incoterm_negocie'] ?: '—') ?></td>
      <td data-label="Délai"><?= View::e($o['delai_livraison'] ?: '—') ?></td>
      <td data-label="Validité"><?= $o['validite_offre'] ? date('d/m/Y', strtotime($o['validite_offre'])) : '—' ?><?php if ($expiree): ?> <span class="badge badge-red">Expirée</span><?php endif; ?></td>
      <td data-label="Statut"><span class="badge badge-<?= $badges[$o['statut']] ?? 'gray' ?>"><?= View::e(Offre::STATUTS[$o['statut']] ?? $o['statut']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
