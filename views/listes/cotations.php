<?php use App\Core\View; use App\Models\Cotation; use App\Models\Pilotage;
$route = 'cotations';
$selects = [
  ['statut', 'Statut', Cotation::STATUTS, 'Cotations actuelles'],
  ['validite', 'Validité', ['expiree' => 'Devis expiré (sans réponse)'], 'Toutes'],
];
$placeholderRecherche = 'Référence, dossier, client...';
$badges = ['brouillon' => 'gray', 'envoyee' => 'blue', 'acceptee' => 'green', 'refusee' => 'red', 'remplacee' => 'gray'];
?>
<h1>Cotations</h1>
<div class="subtitle"><?= count($lignes) ?> cotation<?= count($lignes) > 1 ? 's' : '' ?> — tous dossiers confondus. « Envoyée » = en attente de réponse du client.</div>
<?php include __DIR__ . '/_filtres.php'; ?>
<?php if (empty($lignes)): ?>
  <div class="card"><div class="empty-state">Aucune cotation pour ces critères.</div></div>
<?php else: ?>
<table class="responsive-cards liste-cartes">
  <thead><tr><th>Cotation</th><th>Dossier</th><th>Client</th><th class="num">Prix de vente</th><?php if ($voirMarges): ?><th class="num">Marge</th><?php endif; ?><th>Validité du devis</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $c): $expiree = $c['statut'] === 'envoyee' && $c['validite_devis'] && $c['validite_devis'] < date('Y-m-d'); ?>
    <tr>
      <td data-label="Cotation"><a href="/index.php?r=cotations/<?= (int) $c['id'] ?>"><?= View::e($c['reference']) ?></a><?php if (!empty($c['version']) && (int) $c['version'] > 1): ?> <span style="font-size:12px;color:#888">v<?= (int) $c['version'] ?></span><?php endif; ?></td>
      <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $c['dossier_id'] ?>"><?= View::e($c['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($c['dossier_objet']) ?></span></td>
      <td data-label="Client"><?= View::e($c['client_nom']) ?></td>
      <td data-label="Prix de vente" class="num"><?= $c['montant_total'] !== null ? Pilotage::fmt((float) $c['montant_total'], (string) $c['devise']) : '—' ?></td>
      <?php if ($voirMarges): ?><td data-label="Marge" class="num"><?= $c['marge_pourcentage'] !== null ? number_format((float) $c['marge_pourcentage'], 1, ',', ' ') . ' %' : '—' ?></td><?php endif; ?>
      <td data-label="Validité du devis"><?= $c['validite_devis'] ? date('d/m/Y', strtotime($c['validite_devis'])) : '—' ?><?php if ($expiree): ?> <span class="badge badge-red">Expiré</span><?php endif; ?></td>
      <td data-label="Statut"><span class="badge badge-<?= $badges[$c['statut']] ?? 'gray' ?>"><?= View::e(Cotation::STATUTS[$c['statut']] ?? $c['statut']) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
