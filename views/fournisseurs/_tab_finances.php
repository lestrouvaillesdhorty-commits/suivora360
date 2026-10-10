<?php
use App\Core\View;

// Montants des offres retenues, PAR DEVISE (jamais additionnés entre devises).
$totaux = [];
foreach ($commandes as $c) {
    $dev = ($c['devise'] ?? '') !== '' ? $c['devise'] : '—';
    $totaux[$dev] = ($totaux[$dev] ?? 0) + (float) $c['montant_total'];
}
ksort($totaux);
?>
<div class="card">
  <h2>Montants engagés (offres retenues)</h2>
  <?php if (empty($totaux)): ?>
    <div class="empty-state">Aucune offre retenue pour ce fournisseur.</div>
  <?php else: foreach ($totaux as $dev => $m): ?>
    <div class="info-row"><span class="label">Offres retenues en <?= View::e($dev) ?> (<?= count(array_filter($commandes, fn($c) => (($c['devise'] ?? '') !== '' ? $c['devise'] : '—') === $dev)) ?>)</span><strong style="white-space:nowrap"><?= number_format($m, 2, ',', ' ') ?> <?= View::e($dev) ?></strong></div>
  <?php endforeach; endif; ?>
  <div style="font-size:12px;color:#888;margin-top:8px">Toutes périodes. Base : montant des offres au statut « retenue » (une version remplacée n’est jamais comptée). Les devises ne sont jamais additionnées : aucune conversion n’est enregistrée.</div>
</div>

<div class="card" style="background:#fafafa">
  <h2>Factures, avoirs, règlements et solde</h2>
  <div class="empty-state" style="text-align:left">
    <strong>À connecter.</strong> Suivora360 n’enregistre pas encore les factures fournisseur, les avoirs ni les paiements affectés : ni « reste à payer » ni échéances dépassées ne peuvent être calculés honnêtement. Une commande n’est pas une facture. Aucun chiffre n’est affiché tant que ces données n’existent pas.
  </div>
  <div style="font-size:12px;color:#888;margin-top:8px">Les coordonnées bancaires du fournisseur ne sont pas conservées dans cette version.</div>
</div>
