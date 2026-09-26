<?php use App\Core\View; use App\Models\Dossier; use App\Models\Utilisateur; ?>
<h1>Dossiers</h1>
<div class="subtitle"><?= count($dossiers) ?> dossier<?= count($dossiers) > 1 ? 's' : '' ?></div>

<?php if (empty($dossiers)): ?>
  <div class="card"><div class="empty-state">Aucun dossier pour le moment.</div></div>
<?php else: ?>
<table>
  <thead>
    <tr><th>Référence</th><th>Objet</th><th>Filiale</th><th>Étape</th><th>Responsable</th><th>Priorité</th><th>Échéance</th><th>Statut</th></tr>
  </thead>
  <tbody>
    <?php foreach ($dossiers as $d): ?>
    <tr onclick="window.location='/index.php?r=dossiers/<?= $d['id'] ?>'" style="cursor:pointer">
      <td><a href="/index.php?r=dossiers/<?= $d['id'] ?>"><?= View::e($d['reference']) ?></a></td>
      <td><?= View::e($d['objet']) ?></td>
      <td><?= View::e($d['filiale_nom']) ?></td>
      <td><span class="badge badge-blue"><?= Dossier::ETAPES_LABELS[$d['etape']] ?? $d['etape'] ?></span></td>
      <td><?= View::e(Utilisateur::nameOf($d['responsable_id'])) ?></td>
      <td><?= $d['priorite'] === 'haute' ? '<span class="badge badge-red">Haute</span>' : '<span class="badge badge-gray">Normale</span>' ?></td>
      <td><?= $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '—' ?></td>
      <td>
        <?php $sc = ['actif' => 'green', 'en_retard' => 'red', 'cloture' => 'gray'][$d['statut']] ?? 'gray'; ?>
        <span class="badge badge-<?= $sc ?>"><?= ucfirst($d['statut']) ?></span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
