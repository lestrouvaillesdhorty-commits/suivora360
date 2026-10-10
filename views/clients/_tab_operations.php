<?php
use App\Core\View;
use App\Models\Dossier;

$statutsDemande = [
    'a_qualifier' => ['À qualifier', 'badge-yellow'], 'en_attente_info' => ["En attente d'infos", 'badge-yellow'],
    'qualifiee' => ['Qualifiée', 'badge-blue'], 'rattachee' => ['Rattachée', 'badge-blue'],
    'transformee' => ['Transformée en dossier', 'badge-green'], 'rejetee' => ['Rejetée', 'badge-red'], 'archivee' => ['Archivée', 'badge-gray'],
];
$statutsDossier = ['actif' => ['Actif', 'badge-green'], 'cloture' => ['Clôturé', 'badge-gray'], 'annule' => ['Annulé', 'badge-red'], 'termine' => ['Terminé', 'badge-gray']];
$aujourdhui = date('Y-m-d');
?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px">
  <div style="color:#666;font-size:14px"><?= count($operations) ?> demande<?= count($operations) > 1 ? 's' : '' ?> rattachée<?= count($operations) > 1 ? 's' : '' ?> à ce client</div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-sm btn-secondary" href="/index.php?r=dossiers&client_id=<?= (int) $client['id'] ?>">Voir dans la liste des dossiers</a>
    <?php if ($peutEcrire && (int) $client['is_active'] === 1): ?><a class="btn btn-sm" href="/index.php?r=demandes/nouvelle&client_id=<?= (int) $client['id'] ?>">Nouvelle demande</a><?php endif; ?>
  </div>
</div>
<?php if (empty($operations)): ?>
  <div class="card"><div class="empty-state">Aucune demande ni aucun dossier pour ce client.<?php if ($peutEcrire && (int) $client['is_active'] === 1): ?> <a href="/index.php?r=demandes/nouvelle&client_id=<?= (int) $client['id'] ?>">Créer une demande</a>.<?php endif; ?></div></div>
<?php else: ?>
<table class="responsive-cards">
  <thead><tr><th>Demande</th><th>Dossier</th><th>Activité</th><th>Étape</th><th>Statut</th><th>Responsable</th><th>Échéance</th></tr></thead>
  <tbody>
  <?php foreach ($operations as $o):
      $sd = $statutsDemande[$o['demande_statut']] ?? [ucfirst((string) $o['demande_statut']), 'badge-gray'];
      $hasDossier = !empty($o['dossier_id']);
      $ech = $hasDossier ? ($o['dossier_echeance'] ?? '') : ($o['demande_echeance'] ?? '');
      $enCours = $hasDossier ? ($o['dossier_statut'] === 'actif') : in_array($o['demande_statut'], ['a_qualifier', 'en_attente_info', 'qualifiee'], true);
  ?>
    <tr>
      <td data-label="Demande"><a href="/index.php?r=demandes/<?= (int) $o['demande_id'] ?>"><?= View::e($o['demande_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e($o['objet']) ?></span></td>
      <td data-label="Dossier"><?= $hasDossier ? '<a href="/index.php?r=dossiers/' . (int) $o['dossier_id'] . '">' . View::e($o['dossier_reference']) . '</a>' : '—' ?></td>
      <td data-label="Activité"><?= View::e($o['activite'] ?? '') ?: '—' ?></td>
      <td data-label="Étape"><?= $hasDossier ? View::e(Dossier::ETAPES_LABELS[$o['etape']] ?? $o['etape']) : '—' ?></td>
      <td data-label="Statut">
        <?php if ($hasDossier): $sdo = $statutsDossier[$o['dossier_statut']] ?? [ucfirst((string) $o['dossier_statut']), 'badge-gray']; ?>
          <span class="badge <?= $sdo[1] ?>"><?= View::e($sdo[0]) ?></span>
        <?php else: ?><span class="badge <?= $sd[1] ?>"><?= View::e($sd[0]) ?></span><?php endif; ?>
      </td>
      <td data-label="Responsable"><?= View::e($o['responsable_nom'] ?? '') ?: '—' ?></td>
      <td data-label="Échéance" style="<?= ($ech && $enCours && $ech < $aujourdhui) ? 'color:#991b1b;font-weight:600' : '' ?>"><?= $ech ? date('d/m/Y', strtotime($ech)) : '—' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
