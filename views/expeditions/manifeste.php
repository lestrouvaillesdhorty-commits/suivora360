<?php use App\Core\View;
use App\Models\Expedition;
/** Manifeste d'une expédition : colis, palettes, volumes et poids par client. Aucun prix, aucune marge. */
$info = $info ?? [];
$col = \App\Models\EntrepriseInfo::couleurEffective($info);
$mep = \App\Models\EntrepriseInfo::miseEnPage($info);
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
<?php include __DIR__ . '/../documents/_style.php'; ?>
<style>.num{text-align:right;white-space:nowrap}.total td{font-weight:700;border-top:2px solid var(--c);border-bottom:0}.petit{font-size:11px;color:#777}.corps table th{text-align:left}</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Imprimer / enregistrer en PDF</button></div>
<div class="page">
<?php $titre = 'MANIFESTE ' . $exp['reference']; $droiteLignes = '<br>Édité le ' . date('d/m/Y') . '<br>' . View::e($exp['origine']) . ' → ' . View::e($exp['destination']) . ' · ' . View::e(Expedition::MODES[$exp['mode']] ?? ''); include __DIR__ . '/../documents/_entete.php'; ?>
<div class="corps">
<div class="bloc">
  <div><h3>Transport</h3>
    <?php if ($exp['partenaire']): ?>Transitaire : <strong><?= View::e($exp['partenaire']) ?></strong><br><?php endif; ?>
    Type : <?= View::e(Expedition::TYPES[$exp['type']] ?? '') ?><br>
    <?php if ($exp['numero_suivi']): ?>N° de suivi / conteneur : <strong><?= View::e($exp['numero_suivi']) ?></strong><?php endif; ?></div>
  <div><h3>Dates</h3>
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
<div class="petit" style="margin-top:14px">Document établi par <?= View::e($nomEmetteur) ?>. Il ne comporte aucun prix.</div>
</div>
<?php $piedDroite = 'Manifeste ' . $exp['reference']; $juridique = \App\Models\EntrepriseInfo::ligneJuridique($info); include __DIR__ . '/../documents/_pied.php'; ?>
</div>
</body>
</html>
