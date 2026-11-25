<?php
use App\Core\View;
use App\Models\Demande;
use App\Services\DevisPdf;
$dev = (string) $cotation['devise'];
$adr = trim(implode(', ', array_filter([(string) ($client['adresse'] ?? ''), trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')), (string) ($client['pays'] ?? '')])));
$n = fn($x) => DevisPdf::fmt($x);
$col = \App\Models\EntrepriseInfo::couleurEffective($emetteur);
$mep = \App\Models\EntrepriseInfo::miseEnPage($emetteur);
$livr = trim((string) (($cotation['adresse_livraison'] ?? '') !== '' ? $cotation['adresse_livraison'] : ($client['adresse_livraison'] ?? '')));
$avecRemise = false; foreach ($items as $it) { if ((float) ($it['remise_pct'] ?? 0) > 0) { $avecRemise = true; } }
$juridique = \App\Models\EntrepriseInfo::ligneJuridique($emetteur);
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Devis <?= View::e($cotation['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<style>
:root{--c:<?= View::e($col) ?>}
*{box-sizing:border-box} body{margin:0;background:#eef0f4;font:14px/1.45 -apple-system,Segoe UI,Roboto,sans-serif;color:#1c2030}
.bar{max-width:820px;margin:14px auto 0;padding:0 12px;display:flex;gap:8px;flex-wrap:wrap}
.bar a,.bar button{background:var(--c);color:#fff;border:0;border-radius:6px;padding:9px 14px;font-size:14px;text-decoration:none;cursor:pointer}
.bar a.s{background:#fff;color:var(--c);border:1px solid #BFE0E5}
.page{max-width:820px;margin:12px auto 24px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.1);display:flex;flex-direction:column;min-height:1000px}
.ent{padding:26px 34px 16px;border-bottom:4px solid var(--c);display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.emet .logo{max-height:64px;max-width:220px;display:block;margin-bottom:8px}.emet .coord{font-size:12.5px;color:#556;margin-top:3px}.ent .nom{font-size:22px;font-weight:700}.ent .doc{text-align:right}.ent .doc b{font-size:20px;letter-spacing:.5px;display:block}
.corps{padding:18px 34px;flex:1}
.bloc{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:18px}.bloc>div{flex:1;min-width:230px;background:#F2F7F8;background:color-mix(in srgb,var(--c) 8%,#fff);border-radius:8px;padding:10px 14px}
.bloc h3{margin:0 0 4px;font-size:12px;color:#667;font-weight:600}
.sec{margin:16px 0 6px;font-weight:700;color:var(--c);border-bottom:1px solid #e6e8ef;padding-bottom:4px}
table{width:100%;border-collapse:collapse;margin-top:6px}
th{background:var(--c);color:#fff;font-size:12px;text-align:left;padding:8px}td{padding:8px;border-bottom:1px solid #e6e8ef;vertical-align:top}
.d{font-size:12px;color:#667}.r{text-align:right;white-space:nowrap}
.tot{margin:16px 0 0 auto;width:min(340px,100%)}.tot div{display:flex;justify-content:space-between;padding:5px 0}
.tot .ttc{border-top:2px solid var(--c);margin-top:4px;padding-top:9px;font-size:18px;font-weight:700}
.cond{margin-top:18px;font-size:13px}.pied{padding:12px 34px;border-top:1px solid #e0e3ea;font-size:11.5px;color:#667;line-height:1.5}.pied .pr{text-align:right;color:#99a;margin-top:2px}.sign{display:flex;gap:18px;margin-top:26px;align-items:stretch}.sign>div:first-child{flex:1;font-size:13px}.sign .zone{flex:1;min-height:90px;border:1.5px dashed #BFE0E5;border-radius:10px}
.tw{overflow-x:auto}
<?php if ($mep === 'moderne'): ?>
.band{background:var(--c);color:#fff;padding:14px 34px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.band b{font-size:20px;letter-spacing:.02em}.band .meta{text-align:right;font-size:12px;line-height:1.5}
.ent{border-bottom:0;padding-top:20px}.ent .emet .logo{background:#fff}
th{background:transparent!important;color:var(--c)!important;border-bottom:2px solid var(--c)}
.bloc>div{background:#fff;border:1px solid #DDE4EC;border-left:4px solid var(--c);border-radius:6px}
.tot .ttc{background:var(--c);color:#fff;border-top:0;padding:8px 10px;border-radius:6px;font-size:17px}
<?php elseif ($mep === 'sobre'): ?>
.ent{border-bottom:1px solid #1c2030}th{background:#eef0f4;color:#1c2030;border-bottom:2px solid #1c2030}.tot .ttc{border-top:2px solid #1c2030}.bar a,.bar button{background:#1c2030}.bar a.s{color:#1c2030;background:#fff;border-color:#c9ced8}
<?php endif; ?>
@page{size:A4;margin:0}
html{-webkit-print-color-adjust:exact;print-color-adjust:exact}
@media print{body{background:#fff}.bar,.noprint{display:none!important}.page{box-shadow:none;margin:0;max-width:none;min-height:296mm}.pied{page-break-inside:avoid}tr{page-break-inside:avoid}}
@media (max-width:600px){.ent,.corps,.pied{padding-left:16px;padding-right:16px}.ent .doc{text-align:left}}
</style></head><body>
<div class="bar">
  <button onclick="window.print()">Imprimer / enregistrer en PDF</button>
  <?php if (!empty($barreClient)): ?>
  <a class="s" href="<?= View::e($barreClient['pdf']) ?>">Télécharger le PDF</a>
  <a class="s" href="<?= View::e($barreClient['retour']) ?>">Retour</a>
  <?php else: ?>
  <a class="s" href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/pdf">Télécharger le PDF</a>
  <a class="s" href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/envoyer">Envoyer au client</a>
  <a class="s" href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>">Retour</a>
  <?php endif; ?>
</div>
<div class="page">
  <?php $validiteB = DevisPdf::validite($cotation, $emetteur); if ($mep === 'moderne'): ?>
  <div class="band"><b>DEVIS <?= View::e($cotation['reference']) ?></b><div class="meta"><?= (int) ($cotation['version'] ?? 1) > 1 ? 'Version ' . (int) $cotation['version'] . '<br>' : '' ?>Date : <?= date('d/m/Y', strtotime((string) $cotation['created_at'])) ?><?= $validiteB ? '<br>Valable jusqu\'au ' . date('d/m/Y', strtotime($validiteB)) : '' ?></div></div>
  <?php endif; ?>
  <div class="ent">
    <div class="emet">
      <?php $mode = \App\Models\EntrepriseInfo::enteteMode($emetteur, !empty($logoUri)); ?>
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <?php if ($mode !== 'nom'): ?><img class="logo" style="margin:0" src="<?= $logoUri ?>" alt="<?= View::e($emetteur['nom']) ?>"><?php endif; ?>
      <?php if ($mode !== 'logo'): ?><div class="nom"><?= View::e($emetteur['nom']) ?></div><?php endif; ?>
      </div><div style="height:8px"></div>
      <div class="coord"><?= !empty($emetteur['adresse']) ? nl2br(View::e($emetteur['adresse'])) . '<br>' : '' ?><?= !empty($emetteur['telephone']) ? 'Tél. ' . View::e($emetteur['telephone']) : '' ?><?= !empty($emetteur['telephone']) && !empty($emetteur['email']) ? ' · ' : '' ?><?= View::e((string) ($emetteur['email'] ?? '')) ?></div>
    </div>
    <?php if ($mep !== 'moderne'): ?><div class="doc"><b>DEVIS <?= View::e($cotation['reference']) ?></b>
      <?= (int) ($cotation['version'] ?? 1) > 1 ? 'Version ' . (int) $cotation['version'] . '<br>' : '' ?>
      Date : <?= date('d/m/Y', strtotime((string) $cotation['created_at'])) ?><br>
      <?php $validite = DevisPdf::validite($cotation, $emetteur); ?><?= $validite ? 'Valable jusqu\'au ' . date('d/m/Y', strtotime($validite)) : '' ?></div><?php endif; ?>
  </div>
  <div class="corps">
    <div class="bloc">
      <div><h3>Client<?= $livr !== '' ? ' · facturation' : '' ?></h3><strong><?= View::e((string) ($client['nom'] ?? $cotation['client_nom'])) ?></strong><br>
        <?= $adr !== '' ? View::e($adr) . '<br>' : '' ?><?= !empty($client['email']) ? View::e($client['email']) . '<br>' : '' ?><?= !empty($client['telephone']) ? View::e($client['telephone']) : '' ?>
        <?php if ($livr !== ''): ?><h3 style="margin-top:8px">Livraison</h3><?= nl2br(View::e($livr)) ?><?php endif; ?></div>
      <div><h3>Références</h3>Dossier : <?= View::e($cotation['dossier_reference']) ?><br>
        <?= !empty($cotation['incoterm_client']) ? 'Incoterm : ' . View::e(Demande::INCOTERMS[$cotation['incoterm_client']] ?? $cotation['incoterm_client']) . '<br>' : '' ?>
        <?= !empty($cotation['mode_paiement_negocie']) ? 'Paiement : ' . View::e(Demande::MODES_PAIEMENT[$cotation['mode_paiement_negocie']] ?? $cotation['mode_paiement_negocie']) : '' ?></div>
    </div>
    <?php if ($items): ?>
    <div class="tw"><table>
      <thead><tr><th>N°</th><th>Désignation</th><th class="r">Qté</th><th>Unité</th><th class="r">PU HT</th><?= $avecRemise ? '<th class="r">Remise</th>' : '' ?><th class="r">Total HT</th></tr></thead>
      <tbody>
      <?php $no = 0; foreach ($items as $i => $it): $det = DevisPdf::details($it); ?>
        <?php if (!empty($it['est_note'])): ?><tr><td></td><td colspan="<?= $avecRemise ? 6 : 5 ?>"><em><?= View::e($it['designation']) ?></em></td></tr><?php continue; endif; $no++; ?>
        <tr><td><?= $no ?></td><td><?= View::e($it['designation']) ?><?= $det !== '' ? '<div class="d">' . View::e($det) . '</div>' : '' ?></td>
          <td class="r"><?= View::e(rtrim(rtrim(number_format((float) $it['quantite'], 2, ',', ''), '0'), ',')) ?></td><td><?= View::e((string) $it['unite']) ?></td>
          <td class="r"><?= $it['prix_unitaire'] !== null ? $n($it['prix_unitaire']) : '—' ?></td><?= $avecRemise ? '<td class="r">' . ((float) ($it['remise_pct'] ?? 0) > 0 ? View::e(rtrim(rtrim(number_format((float) $it['remise_pct'], 2, ',', ''), '0'), ',')) . ' %' : '') . '</td>' : '' ?><td class="r"><?= $it['montant'] !== null ? $n($it['montant']) : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?><p>Offre globale (voir les montants ci-dessous).</p><?php endif; ?>
    <div class="tot">
      <?php if ($totaux['brut'] !== null): ?>
      <div><span>Total brut HT</span><span><?= $n($totaux['brut']) ?> <?= View::dev($dev) ?></span></div>
      <?php if ($totaux['remise_lignes'] > 0): ?><div><span>Remises sur lignes</span><span>− <?= $n($totaux['remise_lignes']) ?> <?= View::dev($dev) ?></span></div><?php endif; ?>
      <?php if ($totaux['remise_globale'] > 0): ?><div><span>Remise globale <?= View::e(rtrim(rtrim(number_format((float) $totaux['remise_globale_pct'], 2, ',', ''), '0'), ',')) ?> %<?= $totaux['remise_motif'] !== '' ? ' (' . View::e($totaux['remise_motif']) . ')' : '' ?></span><span>− <?= $n($totaux['remise_globale']) ?> <?= View::dev($dev) ?></span></div><?php endif; ?>
      <div><span>Total net HT</span><span><?= $n($totaux['ht']) ?> <?= View::dev($dev) ?></span></div>
      <?php else: ?>
      <div><span>Total HT</span><span><?= $n($totaux['ht']) ?> <?= View::dev($dev) ?></span></div>
      <?php endif; ?>
      <div><span>TVA<?= $totaux['tva_taux'] !== null ? ' (' . rtrim(rtrim(number_format($totaux['tva_taux'], 2, ',', ''), '0'), ',') . ' %)' : '' ?></span><span><?= $n($totaux['tva']) ?> <?= View::dev($dev) ?></span></div>
      <div class="ttc"><span>Total TTC</span><span><?= $n($totaux['ttc']) ?> <?= View::dev($dev) ?></span></div>
      <?php $d2 = DevisPdf::deuxiemeDevise($cotation); if ($d2 !== null): ?>
      <div style="font-weight:600"><span>Soit environ</span><span><?= View::e($d2['montant_txt']) ?> <?= View::e($d2['nom']) ?></span></div>
      <div style="font-size:11px;color:#667"><span><?= View::e($d2['ligne_taux']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php if (trim((string) $cotation['notes']) !== ''): ?><div class="cond"><strong>Remarques</strong><br><?= nl2br(View::e($cotation['notes'])) ?></div><?php endif; ?>
    <?php if (trim((string) ($emetteur['devis_conditions'] ?? '')) !== ''): ?><div class="cond"><strong>Conditions générales</strong><br><?= nl2br(View::e($emetteur['devis_conditions'])) ?></div><?php endif; ?>
    <?php if (trim((string) ($emetteur['coordonnees_bancaires'] ?? '')) !== ''): ?><div class="cond"><strong>Règlement par virement</strong><br><?= nl2br(View::e($emetteur['coordonnees_bancaires'])) ?></div><?php endif; ?>
    <div class="sign">
      <div><strong>Bon pour accord</strong><br><span class="d">Date, nom et qualité du signataire, signature précédée de la mention « Bon pour accord »</span></div>
      <div class="zone"></div>
    </div>
  </div>
  <?php if (!empty($photos)): ?>
  <div style="margin:18px 0 6px;font-weight:700;font-size:13px;border-bottom:1px solid #d9dde6;padding-bottom:4px">Photos du produit</div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px">
    <?php foreach ($photos as $uri): ?><img src="<?= $uri ?>" alt="Photo du produit" style="width:31%;max-height:190px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px"><?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="pied">
    <div class="pl"><strong><?= View::e($emetteur['nom']) ?></strong><?= !empty($emetteur['adresse']) ? ' — ' . View::e(trim(preg_replace('/\s*\R\s*/u', ', ', (string) $emetteur['adresse']))) : '' ?></div>
    <?php if (!empty($emetteur['site_web'])): ?><div class="pl"><?= View::e($emetteur['site_web']) ?></div><?php endif; ?>
    <?php if ($juridique !== ''): ?><div class="pl"><?= View::e($juridique) ?></div><?php endif; ?>
    <?php if (!empty($emetteur['identifiants'])): ?><div class="pl"><?= View::e(trim(preg_replace('/\s*\R\s*/u', '  ·  ', (string) $emetteur['identifiants']))) ?></div><?php endif; ?>
    <?php if (!empty($emetteur['mentions_pied'])): ?><div class="pl"><?= nl2br(View::e((string) $emetteur['mentions_pied'])) ?></div><?php endif; ?>
  </div>
</div>
</body></html>
