<?php
use App\Core\View;
$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . ($d !== null && $d !== '' ? $d : '');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Facture <?= View::e($facture['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w" style="padding-top:20px">
  <a href="<?= $base ?>&onglet=factures" class="noprint mut">&larr; Retour à mon espace</a>
  <div class="card" style="margin-top:14px">
    <span class="badge <?= $facture['statut'] === 'payee' ? 'gr' : 'ye' ?>"><?= $facture['statut'] === 'payee' ? 'Payée' : 'À régler' ?></span>
    <h1 style="margin-top:8px">Facture <?= View::e($facture['reference']) ?></h1>
    <div class="sub">Dossier <?= View::e($facture['dossier_reference']) ?><?= !empty($facture['date_emission']) ? ' · émise le ' . date('d/m/Y', strtotime($facture['date_emission'])) : '' ?></div>
    <div class="big" style="margin-top:16px"><?= $fm($facture['montant'], $facture['devise']) ?></div>
    <?php if ($facture['statut'] === 'emise' && !empty($facture['date_echeance'])): ?><div class="mut" style="margin-top:6px">À régler avant le <?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></div><?php endif; ?>
    <div class="mut" style="margin-top:12px">Émise par <?= View::e($emetteur) ?> pour <?= View::e($client['nom']) ?>.</div>
    <div class="act"><button class="btn bs noprint" onclick="window.print()">Imprimer / enregistrer en PDF</button></div>
  </div>
  <div class="priv">Ce lien est personnel : ne le partagez pas.</div>
</div></body></html>
