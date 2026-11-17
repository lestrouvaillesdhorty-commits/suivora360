<?php use App\Core\View;
use App\Models\Expedition;
/** Manifeste d'une expédition : colis, palettes, volumes et poids par client. Aucun prix, aucune marge. */
$info = $info ?? [];
$col = \App\Models\EntrepriseInfo::couleurEffective($info);
$nomEmetteur = trim((string) ($info['raison_sociale'] ?? '')) ?: (string) ($organisation['nom'] ?? '');
$n = fn($v, $d = 0) => $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float) $v, $d, ',', ' '), '0'), ',');
$tot = ['colis' => 0, 'palettes' => 0, 'volume' => 0.0, 'poids' => 0.0];
$estimes = 0;
foreach ($reservations as $r) {
    $tot['colis'] += (int) $r['colis'];
    $tot['palettes'] += (int) $r['palettes'];
    $tot['volume'] += (float) $r['volume'];
    $tot['poids'] += (float) $r['poids'];
    if ($r['poids'] !== null && empty($r['poids_confirme'])) { $estimes++; }
}
$date = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Manifeste <?= View::e($exp['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --c: <?= View::e($col) ?>; }
  body { font-family: -apple-system, Arial, sans-serif; color: #1f2430; margin: 0 auto; padding: 30px; max-width: 900px; font-size: 13px; line-height: 1.5; }
  h1 { font-size: 22px; margin: 0; color: var(--c); }
  .entete { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid var(--c); padding-bottom: 12px; margin-bottom: 18px; }
  .bloc { display: flex; gap: 20px; margin-bottom: 18px; flex-wrap: wrap; }
  .bloc > div { flex: 1; min-width: 240px; border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; }
  .bloc h2 { font-size: 11px; margin: 0 0 6px; color: #666; text-transform: uppercase; letter-spacing: .04em; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { background: #f7f8fa; font-size: 11px; }
  .num { text-align: right; white-space: nowrap; }
  .total td { font-weight: 700; border-top: 2px solid #1f2430; border-bottom: 0; }
  .print-btn { margin-bottom: 18px; padding: 8px 14px; cursor: pointer; }
  .pied { margin-top: 24px; font-size: 11px; color: #777; }
  .petit { font-size: 11px; color: #777; }
  @media print { .print-btn { display: none; } body { padding: 0; } tr { page-break-inside: avoid; } }
  @media (max-width: 600px) { .entete { flex-direction: column; } body { padding: 16px; } .tableau { overflow-x: auto; } }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
<div class="entete">
  <div>
    <h1>MANIFESTE</h1>
    <div><strong><?= View::e($exp['reference']) ?></strong> · édité le <?= date('d/m/Y') ?></div>
    <div><?= View::e($exp['origine']) ?> → <?= View::e($exp['destination']) ?> · <?= View::e(Expedition::MODES[$exp['mode']] ?? '') ?></div>
  </div>
  <div style="text-align:right">
    <?php if (!empty($logoUri)): ?><img src="<?= $logoUri ?>" alt="" style="max-height:54px;max-width:200px;display:block;margin:0 0 6px auto"><?php endif; ?>
    <strong><?= View::e($nomEmetteur) ?></strong><br>
    <?php if (!empty($info['adresse'])): ?><?= nl2br(View::e((string) $info['adresse'])) ?><br><?php endif; ?>
    <?= View::e(implode(' · ', array_filter([!empty($info['telephone']) ? 'Tél. ' . $info['telephone'] : '', $info['email'] ?? '']))) ?>
  </div>
</div>
<div class="bloc">
  <div><h2>Transport</h2>
    <?php if ($exp['partenaire']): ?>Transitaire : <strong><?= View::e($exp['partenaire']) ?></strong><br><?php endif; ?>
    Type : <?= View::e(Expedition::TYPES[$exp['type']] ?? '') ?><br>
    <?php if ($exp['numero_suivi']): ?>N° de suivi / conteneur : <strong><?= View::e($exp['numero_suivi']) ?></strong><?php endif; ?></div>
  <div><h2>Dates</h2>
    Clôture des réservations : <?= $date($exp['cloture_reservations']) ?><br>
    Départ : <strong><?= $date($exp['date_depart']) ?></strong><br>
    Arrivée prévue : <?= $date($exp['date_arrivee']) ?></div>
</div>
<div class="tableau">
<table>
  <thead><tr><th>Client</th><th>Marchandise</th><th>Description</th><th class="num">Colis</th><th class="num">Palettes</th><th class="num">Volume (m³)</th><th class="num">Poids (kg)</th></tr></thead>
  <tbody>
  <?php foreach ($reservations as $r): ?>
    <tr>
      <td><?= View::e($r['client_nom'] ?: ($r['client_nom_lie'] ?? ($r['dossier_reference'] ?? ''))) ?></td>
      <td><?= View::e(($r['type_libelle'] ?? '') !== '' ? $r['type_libelle'] : (Expedition::MARCHANDISES[$r['type_marchandise']] ?? '')) ?></td>
      <td><?= View::e($r['description']) ?></td>
      <td class="num"><?= $n($r['colis']) ?></td>
      <td class="num"><?= $n($r['palettes']) ?></td>
      <td class="num"><?= $n($r['volume'], 2) ?></td>
      <td class="num"><?= $n($r['poids'], 1) ?><?= $r['poids'] !== null && empty($r['poids_confirme']) ? ' *' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$reservations): ?><tr><td colspan="7" class="petit">Aucune réservation pour le moment.</td></tr><?php endif; ?>
  <tr class="total"><td colspan="3">Total (<?= count($reservations) ?> client<?= count($reservations) > 1 ? 's' : '' ?>)</td><td class="num"><?= $n($tot['colis']) ?></td><td class="num"><?= $n($tot['palettes']) ?></td><td class="num"><?= $n($tot['volume'], 2) ?></td><td class="num"><?= $n($tot['poids'], 1) ?></td></tr>
  </tbody>
</table>
</div>
<?php if ($estimes): ?><div class="petit">* Poids estimé, à confirmer par le transitaire.</div><?php endif; ?>
<div class="pied">Document établi par <?= View::e($nomEmetteur) ?>. Il ne comporte aucun prix.</div>
</body>
</html>
