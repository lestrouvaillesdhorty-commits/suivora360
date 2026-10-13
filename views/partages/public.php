<?php use App\Core\View; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Demande d'offre — <?= View::e($dossier['reference'] ?? '') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 24px 16px; color: #1f2937; }
  .sheet { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .ref { color: #666; font-size: 13px; margin-bottom: 20px; }
  h2 { font-size: 14px; color: #374151; margin: 20px 0 8px; border-bottom: 1px solid #eef0f4; padding-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 6px; }
  th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #f1f2f5; }
  .print-btn { padding: 10px 18px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; }
  .piece-link { display: inline-block; margin: 4px 8px 4px 0; padding: 6px 12px; background: #f1f2f5; border-radius: 6px; text-decoration: none; color: #1f2937; font-size: 13px; }
  .note { font-size: 12px; color: #888; margin-top: 24px; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { box-shadow: none; }
    .no-print { display: none; }
  }
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

<div class="sheet">
  <?php $emetteur = \App\Models\Filiale::find((int) ($consultation['filiale_id'] ?? 0)); if (!empty($emetteur['nom'])): ?><div style="font-size:13px;color:#6b7280;margin-bottom:6px"><?= View::e($emetteur['nom']) ?></div><?php endif; ?>
  <h1>Demande d'offre — <?= View::e($dossier['objet'] ?? '') ?></h1>
  <div class="ref">Référence dossier : <?= View::e($dossier['reference'] ?? '') ?> — Référence consultation : <?= View::e($consultation['reference'] ?? '') ?></div>

  <?php if (!$masquerClient): ?>
    <h2>Client</h2>
    <div><?= View::e($demande['expediteur_entreprise'] ?? $demande['expediteur_nom'] ?? '—') ?></div>
  <?php endif; ?>

  <h2>Besoins communiqués</h2>
  <div style="white-space:pre-wrap;font-size:13px"><?= View::e($consultation['articles_demandes'] ?? '') ?: '—' ?></div>

  <?php if (!empty($articles)): ?>
  <table>
    <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Conditionnement</th><th>Référence</th><th>Marque</th><th>Caractéristiques</th></tr></thead>
    <tbody>
    <?php foreach ($articles as $a): ?>
      <tr>
        <td><?= View::e($a['designation']) ?></td>
        <td><?= View::e((string) $a['quantite']) ?></td>
        <td><?= View::e($a['unite']) ?></td>
        <td><?= View::e((string) ($a['conditionnement'] ?? '')) ?></td>
        <td><?= View::e($a['reference']) ?></td>
        <td><?= View::e($a['marque']) ?></td>
        <td><?= View::e((string) ($a['caracteristiques'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!empty($detailsDemande)): ?>
  <h2>Détails de la demande</h2>
  <table>
    <tbody>
    <?php foreach ($detailsDemande as $lib => $val): ?>
      <tr><th style="width:38%;font-weight:600"><?= View::e($lib) ?></th><td><?= View::e($val) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <h2>Spécifications logistiques</h2>
  <div style="font-size:13px">
    Destination : <?= View::e($demande['destination_pays'] ?? '') ?: 'Non précisée' ?><br>
    <?php if (!$masquerClient): ?>Lieu de livraison : <?= View::e($demande['lieu_livraison'] ?? '') ?: 'Non précisé' ?><br><?php endif; ?>
    Incoterm souhaité : <?= View::e($demande['incoterm_souhaite'] ?? '') ?: 'Non précisé' ?><br>
    Échéance de réponse souhaitée : <?= !empty($partage['echeance_reponse']) ? date('d/m/Y', strtotime($partage['echeance_reponse'])) : 'Non précisée' ?>
  </div>

  <?php if (!empty($pieces)): ?>
  <h2>Pièces jointes</h2>
  <?php foreach ($pieces as $p): ?>
    <a class="piece-link" href="<?= !empty($apercu) ? '/index.php?r=dossiers/' . (int) $p['dossier_id'] . '/pieces/' . (int) $p['id'] . '/telecharger' : '/index.php?r=partage-public/' . View::e($partage['token']) . '/pieces/' . (int) $p['id'] ?>" target="_blank"><?= View::e($p['nom_original']) ?></a>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="note">
    Ce lien est temporaire et peut être révoqué à tout moment par l'expéditeur. Merci de répondre directement par le canal utilisé pour vous transmettre ce lien.
  </div>
</div>
</body>
</html>
