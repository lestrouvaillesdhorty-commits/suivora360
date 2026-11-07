<?php use App\Core\View;
$info = $info ?? [];
$col = \App\Models\EntrepriseInfo::couleurEffective($info);
$mep = \App\Models\EntrepriseInfo::miseEnPage($info);
$nomEmetteur = trim((string) ($info['raison_sociale'] ?? '')) ?: (string) ($organisation['nom'] ?? '');
$juridique = \App\Models\EntrepriseInfo::ligneJuridique($info);
$brut = 0.0; $remTot = 0.0; $avecRem = false;
foreach ($lignes as $l0) {
    if ($l0['quantite'] !== null && $l0['prix_unitaire'] !== null) { $b = (float) $l0['quantite'] * (float) $l0['prix_unitaire']; $brut += $b; $remTot += $b - (float) $l0['montant']; }
    if ((float) ($l0['remise_pct'] ?? 0) > 0) { $avecRem = true; }
}
$fmt = fn($m) => number_format((float) $m, 2, ',', ' ');
$qte = fn($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bon de commande <?= View::e($bon['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --c: <?= View::e($col) ?>; }
  <?php if ($mep === 'moderne'): ?>.entete { background: var(--c); color: #fff; padding: 14px 18px; border-bottom: 0; } .entete h1 { color: #fff; }<?php elseif ($mep === 'sobre'): ?>h1 { color: #1f2430; } .entete { border-bottom-color: #1f2430; } th { background: #eef0f4; }<?php endif; ?>
  body { font-family: -apple-system, Arial, sans-serif; color: #1f2430; margin: 0; padding: 30px; max-width: 820px; margin: 0 auto; font-size: 13px; line-height: 1.5; }
  h1 { font-size: 22px; margin: 0; color: var(--c); }
  .entete { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid var(--c); padding-bottom: 12px; margin-bottom: 18px; }
  .bloc { display: flex; gap: 20px; margin-bottom: 18px; }
  .bloc > div { flex: 1; border: 1px solid #ddd; border-radius: 6px; padding: 10px 12px; }
  .bloc h2 { font-size: 11px; margin: 0 0 6px; color: #666; text-transform: uppercase; letter-spacing: .04em; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { background: #f7f8fa; font-size: 11px; }
  .num { text-align: right; white-space: nowrap; }
  .total td { font-weight: 700; border-top: 2px solid #1f2430; border-bottom: 0; }
  .annule { background: #fee2e2; color: #991b1b; padding: 8px 12px; border-radius: 6px; margin-bottom: 14px; font-weight: 700; }
  .print-btn { margin-bottom: 18px; padding: 8px 14px; cursor: pointer; }
  .pied { margin-top: 28px; font-size: 11px; color: #777; }
  @media print { .print-btn { display: none; } body { padding: 0; } tr { page-break-inside: avoid; } }
  @media (max-width: 600px) { .entete, .bloc { flex-direction: column; } }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
<?php if ($bon['statut'] === 'annule'): ?><div class="annule">BON ANNULÉ<?= !empty($bon['annule_motif']) ? ' — ' . View::e($bon['annule_motif']) : '' ?></div><?php endif; ?>
<?php if ($bon['statut'] === 'brouillon'): ?><div class="print-btn" style="cursor:default;color:#92400e">Ce bon est encore un brouillon : vérifiez-le avant de l’envoyer.</div><?php endif; ?>
<div class="entete">
  <div>
    <h1>BON DE COMMANDE</h1>
    <div><strong><?= View::e($bon['reference']) ?></strong> · Date : <?= date('d/m/Y', strtotime($bon['date_emission'])) ?></div>
    <?php if (!empty($bon['dossier_reference'])): ?><div>Notre dossier : <?= View::e($bon['dossier_reference']) ?></div><?php endif; ?>
    <?php if (!empty($bon['reference_offre'])): ?><div>Suite à votre offre : <?= View::e($bon['reference_offre']) ?></div><?php endif; ?>
  </div>
  <div style="text-align:right">
    <?php if (!empty($logoUri)): ?><img src="<?= $logoUri ?>" alt="" style="max-height:54px;max-width:200px;display:block;margin:0 0 6px auto"><?php endif; ?>
    <strong><?= View::e($nomEmetteur) ?></strong><br>
    <?php if (!empty($info['adresse'])): ?><?= nl2br(View::e((string) $info['adresse'])) ?><br><?php endif; ?>
    <?= View::e(implode(' · ', array_filter([!empty($info['telephone']) ? 'Tél. ' . $info['telephone'] : '', $info['email'] ?? '']))) ?>
  </div>
</div>
<div class="bloc">
  <div><h2>Fournisseur</h2><strong><?= View::e($bon['fournisseur_nom']) ?></strong><br><?= View::e($bon['fournisseur_adresse']) ?><br>
    <?php if ($bon['destinataire_nom'] !== '' || $bon['destinataire_email'] !== ''): ?>À l’attention de : <?= View::e($bon['destinataire_nom']) ?> <?= $bon['destinataire_email'] !== '' ? View::e($bon['destinataire_email']) : '' ?><?php endif; ?></div>
  <div><h2>Conditions</h2>
    Devise : <strong><?= View::e($bon['devise']) ?></strong><br>
    <?php if ($bon['incoterm'] !== ''): ?>Incoterm : <?= View::e($bon['incoterm']) ?><br><?php endif; ?>
    <?php if ($bon['conditions_paiement'] !== ''): ?>Paiement : <?= View::e($bon['conditions_paiement']) ?><br><?php endif; ?>
    <?php if ($bon['date_livraison_souhaitee']): ?>Livraison souhaitée : <strong><?= date('d/m/Y', strtotime($bon['date_livraison_souhaitee'])) ?></strong><br><?php endif; ?>
    <?php if ($bon['lieu_livraison'] !== ''): ?>Livraison à : <?= View::e($bon['lieu_livraison']) ?><br><?php endif; ?>
    <?php if (!empty($info['adresse'])): ?>Facturation à : <?= View::e($nomEmetteur) ?>, <?= View::e(trim(preg_replace('/\s*\R\s*/u', ', ', (string) $info['adresse']))) ?><?php endif; ?></div>
</div>
<table>
  <thead><tr><th>Désignation</th><th class="num">Quantité</th><th>Unité</th><th class="num">Prix unitaire</th><?= $avecRem ? '<th class="num">Remise</th>' : '' ?><th class="num">Montant</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $l): ?>
    <tr><td><?= View::e($l['designation']) ?></td><td class="num"><?= View::e($qte($l['quantite'])) ?></td><td><?= View::e($l['unite']) ?></td>
      <td class="num"><?= $l['prix_unitaire'] !== null ? $fmt($l['prix_unitaire']) : '' ?></td><?= $avecRem ? '<td class="num">' . ((float) ($l['remise_pct'] ?? 0) > 0 ? View::e(rtrim(rtrim(number_format((float) $l['remise_pct'], 2, ',', ''), '0'), ',')) . ' %' : '') . '</td>' : '' ?><td class="num"><?= $l['montant'] !== null ? $fmt($l['montant']) : '' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
  <?php if ($remTot > 0.004): ?><tr><td colspan="<?= $avecRem ? 5 : 4 ?>" class="num">Total brut</td><td class="num"><?= $fmt($brut) ?></td></tr><tr><td colspan="<?= $avecRem ? 5 : 4 ?>" class="num">Remises</td><td class="num">− <?= $fmt($remTot) ?></td></tr><?php endif; ?>
  <tr class="total"><td colspan="<?= $avecRem ? 5 : 4 ?>" class="num"><?= $remTot > 0.004 ? 'Total net ' : 'Total ' ?><?= View::e($bon['devise']) ?></td><td class="num"><?= $fmt($bon['montant_total']) ?></td></tr></tfoot>
</table>
<?php if (!empty($bon['notes'])): ?><div><strong>Instructions</strong><div style="white-space:pre-wrap"><?= View::e($bon['notes']) ?></div></div><?php endif; ?>
<div class="pied"><?php if ($juridique !== '' || !empty($info['identifiants'])): ?><?= View::e(implode('  ·  ', array_filter([$nomEmetteur, $juridique, trim(preg_replace('/\s*\R\s*/u', '  ·  ', (string) ($info['identifiants'] ?? '')))]))) ?><br><?php endif; ?>Merci de nous confirmer la réception de ce bon et la date de livraison prévue. Édité le <?= date('d/m/Y H:i') ?> par <?= View::e($emetteur) ?>.</div>
</body>
</html>
