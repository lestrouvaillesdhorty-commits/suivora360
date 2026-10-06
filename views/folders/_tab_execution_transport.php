<?php
use App\Core\View;
use App\Models\Client;
use App\Models\Commande;

// [ajouté 06/10, étape 6 du découpage Dossiers] Onglet Exécution, déclinaison
// Transport / Logistique (maquette ExecutionTransport.dc.html). Aucune
// nouvelle table : tout est lu dans l'offre retenue (départ, mode, colis,
// poids, volume, coût transport), la demande/le client (destinataire,
// destination), la commande et ses étapes de suivi (dates prévues/réelles,
// tracking, transit). Les champs ne sont pas saisis ici : ils se
// modifient là où ils vivent déjà (offre, commande, demande).
$o = $offreRetenue ?? null;
$clientT = !empty($demande['client_id']) ? Client::find((int) $demande['client_id']) : null;
$stepsT = $commande ? Commande::steps((int) $commande['id']) : [];
$stepParCode = [];
foreach ($stepsT as $st) { $stepParCode[$st['libelle']] = $st; }
$nd = '<span style="color:#999">Non renseigné</span>';
$fmtD = fn($d) => $d ? date('d/m/Y', strtotime($d)) : null;
$prevuReel = function (?array $st) use ($fmtD) {
    if (!$st) return null;
    $p = $fmtD($st['date_prevue'] ?? null); $r = $fmtD($st['date_reelle'] ?? null);
    if (!$p && !$r) return null;
    return ($p ?: '—') . ' · ' . ($r ?: 'en attente');
};
$retard = $commande && $commande['statut'] !== 'terminee' && !empty($commande['date_transit_fin']) && $commande['date_transit_fin'] < date('Y-m-d');
$joursRetard = $retard ? (int) ((time() - strtotime($commande['date_transit_fin'])) / 86400) : 0;
$notesSteps = array_filter($stepsT, fn($s) => trim((string) ($s['notes'] ?? '')) !== '');
?>
<?php if ($retard): ?>
<div class="amber-alert"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Fin de transit prévue le <?= $fmtD($commande['date_transit_fin']) ?> — dépassée de <?= $joursRetard ?> jour<?= $joursRetard > 1 ? 's' : '' ?>.</div>
<?php endif; ?>

