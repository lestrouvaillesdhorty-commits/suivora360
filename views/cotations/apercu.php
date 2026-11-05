<?php
use App\Core\View;
use App\Models\Demande;
use App\Services\DevisPdf;
$dev = (string) $cotation['devise'];
$adr = trim(implode(', ', array_filter([(string) ($client['adresse'] ?? ''), trim(($client['code_postal'] ?? '') . ' ' . ($client['ville'] ?? '')), (string) ($client['pays'] ?? '')])));
$n = fn($x) => DevisPdf::fmt($x);
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Devis <?= View::e($cotation['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<style>
*{box-sizing:border-box} body{margin:0;background:#eef0f4;font:14px/1.45 -apple-system,Segoe UI,Roboto,sans-serif;color:#1c2030}
.bar{max-width:820px;margin:14px auto 0;padding:0 12px;display:flex;gap:8px;flex-wrap:wrap}
.bar a,.bar button{background:#0B7C8A;color:#fff;border:0;border-radius:6px;padding:9px 14px;font-size:14px;text-decoration:none;cursor:pointer}
.bar a.s{background:#fff;color:#0B7C8A;border:1px solid #BFE0E5}
.page{max-width:820px;margin:12px auto 24px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.1);display:flex;flex-direction:column;min-height:1000px}
.ent{padding:26px 34px 16px;border-bottom:4px solid #0B7C8A;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.emet .logo{max-height:64px;max-width:220px;display:block;margin-bottom:8px}.emet .coord{font-size:12.5px;color:#556;margin-top:3px}.ent .nom{font-size:22px;font-weight:700}.ent .doc{text-align:right}.ent .doc b{font-size:20px;letter-spacing:.5px;display:block}
.corps{padding:18px 34px;flex:1}
.bloc{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:18px}.bloc>div{flex:1;min-width:230px;background:#F2F7F8;border-radius:8px;padding:10px 14px}
.bloc h3{margin:0 0 4px;font-size:12px;color:#667;font-weight:600}
table{width:100%;border-collapse:collapse;margin-top:6px}
th{background:#0B7C8A;color:#fff;font-size:12px;text-align:left;padding:8px}td{padding:8px;border-bottom:1px solid #e6e8ef;vertical-align:top}
.d{font-size:12px;color:#667}.r{text-align:right;white-space:nowrap}
.tot{margin:16px 0 0 auto;width:min(340px,100%)}.tot div{display:flex;justify-content:space-between;padding:5px 0}
.tot .ttc{border-top:2px solid #0B7C8A;margin-top:4px;padding-top:9px;font-size:18px;font-weight:700}
.cond{margin-top:18px;font-size:13px}.pied{padding:12px 34px;border-top:1px solid #e0e3ea;font-size:11.5px;color:#667;line-height:1.5}.pied .pr{text-align:right;color:#99a;margin-top:2px}.sign{display:flex;gap:18px;margin-top:26px;align-items:stretch}.sign>div:first-child{flex:1;font-size:13px}.sign .zone{flex:1;min-height:90px;border:1.5px dashed #BFE0E5;border-radius:10px}
.tw{overflow-x:auto}
@media print{body{background:#fff}.bar{display:none}.page{box-shadow:none;margin:0;max-width:none;min-height:0}}
@media (max-width:600px){.ent,.corps,.pied{padding-left:16px;padding-right:16px}.ent .doc{text-align:left}}
</style></head><body>
<div class="bar">
  <button onclick="window.print()">Imprimer / enregistrer en PDF</button>
  <a class="s" href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/pdf">Télécharger le PDF</a>
  <a class="s" href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/envoyer">Envoyer au client</a>
  <a class="s" href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>">Retour</a>
</div>
<div class="page">
  <div class="ent">
    <div class="emet">
      <?php if (!empty($logoUri)): ?><img class="logo" src="<?= $logoUri ?>" alt="<?= View::e($emetteur['nom']) ?>"><?php endif; ?>
      <div class="nom"><?= View::e($emetteur['nom']) ?></div>
      <div class="coord"><?= !empty($emetteur['adresse']) ? nl2br(View::e($emetteur['adresse'])) . '<br>' : '' ?><?= !empty($emetteur['telephone']) ? 'Tél. ' . View::e($emetteur['telephone']) : '' ?><?= !empty($emetteur['telephone']) && !empty($emetteur['email']) ? ' · ' : '' ?><?= View::e((string) ($emetteur['email'] ?? '')) ?></div>
    </div>
    <div class="doc"><b>DEVIS <?= View::e($cotation['reference']) ?></b>
      <?= (int) ($cotation['version'] ?? 1) > 1 ? 'Version ' . (int) $cotation['version'] . '<br>' : '' ?>
      Date : <?= date('d/m/Y', strtotime((string) $cotation['created_at'])) ?><br>
      <?php $validite = DevisPdf::validite($cotation, $emetteur); ?><?= $validite ? 'Valable jusqu\'au ' . date('d/m/Y', strtotime($validite)) : '' ?></div>
  </div>
  <div class="corps">
    <div class="bloc">
      <div><h3>Client</h3><strong><?= View::e((string) ($client['nom'] ?? $cotation['client_nom'])) ?></strong><br>
        <?= $adr !== '' ? View::e($adr) . '<br>' : '' ?><?= !empty($client['email']) ? View::e($client['email']) . '<br>' : '' ?><?= !empty($client['telephone']) ? View::e($client['telephone']) : '' ?></div>
      <div><h3>Références</h3>Dossier : <?= View::e($cotation['dossier_reference']) ?><br>
        <?= !empty($cotation['incoterm_client']) ? 'Incoterm : ' . View::e(Demande::INCOTERMS[$cotation['incoterm_client']] ?? $cotation['incoterm_client']) . '<br>' : '' ?>
        <?= !empty($cotation['mode_paiement_negocie']) ? 'Paiement : ' . View::e(Demande::MODES_PAIEMENT[$cotation['mode_paiement_negocie']] ?? $cotation['mode_paiement_negocie']) : '' ?></div>
    </div>
    <?php if ($items): ?>
    <div class="tw"><table>
      <thead><tr><th>N°</th><th>Désignation</th><th class="r">Qté</th><th>Unité</th><th class="r">PU HT</th><th class="r">Total HT</th></tr></thead>
      <tbody>
      <?php foreach ($items as $i => $it): $det = DevisPdf::details($it); ?>
        <tr><td><?= $i + 1 ?></td><td><?= View::e($it['designation']) ?><?= $det !== '' ? '<div class="d">' . View::e($det) . '</div>' : '' ?></td>
          <td class="r"><?= View::e(rtrim(rtrim(number_format((float) $it['quantite'], 2, ',', ''), '0'), ',')) ?></td><td><?= View::e((string) $it['unite']) ?></td>
          <td class="r"><?= $it['prix_unitaire'] !== null ? $n($it['prix_unitaire']) : '—' ?></td><td class="r"><?= $it['montant'] !== null ? $n($it['montant']) : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?><p>Offre globale (voir les montants ci-dessous).</p><?php endif; ?>
    <div class="tot">
      <div><span>Total HT</span><span><?= $n($totaux['ht']) ?> <?= View::e($dev) ?></span></div>
      <div><span>TVA<?= $totaux['tva_taux'] !== null ? ' (' . rtrim(rtrim(number_format($totaux['tva_taux'], 2, ',', ''), '0'), ',') . ' %)' : '' ?></span><span><?= $n($totaux['tva']) ?> <?= View::e($dev) ?></span></div>
      <div class="ttc"><span>Total TTC</span><span><?= $n($totaux['ttc']) ?> <?= View::e($dev) ?></span></div>
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
    <div class="pl"><?= View::e(implode('  ·  ', array_filter([!empty($emetteur['telephone']) ? 'Tél. ' . $emetteur['telephone'] : '', $emetteur['email'] ?? '', $emetteur['site_web'] ?? '']))) ?></div>
    <?php if (!empty($emetteur['identifiants'])): ?><div class="pl"><?= View::e(trim(preg_replace('/\s*\R\s*/u', '  ·  ', (string) $emetteur['identifiants']))) ?></div><?php endif; ?>
    <div class="pl pr">Devis <?= View::e($cotation['reference']) ?> — <?= View::e($dev) ?></div>
  </div>
</div>
</body></html>
