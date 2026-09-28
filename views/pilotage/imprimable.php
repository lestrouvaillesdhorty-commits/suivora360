<?php
use App\Core\View;
use App\Models\Pilotage;

$titres = ['general' => 'Vue générale', 'activites' => 'Activités', 'collaborateurs' => 'Collaborateurs'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Pilotage — <?= View::e($titres[$onglet] ?? $onglet) ?> — Suivora360</title>
<style>
  body { font-family: -apple-system, Arial, sans-serif; color: #1f2430; padding: 30px; }
  h1 { font-size: 20px; margin: 0 0 4px; color: #5036F5; }
  .meta { font-size: 12px; color: #666; margin-bottom: 18px; }
  .meta span { margin-right: 18px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th, td { padding: 6px 10px; text-align: left; font-size: 12px; border-bottom: 1px solid #ddd; }
  th { background: #f7f8fa; text-transform: uppercase; font-size: 10px; }
  td.num, th.num { text-align: right; }
  .total-row td { font-weight: 700; border-top: 2px solid #333; }
  .print-btn { margin-bottom: 20px; }
  @media print { .print-btn { display: none; } }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
<h1>Pilotage — <?= View::e($titres[$onglet] ?? $onglet) ?></h1>
<div class="meta">
  <span>Période : <?= View::e($filters['periode_label']) ?></span>
  <span>Base : <?= Pilotage::BASES[$filters['base']] ?></span>
  <span>Devise : <?= View::e($filters['devise']) ?></span>
  <span>Activité : <?= View::e($filters['activite'] ?: 'Toutes') ?></span>
  <span>Exporté le <?= date('d/m/Y H:i') ?></span>
</div>

<?php if ($onglet === 'general'): $t = $vueGenerale['resultats_activite']; ?>
  <table>
    <thead><tr><th>Activité</th><th class="num">Commandes</th><th class="num">Montant HT</th><th class="num">Marge</th><th class="num">Marge/Ventes HT</th></tr></thead>
    <tbody>
      <?php foreach ($t['lignes'] as $r): ?>
      <tr><td><?= View::e($r['activite']) ?></td><td class="num"><?= $r['commandes'] ?></td><td class="num"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmt($r['marge'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmtPct($r['marge_pct']) ?></td></tr>
      <?php endforeach; ?>
      <tr class="total-row"><td>Total</td><td class="num"><?= $t['total']['commandes'] ?></td><td class="num"><?= Pilotage::fmt($t['total']['ventes_ht'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmt($t['total']['marge'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmtPct($t['total']['marge_pct']) ?></td></tr>
    </tbody>
  </table>

<?php elseif ($onglet === 'activites'): $t = $activitesData['tableau']; ?>
  <table>
    <thead><tr><th>Activité</th><th class="num">Commandes</th><th class="num">Ventes HT</th><th class="num">Marge</th><th class="num">Marge/Ventes HT</th><th class="num">Part</th></tr></thead>
    <tbody>
      <?php foreach ($t['lignes'] as $r): ?>
      <tr><td><?= View::e($r['activite']) ?></td><td class="num"><?= $r['commandes'] ?></td><td class="num"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmt($r['marge'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmtPct($r['marge_pct']) ?></td><td class="num"><?= $r['part_ventes'] ?>%</td></tr>
      <?php endforeach; ?>
      <tr class="total-row"><td>Total</td><td class="num"><?= $t['total']['commandes'] ?></td><td class="num"><?= Pilotage::fmt($t['total']['ventes_ht'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmt($t['total']['marge'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmtPct($t['total']['marge_pct']) ?></td><td class="num">100%</td></tr>
    </tbody>
  </table>

<?php else: ?>
  <p style="font-size:12px;color:#666">Coûts individuels et contribution nette réservés à la consultation à l'écran (rôles autorisés) — non inclus dans cet export imprimé au-delà des ventes/marges déjà visibles ci-dessous.</p>
  <table>
    <thead><tr><th>Collaborateur</th><th class="num">Dossiers</th><th class="num">Ventes HT</th><th class="num">Marge attribuée</th><th class="num">Transformation</th></tr></thead>
    <tbody>
      <?php foreach ($collaborateursData['lignes'] as $r): ?>
      <tr><td><?= View::e($r['nom']) ?></td><td class="num"><?= $r['dossiers'] ?></td><td class="num"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmt($r['marge_attribuee'], $filters['devise']) ?></td><td class="num"><?= Pilotage::fmtPct($r['transformation']['taux'] ?? null) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<script>window.onload = function(){ setTimeout(function(){ window.print(); }, 300); };</script>
</body>
</html>
