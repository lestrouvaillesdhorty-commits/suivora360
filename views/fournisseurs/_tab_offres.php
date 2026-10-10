<?php
use App\Core\View;
use App\Models\ConsultationFournisseur;
use App\Models\Offre;

$fmt = fn($m, $dev) => number_format((float) $m, 2, ',', ' ') . ' ' . View::e((string) $dev);
$badgeConsult = ['envoyee' => 'badge-blue', 'relance' => 'badge-orange', 'reponse_recue' => 'badge-green', 'sans_reponse' => 'badge-red'];
$badgeOffre = ['recue' => 'badge-blue', 'retenue' => 'badge-green', 'rejetee' => 'badge-gray', 'remplacee' => 'badge-gray'];
$aujourdhui = date('Y-m-d');
?>
<div class="card">
  <h2>Consultations adressées au fournisseur</h2>
  <?php if (empty($consultations)): ?>
    <div class="empty-state">Aucune consultation envoyée à ce fournisseur pour le moment.</div>
  <?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Référence</th><th>Dossier</th><th>Envoyée le</th><th>Réponse attendue avant</th><th>Responsable</th><th>État</th><th class="num">Offres</th></tr></thead>
    <tbody>
    <?php foreach ($consultations as $c):
        $retard = in_array($c['statut'], ['envoyee', 'relance'], true) && !empty($c['echeance_reponse']) && $c['echeance_reponse'] < $aujourdhui; ?>
      <tr onclick="window.location='/index.php?r=consultations/<?= (int) $c['id'] ?>'" style="cursor:pointer">
        <td data-label="Référence"><strong><?= View::e($c['reference']) ?></strong></td>
        <td data-label="Dossier" onclick="event.stopPropagation()"><a href="/index.php?r=dossiers/<?= (int) $c['dossier_id'] ?>"><?= View::e($c['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e(mb_strimwidth((string) $c['dossier_objet'], 0, 45, '…')) ?></span></td>
        <td data-label="Envoyée le"><?= !empty($c['date_envoi']) ? date('d/m/Y', strtotime($c['date_envoi'])) : '—' ?></td>
        <td data-label="Réponse attendue avant" style="<?= $retard ? 'color:#991b1b;font-weight:600' : '' ?>"><?= !empty($c['echeance_reponse']) ? date('d/m/Y', strtotime($c['echeance_reponse'])) . ($retard ? ' (dépassée)' : '') : '—' ?></td>
        <td data-label="Responsable"><?= View::e($c['responsable_nom'] ?? '') ?: '—' ?></td>
        <td data-label="État"><span class="badge <?= $badgeConsult[$c['statut']] ?? 'badge-gray' ?>"><?= View::e(ConsultationFournisseur::STATUTS[$c['statut']] ?? $c['statut']) ?></span></td>
        <td data-label="Offres" class="num"><?= (int) $c['nb_offres'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Offres reçues</h2>
  <?php if (empty($offres)): ?>
    <div class="empty-state">Aucune offre reçue de ce fournisseur pour le moment.</div>
  <?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Référence</th><th>Consultation / dossier</th><th>Version</th><th>Reçue le</th><th>Valable jusqu’au</th><th class="num">Montant</th><th>Délai proposé</th><th>Décision</th></tr></thead>
    <tbody>
    <?php foreach ($offres as $o): $remplacee = $o['statut'] === 'remplacee'; ?>
      <tr onclick="window.location='/index.php?r=dossiers/<?= (int) $o['dossier_id'] ?>&onglet=achats&sous=offres'" style="cursor:pointer;<?= $remplacee ? 'opacity:.6' : '' ?>">
        <td data-label="Référence"><strong><?= View::e($o['reference']) ?></strong></td>
        <td data-label="Consultation / dossier" onclick="event.stopPropagation()"><a href="/index.php?r=consultations/<?= (int) $o['consultation_id'] ?>"><?= View::e($o['consultation_reference']) ?></a> · <a href="/index.php?r=dossiers/<?= (int) $o['dossier_id'] ?>"><?= View::e($o['dossier_reference']) ?></a></td>
        <td data-label="Version">v<?= (int) $o['version'] ?><?= $remplacee ? '<br><span style="font-size:11px;color:#888">version antérieure</span>' : '' ?></td>
        <td data-label="Reçue le"><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
        <td data-label="Valable jusqu’au"><?= !empty($o['validite_offre']) ? date('d/m/Y', strtotime($o['validite_offre'])) : '—' ?></td>
        <td data-label="Montant" class="num" style="white-space:nowrap"><?= $fmt($o['montant_total'], $o['devise']) ?></td>
        <td data-label="Délai proposé"><?= View::e($o['delai_livraison'] ?? '') ?: '—' ?></td>
        <td data-label="Décision"><span class="badge <?= $badgeOffre[$o['statut']] ?? 'badge-gray' ?>"><?= View::e(Offre::STATUTS[$o['statut']] ?? $o['statut']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <div style="font-size:12px;color:#888;margin-top:10px">Une consultation est envoyée au fournisseur ; une offre est sa réponse ; une offre « retenue » est celle choisie côté achats. Retenir une offre ne vaut <strong>pas</strong> accord du client : le parcours reste estimation, cotation, accord client, puis achats. Une version remplacée garde son historique et n’est jamais comptée comme une nouvelle offre. Les montants de devises différentes ne sont jamais additionnés.</div>
</div>
