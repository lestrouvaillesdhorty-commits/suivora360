<?php
use App\Core\Auth;
use App\Core\View;
use App\Models\Expedition;
/** Liste des expéditions. Variables : $disponible, $expeditions, $statut, $voitMarges. */
$fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
$onglets = ['en_cours' => 'En cours', 'livree' => 'Livrées', '' => 'Toutes'];
?>
<div class="page-header">
  <h1>Expéditions</h1>
  <?php if (Auth::canWrite() && $disponible): ?><a class="btn btn-primary" href="/index.php?r=expeditions/nouvelle">Nouvelle expédition</a><?php endif; ?>
</div>
<?php if (!$disponible): ?>
  <div class="card"><div class="empty-state">Le module n'est pas encore activé : lancez migrate_v58.php une fois.</div></div>
<?php else: ?>
<div class="tabs" style="margin-bottom:12px">
  <?php foreach ($onglets as $k => $l): ?>
    <a class="tab <?= ($statut === (string) $k || ($k === '' && $statut === 'toutes')) ? 'active' : '' ?>" href="/index.php?r=expeditions<?= $k === 'en_cours' ? '' : '&statut=' . ($k === '' ? 'toutes' : $k) ?>"><?= View::e($l) ?></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <?php if (!$expeditions): ?>
    <div class="empty-state">Aucune expédition. Créez-en une pour y rattacher les marchandises de vos clients.</div>
  <?php else: ?>
  <table class="table responsive-cards">
    <thead><tr><th>Référence</th><th>Trajet</th><th>Type</th><th>Départ</th><th>Remplissage</th><th>Statut</th><?php if ($voitMarges): ?><th>Résultat</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($expeditions as $e): $b = $e['bilan']; ?>
      <tr>
        <td data-label="Référence"><a href="/index.php?r=expeditions/<?= (int) $e['id'] ?>"><strong><?= View::e($e['reference']) ?></strong></a></td>
        <td data-label="Trajet"><?= View::e($e['origine']) ?> → <?= View::e($e['destination']) ?></td>
        <td data-label="Type"><?= View::e(Expedition::TYPES[$e['type']] ?? $e['type']) ?> · <?= View::e(Expedition::MODES[$e['mode']] ?? $e['mode']) ?></td>
        <td data-label="Départ"><?= $e['date_depart'] ? date('d/m/Y', strtotime($e['date_depart'])) : '—' ?></td>
        <td data-label="Remplissage"><?= $b['remplissage'] !== null ? (int) $b['remplissage'] . ' %' : '—' ?> · <?= (int) $b['nb'] ?> réservation(s)</td>
        <td data-label="Statut"><span class="badge badge-blue"><?= View::e(Expedition::STATUTS[$e['statut']] ?? $e['statut']) ?></span></td>
        <?php if ($voitMarges): ?><td data-label="Résultat"><span style="color:<?= $b['resultat'] < 0 ? '#b3261e' : 'inherit' ?>"><?= $fmt($b['resultat']) ?> <?= View::e($e['devise']) ?></span></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