<div class="card">
  <h2>Expédition</h2>
  <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
    <div class="form-group"><label>Expéditeur</label><input type="text" value="<?= View::e($filiale['nom'] ?? '') ?>" placeholder="Non renseigné" disabled></div>
    <div class="form-group"><label>Destinataire</label><input type="text" value="<?= View::e($clientT['nom'] ?? ($demande['expediteur_entreprise'] ?? '')) ?>" placeholder="Non renseigné" disabled></div>
    <div class="form-group"><label>Contact destinataire</label><input type="text" value="<?= View::e(trim(($demande['expediteur_nom'] ?? '') . (!empty($demande['expediteur_telephone']) ? ' — ' . $demande['expediteur_telephone'] : ''))) ?>" placeholder="Non renseigné" disabled></div>
  </div>
  <div class="form-row" style="grid-template-columns:1fr 1fr 1fr">
    <div class="form-group"><label>Départ</label><input type="text" value="<?= View::e($o['lieu_depart'] ?? '') ?>" placeholder="Non renseigné" disabled></div>
    <div class="form-group"><label>Destination</label><input type="text" value="<?= View::e(trim(($demande['lieu_livraison'] ?? '') . (!empty($demande['destination_pays']) ? ', ' . $demande['destination_pays'] : ''), ', ')) ?>" placeholder="Non renseigné" disabled></div>
    <div class="form-group"><label>Mode de transport</label><input type="text" value="<?= View::e($o['mode_transport'] ?? '') ?>" placeholder="Non renseigné" disabled></div>
  </div>
  <div class="hint">Lecture seule : départ, mode et marchandise viennent de l'offre retenue, la destination et le contact de la demande. Modifiez-les à cet endroit.</div>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:20px 22px 0"><h2 style="margin:0">Marchandise</h2></div>
  <?php if ($o): ?>
  <div class="table-scroll">
  <table class="dtable">
    <thead><tr><th>Colis</th><th>Poids</th><th>Volume</th><th>Prestataire / transporteur</th></tr></thead>
    <tbody><tr>
      <td class="row-title"><?= !empty($o['nombre_colis']) ? (int) $o['nombre_colis'] . ' colis' : $nd ?></td>
      <td><?= !empty($o['poids_kg']) ? number_format((float) $o['poids_kg'], 0, ',', ' ') . ' kg' : $nd ?></td>
      <td><?= !empty($o['volume_m3']) ? number_format((float) $o['volume_m3'], 1, ',', ' ') . ' m³' : $nd ?></td>
      <td><?= View::e($o['fournisseur_nom']) ?></td>
    </tr></tbody>
  </table>
  </div>
  <?php else: ?>
  <div class="empty-state" style="margin:14px 22px 18px">Aucune offre retenue — la marchandise et le transporteur apparaîtront une fois l'offre retenue.</div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Dates et coûts</h2>
  <div class="kv-row"><span class="label">Expédition prévue / réelle</span><span class="value"><?= $prevuReel($stepParCode['expedition'] ?? null) ?? $nd ?></span></div>
  <div class="kv-row"><span class="label">Transit (début → fin prévue)</span><span class="value"><?= ($commande && ($commande['date_transit_debut'] || $commande['date_transit_fin'])) ? ($fmtD($commande['date_transit_debut']) ?: '—') . ' → ' . ($fmtD($commande['date_transit_fin']) ?: '—') : $nd ?></span></div>
  <div class="kv-row"><span class="label">Livraison prévue / réelle</span><span class="value"><?= $prevuReel($stepParCode['livraison'] ?? null) ?? $nd ?></span></div>
  <div class="kv-row"><span class="label">N° de tracking</span><span class="value"><?= !empty($commande['tracking_numero']) ? View::e($commande['tracking_numero']) : $nd ?></span></div>
  <div class="kv-row"><span class="label">Coût transport (offre retenue)</span><span class="value"><?= !empty($o['transport_montant']) ? number_format((float) $o['transport_montant'], 0, ',', ' ') . ' ' . View::e($o['devise'] ?? '') : $nd ?></span></div>
  <div class="kv-row"><span class="label">Dédouanement</span><span class="value"><?php
    $dd = $stepParCode['douane'] ?? null;
    echo $dd ? '<span class="badge ' . ($dd['statut'] === 'termine' ? 'badge-green' : ($dd['statut'] === 'en_cours' ? 'badge-blue' : 'badge-gray')) . '">' . View::e(Commande::libelleStatutEtape('douane', $dd['statut'])) . '</span>' : $nd;
  ?></span></div>
  <?php if ($commande): ?><div class="hint"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/commande">Mettre à jour dans le suivi de la commande</a></div><?php endif; ?>
</div>

<div class="card">
  <h2>Réception, incidents et réserves</h2>
  <?php $recu = $stepParCode['livraison'] ?? null; ?>
  <?php if ($recu && $recu['statut'] === 'termine'): ?>
    <div class="kv-row"><span class="label">Réception</span><span class="value"><span class="badge badge-green">Effectuée</span> <?= $fmtD($recu['date_reelle'] ?? null) ?></span></div>
  <?php else: ?>
    <div class="empty-state">Réception non effectuée.</div>
  <?php endif; ?>
  <?php foreach ($notesSteps as $ns): ?>
    <div class="kv-row"><span class="label">Note — <?= View::e(Commande::ETAPES_STEPS[$ns['libelle']] ?? $ns['libelle']) ?></span><span class="value"><?= View::e($ns['notes']) ?></span></div>
  <?php endforeach; ?>
  <div class="hint">Les incidents et réserves se consignent pour l'instant dans les notes des étapes de suivi de la commande (pas de registre dédié).</div>
</div>
