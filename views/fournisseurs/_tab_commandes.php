<?php
use App\Core\View;
use App\Models\Commande;

$fmt = fn($m, $dev) => number_format((float) $m, 2, ',', ' ') . ' ' . View::e((string) $dev);
?>
<?php if ($peutVoirBons): ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <h2 style="margin:0">Bons de commande adressés à ce fournisseur</h2>
    <?php if ($peutGererBon && $bonsPret): ?><a href="/index.php?r=fournisseurs/<?= (int) $fournisseur['id'] ?>/bons-commande/nouveau" class="btn btn-sm">Nouveau bon de commande</a><?php endif; ?>
  </div>
  <?php if (!$bonsPret): ?>
    <div class="empty-state">Migration V24 requise pour activer les bons de commande.</div>
  <?php elseif (empty($bonsCommande)): ?>
    <div class="empty-state">Aucun bon de commande. Créez-en un librement, ou depuis une offre retenue ci-dessous.</div>
  <?php else: ?>
  <table class="responsive-cards" style="margin-top:10px">
    <thead><tr><th>Bon</th><th>Dossier</th><th>Date</th><th>Livraison souhaitée</th><th class="num">Montant</th><th>Statut</th><?php if ($peutVoirBons && $bonsPret): ?><th>Bon de commande</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($bonsCommande as $b): ?>
      <tr>
        <td data-label="Bon"><a href="/index.php?r=bons-commande/<?= (int) $b['id'] ?>"><?= View::e($b['reference']) ?></a></td>
        <td data-label="Dossier"><?= !empty($b['dossier_id']) ? '<a href="/index.php?r=dossiers/' . (int) $b['dossier_id'] . '">' . View::e($b['dossier_reference']) . '</a>' : '—' ?></td>
        <td data-label="Date"><?= date('d/m/Y', strtotime($b['date_emission'])) ?></td>
        <td data-label="Livraison souhaitée"><?= $b['date_livraison_souhaitee'] ? date('d/m/Y', strtotime($b['date_livraison_souhaitee'])) : '—' ?></td>
        <td data-label="Montant" class="num" style="white-space:nowrap"><?= $fmt($b['montant_total'], $b['devise']) ?></td>
        <td data-label="Statut"><span class="badge <?= \App\Models\BonCommandeFournisseur::BADGES[$b['statut']] ?>"><?= View::e(\App\Models\BonCommandeFournisseur::STATUTS[$b['statut']]) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Commandes issues de ses offres retenues</h2>
  <?php if (empty($commandes)): ?>
    <div class="empty-state">Aucune offre retenue pour ce fournisseur : aucune commande liée pour le moment.</div>
  <?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Dossier</th><th>Commande</th><th>Offre retenue</th><th class="num">Montant</th><th>Livraison prévue</th><th>Livraison réelle</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($commandes as $c): ?>
      <tr>
        <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $c['dossier_id'] ?>"><?= View::e($c['dossier_reference']) ?></a><br><span style="font-size:12px;color:#888"><?= View::e(mb_strimwidth((string) $c['objet'], 0, 45, '…')) ?></span></td>
        <td data-label="Commande"><?php if (!empty($c['commande_id'])): ?><a href="/index.php?r=dossiers/<?= (int) $c['dossier_id'] ?>&onglet=execution"><?= View::e($c['commande_reference']) ?></a><?php else: ?><span style="color:#999">Pas encore de commande</span><?php endif; ?></td>
        <td data-label="Offre retenue"><a href="/index.php?r=dossiers/<?= (int) $c['dossier_id'] ?>&onglet=achats&sous=offres"><?= View::e($c['offre_reference']) ?></a></td>
        <td data-label="Montant" class="num" style="white-space:nowrap"><?= $fmt($c['montant_total'], $c['devise']) ?></td>
        <td data-label="Livraison prévue"><?= !empty($c['livraison_prevue']) ? date('d/m/Y', strtotime($c['livraison_prevue'])) : '—' ?></td>
        <td data-label="Livraison réelle"><?= !empty($c['livraison_reelle']) ? date('d/m/Y', strtotime($c['livraison_reelle'])) : '—' ?></td>
        <td data-label="Statut">
          <?php if (!empty($c['commande_id'])): ?>
            <span class="badge <?= $c['commande_statut'] === 'terminee' ? 'badge-green' : 'badge-blue' ?>"><?= $c['commande_statut'] === 'terminee' ? 'Terminée' : 'En cours' ?></span>
            <?php if (!empty($c['etape']) && $c['commande_statut'] !== 'terminee'): ?><br><span style="font-size:12px;color:#888"><?= View::e(Commande::ETAPES_STEPS[$c['etape']] ?? $c['etape']) ?></span><?php endif; ?>
          <?php else: ?><span class="badge badge-gray">Offre retenue</span><?php endif; ?>
        </td>
        <?php if ($peutVoirBons && $bonsPret): $bonOffre = \App\Models\BonCommandeFournisseur::actifPourOffre((int) $c['offre_id']); ?>
        <td data-label="Bon de commande">
          <?php if ($bonOffre): ?><a href="/index.php?r=bons-commande/<?= (int) $bonOffre['id'] ?>"><?= View::e($bonOffre['reference']) ?></a>
          <?php elseif ($peutGererBon): ?><a href="/index.php?r=fournisseurs/<?= (int) $fournisseur['id'] ?>/bons-commande/nouveau&offre_id=<?= (int) $c['offre_id'] ?>" class="btn btn-sm btn-secondary">Créer le bon</a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card" style="background:#fafafa">
  <h2>Réceptions, réserves et incidents</h2>
  <div class="empty-state" style="text-align:left">
    <strong>À connecter.</strong> Suivora360 suit aujourd’hui la commande par étapes sur le dossier (paiement, expédition, douane, livraison, solde). Les réceptions partielles, les réserves, les non-conformités et les incidents ne sont pas encore enregistrés séparément : aucun chiffre n’est affiché tant que ces données n’existent pas.
  </div>
</div>
