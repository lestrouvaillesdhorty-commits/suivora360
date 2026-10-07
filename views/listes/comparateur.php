<?php use App\Core\View;
$route = 'comparateur';
$selects = [['decision', 'Décision', ['a_trancher' => 'À trancher (aucune offre retenue)', 'decide' => 'Offre retenue'], 'Tous']];
$placeholderRecherche = 'Référence ou objet du dossier...';
?>
<h1>Comparateur</h1>
<div class="subtitle"><?= count($lignes) ?> dossier<?= count($lignes) > 1 ? 's' : '' ?> avec des offres à comparer. On compare toujours les offres d'un même dossier : choisissez le dossier pour ouvrir son comparateur.</div>
<?php include __DIR__ . '/_filtres.php'; ?>
<?php if (empty($lignes)): ?>
  <div class="card"><div class="empty-state">Aucun dossier avec des offres pour ces critères.</div></div>
<?php else: ?>
<table class="responsive-cards">
  <thead><tr><th>Dossier</th><th class="num">Offres</th><th>Décision</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $d): ?>
    <tr>
      <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $d['id'] ?>"><?= View::e($d['reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($d['objet']) ?></span></td>
      <td data-label="Offres" class="num"><?= (int) $d['nb_offres'] ?></td>
      <td data-label="Décision"><?php if ($d['retenue_fournisseur']): ?><span class="badge badge-green">Retenue : <?= View::e($d['retenue_fournisseur']) ?></span><?php else: ?><span class="badge badge-orange">À trancher (<?= (int) $d['nb_a_comparer'] ?> offre<?= (int) $d['nb_a_comparer'] > 1 ? 's' : '' ?>)</span><?php endif; ?></td>
      <td><a href="/index.php?r=dossiers/<?= (int) $d['id'] ?>/comparateur" class="btn btn-sm btn-secondary">Ouvrir le comparateur</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
