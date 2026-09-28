<?php
use App\Core\View;
use App\Models\Pilotage;
use App\Models\Utilisateur;

$titre = 'Détail des opérations';
if ($axe === 'activite') {
    $titre = 'Opérations — activité « ' . $valeur . ' »';
} elseif ($axe === 'responsable') {
    $titre = 'Opérations — ' . Utilisateur::nameOf((int) $valeur);
}
$retourUrl = '/index.php?r=pilotage' . ($retour !== '' ? '&' . $retour : '');
?>
<div class="pilotage-header">
  <div>
    <h1><?= View::e($titre) ?></h1>
    <div class="subtitle" style="margin-bottom:0">Commandes confirmées composant ce résultat, sur <?= View::e($filters['periode_label']) ?></div>
  </div>
  <a href="<?= View::e($retourUrl) ?>" class="btn btn-secondary">← Retour à Pilotage</a>
</div>

<div class="card">
  <?php if (empty($operations)): ?>
    <div class="empty-state">Aucune opération sur ce périmètre.</div>
  <?php else: ?>
    <table class="pilotage-table responsive-cards">
      <thead>
        <tr><th>Référence</th><th>Client</th><th>Activité</th><th>Responsable</th><th>Date</th><th>Statut</th><th class="num">Montant HT</th><th class="num">Marge</th></tr>
      </thead>
      <tbody>
        <?php foreach ($operations as $o): ?>
        <tr>
          <td data-label="Référence">
            <a href="/index.php?r=dossiers/<?= $o['dossier_id'] ?>"><?= View::e($o['dossier_reference']) ?></a><br>
            <span style="color:#888;font-size:12px">Commande <?= View::e($o['commande_reference']) ?></span>
          </td>
          <td data-label="Client"><?= View::e($o['client_nom']) ?></td>
          <td data-label="Activité"><?= View::e($o['activite']) ?></td>
          <td data-label="Responsable"><?= $o['responsable_id'] ? View::e(Utilisateur::nameOf((int) $o['responsable_id'])) : '—' ?></td>
          <td data-label="Date"><?= date('d/m/Y', strtotime($o['date'])) ?></td>
          <td data-label="Statut"><span class="badge badge-blue"><?= View::e(ucfirst(str_replace('_', ' ', $o['statut']))) ?></span></td>
          <td data-label="Montant HT" class="num"><?= Pilotage::fmt((float) $o['montant_total'], $o['devise']) ?></td>
          <td data-label="Marge" class="num"><?= $o['marge_montant'] !== null ? Pilotage::fmt((float) $o['marge_montant'], $o['devise']) : 'Non renseigné' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
