<?php
use App\Core\View;
use App\Models\BonSuivi;
/** Lien fournisseur — documents. Variables : $lien, $fournisseur, $bons, $docs, $preBon, $onglet, $emetteur, $base, $nbCommandes, $csrfToken, $flashPortail. */
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Documents — <?= View::e($fournisseur['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/../portail/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1>Vos documents</h1><div class="sub">Envoyez votre facture ou votre bon de livraison.</div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <?php include __DIR__ . '/_onglets.php'; ?>
  <div class="card"><h2>Envoyer un document</h2>
  <?php if (!$bons): ?><div class="mut">Aucune commande en cours : vous pourrez envoyer un document dès qu'un bon de commande vous sera adressé.</div><?php else: ?>
    <form method="post" enctype="multipart/form-data" action="<?= $base ?>/documents"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <label>Commande concernée</label><select name="bon_id"><?php foreach ($bons as $b): ?><option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === (int) $preBon ? 'selected' : '' ?>><?= View::e($b['reference'] . (!empty($b['dossier_reference']) ? ' · ' . $b['dossier_reference'] : '')) ?></option><?php endforeach; ?></select>
      <label>Type de document</label><select name="type"><?php foreach (BonSuivi::TYPES as $k => $lib): ?><option value="<?= $k ?>"><?= View::e($lib) ?></option><?php endforeach; ?></select>
      <label>Fichier (PDF ou photo, 10 Mo maximum)</label><input type="file" name="fichier" accept="application/pdf,image/jpeg,image/png,image/webp">
      <div class="act"><button class="btn bp" type="submit">Envoyer</button></div></form>
  <?php endif; ?></div>
  <div class="card"><h2>Déjà envoyés</h2>
    <?php if (!$docs): ?><div class="mut">Aucun document envoyé.</div><?php endif; ?>
    <?php foreach ($docs as $x): ?><div class="row"><div><div class="ref"><?= View::e($x['nom_original']) ?></div><div class="mut"><?= View::e(BonSuivi::TYPES[$x['type']] ?? 'Autre') ?> · <?= View::e($x['bon_reference']) ?> · <?= date('d/m/Y', strtotime($x['created_at'])) ?></div></div><span class="badge gr">Reçu</span></div><?php endforeach; ?></div>
  <div class="priv">Ce lien est personnel : ne le partagez pas.</div>
</div></body></html>
