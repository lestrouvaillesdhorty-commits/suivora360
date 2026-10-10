<?php use App\Core\View; use App\Models\Commande; use App\Models\Pilotage;
$route = 'commandes';
$selects = [
  ['statut', 'Statut', ['en_cours' => 'En cours', 'en_retard' => 'Relance dépassée', 'terminee' => 'Terminée'], 'Tous'],
  ['etape', 'Étape', Commande::ETAPES_STEPS, 'Toutes'],
];
$placeholderRecherche = 'Référence, dossier, client...';
?>
<h1>Commandes</h1>
<div class="subtitle"><?= count($lignes) ?> commande<?= count($lignes) > 1 ? 's' : '' ?> — tous dossiers confondus. Le suivi des étapes se fait sur la fiche de la commande.</div>
<?php include __DIR__ . '/_filtres.php'; ?>
<?php if (empty($lignes)): ?>
  <div class="card"><div class="empty-state">Aucune commande pour ces critères.</div></div>
<?php else: ?>
<table class="responsive-cards liste-cartes">
  <thead><tr><th>Commande</th><th>Dossier</th><th>Client</th><th class="num">Montant</th><th>Étape</th><th>Prochaine action</th><th>Relance</th><th>Statut</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $m): $retard = Commande::estEnRetard($m); ?>
    <tr>
      <td data-label="Commande"><a href="/index.php?r=dossiers/<?= (int) $m['dossier_id'] ?>/commande"><?= View::e($m['reference']) ?></a></td>
      <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $m['dossier_id'] ?>"><?= View::e($m['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($m['dossier_objet']) ?></span></td>
      <td data-label="Client"><?= View::e($m['client_nom']) ?></td>
      <td data-label="Montant" class="num"><?= $m['montant_total'] !== null ? Pilotage::fmt((float) $m['montant_total'], (string) $m['devise']) : '—' ?></td>
      <td data-label="Étape"><span class="badge badge-blue"><?= View::e(Commande::ETAPES_STEPS[$m['etape']] ?? $m['etape']) ?></span></td>
      <td data-label="Prochaine action"><?= View::e($m['prochaine_action'] ?: '—') ?></td>
      <td data-label="Relance"><?= $m['date_relance'] ? date('d/m/Y', strtotime($m['date_relance'])) : '—' ?><?php if ($retard): ?> <span class="badge badge-red">En retard</span><?php endif; ?></td>
      <td data-label="Statut"><span class="badge badge-<?= $m['statut'] === 'terminee' ? 'green' : 'blue' ?>"><?= $m['statut'] === 'terminee' ? 'Terminée' : 'En cours' ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
