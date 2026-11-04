<?php use App\Core\View; use App\Services\DevisPdf; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Demande d'offre — <?= View::e($consultation['reference'] ?? '') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<style>
*{box-sizing:border-box} body{margin:0;background:#eef0f4;font:14px/1.45 -apple-system,Segoe UI,Roboto,sans-serif;color:#1c2030;padding:12px 0}
.print-btn{background:#0B7C8A;color:#fff;border:0;border-radius:6px;padding:9px 14px;font-size:14px;text-decoration:none;cursor:pointer;display:inline-block}
.page{max-width:820px;margin:12px auto 24px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.1);display:flex;flex-direction:column;min-height:1000px}
.ent{padding:26px 34px 16px;border-bottom:4px solid #0B7C8A;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.emet .logo{max-height:64px;max-width:220px;display:block;margin-bottom:8px}.emet .coord{font-size:12.5px;color:#556;margin-top:3px}.ent .nom{font-size:22px;font-weight:700}.ent .doc{text-align:right}.ent .doc b{font-size:20px;letter-spacing:.5px;display:block}
.corps{padding:18px 34px;flex:1}
.bloc{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:18px}.bloc>div{flex:1;min-width:230px;background:#F2F7F8;border-radius:8px;padding:10px 14px}
.bloc h3{margin:0 0 4px;font-size:12px;color:#667;font-weight:600}
h2{font-size:14px;color:#0B7C8A;margin:20px 0 8px;border-bottom:1px solid #e6eef0;padding-bottom:6px}
table{width:100%;border-collapse:collapse;margin-top:6px}
th{background:#0B7C8A;color:#fff;font-size:12px;text-align:left;padding:8px}td{padding:8px;border-bottom:1px solid #e6e8ef;vertical-align:top}
table.kv th{background:#F2F7F8;color:#334;width:38%;font-weight:600}
.d{font-size:12px;color:#667}.r{text-align:right;white-space:nowrap}.tw{overflow-x:auto}
.cond{margin-top:14px;font-size:13px}
.piece-link{display:inline-block;margin:4px 8px 4px 0;padding:6px 12px;background:#F2F7F8;border-radius:6px;text-decoration:none;color:#1c2030;font-size:13px}
.pied{padding:12px 34px;border-top:1px solid #e0e3ea;font-size:11.5px;color:#667;line-height:1.5}.pied .pr{text-align:right;color:#99a;margin-top:2px}
@media print{body{background:#fff;padding:0}.no-print{display:none!important}.page{box-shadow:none;margin:0;max-width:none;min-height:0}}
@media (max-width:600px){.ent,.corps,.pied{padding-left:16px;padding-right:16px}.ent .doc{text-align:left}}
</style>
</head>
<body>
<?php if (!empty($apercu)): ?>
<div class="no-print" style="max-width:720px;margin:0 auto 16px;background:#fffbeb;border:1px solid #f59e0b;color:#92400e;padding:12px 16px;border-radius:8px;font-size:13px">
  <strong>Aperçu interne</strong> — voici exactement ce que le fournisseur voit. Rien n'a été envoyé et aucun lien n'a été créé par cet aperçu.
</div>
<?php endif; ?>
<?php $urlPdf = $pdfUrl ?? ('/index.php?r=partage-public/' . View::e($partage['token'] ?? '') . '/pdf'); ?>
<div class="no-print" style="max-width:720px;margin:0 auto 16px;display:flex;gap:8px;flex-wrap:wrap">
  <a class="print-btn" style="text-decoration:none;display:inline-block;margin:0" href="<?= $urlPdf ?>">Télécharger le PDF</a>
  <button class="print-btn" style="background:#6b7280;margin:0" onclick="window.print()">Imprimer</button>
  <?php if (!empty($basculeUrl)): ?>
    <a class="print-btn" style="text-decoration:none;display:inline-block;margin:0;background:#fff;color:#374151;border:1px solid #d1d5db" href="<?= $basculeUrl ?>"><?= $masquerClient ? 'Afficher le nom du client' : 'Masquer le nom du client' ?></a>
  <?php endif; ?>
</div>

<div class="page">
  <?php $em = $emetteur ?? ['nom' => '']; ?>
  <div class="ent">
    <div class="emet">
      <?php if (!empty($logoUri)): ?><img class="logo" src="<?= $logoUri ?>" alt="<?= View::e($em['nom']) ?>"><?php endif; ?>
      <div class="nom"><?= View::e($em['nom']) ?></div>
      <div class="coord"><?= !empty($em['adresse']) ? nl2br(View::e($em['adresse'])) . '<br>' : '' ?><?= !empty($em['telephone']) ? 'Tél. ' . View::e($em['telephone']) : '' ?><?= !empty($em['telephone']) && !empty($em['email']) ? ' · ' : '' ?><?= View::e((string) ($em['email'] ?? '')) ?></div>
    </div>
    <div class="doc"><b>DEMANDE D'OFFRE <?= View::e($consultation['reference'] ?? '') ?></b>
      Date : <?= date('d/m/Y') ?><br>
      <?= !empty($partage['echeance_reponse']) ? '<strong>Réponse souhaitée avant le ' . date('d/m/Y', strtotime($partage['echeance_reponse'])) . '</strong>' : '' ?></div>
  </div>
  <div class="corps">
  <div class="bloc">
    <div><h3>Objet</h3><strong><?= View::e($dossier['objet'] ?? '') ?></strong><br>Dossier : <?= View::e($dossier['reference'] ?? '') ?><?php if (!$masquerClient): ?><br>Client : <?= View::e($demande['expediteur_entreprise'] ?? $demande['expediteur_nom'] ?? '—') ?><?php endif; ?></div>
    <div><h3>Livraison souhaitée</h3>Destination : <?= View::e($demande['destination_pays'] ?? '') ?: 'Non précisée' ?><br>
      <?php if (!$masquerClient): ?>Lieu : <?= View::e($demande['lieu_livraison'] ?? '') ?: 'Non précisé' ?><br><?php endif; ?>
      Incoterm : <?= View::e($demande['incoterm_souhaite'] ?? '') ?: 'Non précisé' ?></div>
  </div>

  <?php if (trim((string) ($consultation['articles_demandes'] ?? '')) !== ''): ?>
  <h2>Besoins communiqués</h2>
  <div style="white-space:pre-wrap;font-size:13px"><?= View::e($consultation['articles_demandes']) ?></div>
  <?php endif; ?>

  <?php if (!empty($articles)): ?>
  <h2>Articles demandés</h2>
  <div class="tw"><table>
    <thead><tr><th>N°</th><th>Désignation</th><th class="r">Qté</th><th>Unité</th><th>Conditionnement</th><th>Référence</th><th>Marque</th><th>Caractéristiques</th></tr></thead>
    <tbody>
    <?php foreach ($articles as $i => $a): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= View::e($a['designation']) ?></td>
        <td class="r"><?= View::e(rtrim(rtrim(number_format((float) $a['quantite'], 2, ',', ''), '0'), ',')) ?></td>
        <td><?= View::e((string) $a['unite']) ?></td>
        <td><?= View::e((string) ($a['conditionnement'] ?? '')) ?></td>
        <td><?= View::e((string) $a['reference']) ?></td>
        <td><?= View::e((string) $a['marque']) ?></td>
        <td><?= View::e((string) ($a['caracteristiques'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

  <?php if (!empty($detailsDemande)): ?>
  <h2>Détails de la demande</h2>
  <table class="kv"><tbody>
    <?php foreach ($detailsDemande as $lib => $val): ?>
      <tr><th><?= View::e($lib) ?></th><td><?= View::e($val) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
  <?php
    $urlPiece = fn($x) => !empty($apercu)
        ? '/index.php?r=dossiers/' . (int) $x['dossier_id'] . '/pieces/' . (int) $x['id'] . '/telecharger&apercu=1'
        : '/index.php?r=partage-public/' . View::e($partage['token']) . '/pieces/' . (int) $x['id'];
    $photosR = array_filter($pieces ?? [], fn($x) => str_starts_with((string) ($x['type_mime'] ?? ''), 'image/'));
    $docsR = array_filter($pieces ?? [], fn($x) => !str_starts_with((string) ($x['type_mime'] ?? ''), 'image/'));
  ?>
  <?php if (!empty($photosR)): ?>
  <h2>Photos</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach ($photosR as $p): ?>
      <a href="<?= $urlPiece($p) ?>" target="_blank"><img src="<?= $urlPiece($p) ?>" alt="<?= View::e($p['nom_original']) ?>" style="width:150px;height:112px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb"></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($docsR)): ?>
  <h2>Documents</h2>
  <?php foreach ($docsR as $p): ?>
    <a class="piece-link" href="<?= $urlPiece($p) ?>" target="_blank"><?= View::e($p['nom_original']) ?></a>
  <?php endforeach; ?>
  <?php endif; ?>

  <h2>Votre offre</h2>
  <div class="cond">Merci de nous indiquer pour chaque article : prix unitaire et devise, délai de production / livraison, quantité minimale de commande, validité de l'offre, conditions de paiement et Incoterm proposé.<?php if (!empty($partage['echeance_reponse'])): ?> <strong>Réponse souhaitée avant le <?= date('d/m/Y', strtotime($partage['echeance_reponse'])) ?>.</strong><?php endif; ?></div>
  <div class="cond d">Ce lien est temporaire et peut être révoqué à tout moment par l'expéditeur. Merci de répondre directement par le canal utilisé pour vous transmettre ce lien.</div>
  </div>
  <div class="pied">
    <div><strong><?= View::e($em['nom']) ?></strong><?= !empty($em['adresse']) ? ' — ' . View::e(trim(preg_replace('/\s*\R\s*/u', ', ', (string) $em['adresse']))) : '' ?></div>
    <div><?= View::e(implode('  ·  ', array_filter([!empty($em['telephone']) ? 'Tél. ' . $em['telephone'] : '', $em['email'] ?? '', $em['site_web'] ?? '']))) ?></div>
    <?php if (!empty($em['identifiants'])): ?><div><?= View::e(trim(preg_replace('/\s*\R\s*/u', '  ·  ', (string) $em['identifiants']))) ?></div><?php endif; ?>
    <div class="pr">Demande d'offre <?= View::e($consultation['reference'] ?? '') ?></div>
  </div>
</div>
</body>
</html>
