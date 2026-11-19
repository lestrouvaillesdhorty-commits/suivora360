<?php
use App\Core\View;
use App\Models\BonSuivi;
/** Lien fournisseur — détail d'une commande. Variables : $lien, $fournisseur, $bon, $lignes, $docs, $onglet, $emetteur, $base, $nbCommandes, $csrfToken, $flashPortail. */
$etape = BonSuivi::etape($bon); $b = $bon;
$fm = fn($m) => number_format((float) $m, 2, ',', ' ');
$qte = fn($q) => $q === null || $q === '' ? '' : rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
$d = fn($x) => date('d/m/Y', strtotime($x));
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Bon <?= View::e($bon['reference']) ?> — <?= View::e($fournisseur['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/../portail/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1>Bon <?= View::e($bon['reference']) ?></h1><div class="sub"><?= !empty($bon['dossier_reference']) ? 'Dossier ' . View::e($bon['dossier_reference']) . ' · ' : '' ?>émis le <?= $d($bon['date_emission']) ?></div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <?php include __DIR__ . '/_onglets.php'; ?>
  <div class="card"><h2>Articles commandés</h2>
    <?php foreach ($lignes as $l): ?><div class="row"><div><div class="ref"><?= View::e($l['designation']) ?></div>
      <div class="mut"><?= $qte($l['quantite']) !== '' ? $qte($l['quantite']) . ' ' . View::e($l['unite']) : '' ?><?= $l['prix_unitaire'] !== null ? ' × ' . $fm($l['prix_unitaire']) . ' ' . View::e($bon['devise']) : '' ?></div></div>
      <div><b><?= $l['montant'] !== null ? $fm($l['montant']) . ' ' . View::e($bon['devise']) : '' ?></b></div></div><?php endforeach; ?>
    <div class="row"><div><b>Total</b></div><div class="big"><?= $fm($bon['montant_total']) ?> <?= View::e($bon['devise']) ?></div></div>
    <?php if (!empty($bon['date_livraison_souhaitee'])): ?><div class="mut">Livraison souhaitée le <?= $d($bon['date_livraison_souhaitee']) ?></div><?php endif; ?>
    <?php $liv = !empty($bon['livraison_adresse']) ? $bon['livraison_adresse'] : ($bon['lieu_livraison'] ?? ''); if ($liv !== ''): ?><div class="mut" style="white-space:pre-line">Livraison : <?= View::e($liv) ?><?= !empty($bon['incoterm']) ? ' (' . View::e($bon['incoterm']) . ')' : '' ?></div><?php endif; ?>
    <?php if (!empty($bon['notes'])): ?><div style="margin-top:8px;white-space:pre-line;font-size:14px"><b>Instructions</b><br><?= View::e($bon['notes']) ?></div><?php endif; ?>
  </div>
  <div class="card"><h2>Avancement</h2><?php include __DIR__ . '/_etapes.php'; ?>
    <?php if (!empty($bon['prepare_le'])): ?><div class="mut">Préparation prévue le <?= $d($bon['prepare_le']) ?></div><?php endif; ?>
    <?php if (!empty($bon['expedie_le'])): ?><div class="mut">Expédiée le <?= $d($bon['expedie_le']) ?><?= !empty($bon['transporteur']) ? ' · ' . View::e($bon['transporteur']) : '' ?><?= !empty($bon['suivi_numero']) ? ' · suivi ' . View::e($bon['suivi_numero']) : '' ?><?= !empty($bon['arrivee_prevue']) ? ' · arrivée prévue le ' . $d($bon['arrivee_prevue']) : '' ?></div><?php endif; ?>

    <?php if ($bon['statut'] === 'envoye'): ?>
    <form method="post" action="<?= $base ?>/commandes/<?= (int) $bon['id'] ?>/confirmer"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <label>Date de préparation prévue</label><input type="date" name="prepare_le" min="<?= date('Y-m-d') ?>">
      <div class="act"><button class="btn bp" type="submit">Je confirme la commande</button></div></form>
    <details class="d" style="margin-top:12px"><summary class="mut"><u>Je ne peux pas honorer cette commande</u></summary>
      <form method="post" action="<?= $base ?>/commandes/<?= (int) $bon['id'] ?>/refuser"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label>Pourquoi ? (facultatif)</label><input type="text" name="motif" maxlength="255" placeholder="Ex. rupture de stock">
        <div class="act"><button class="btn bs" type="submit">Prévenir l'entreprise</button></div></form></details>
    <?php elseif ($etape <= 3 && $bon['statut'] === 'confirme'): ?>
    <form method="post" action="<?= $base ?>/commandes/<?= (int) $bon['id'] ?>/expedier"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <label>Date d'expédition</label><input type="date" name="expedie_le" value="<?= date('Y-m-d') ?>">
      <label>Transporteur</label><input type="text" name="transporteur" maxlength="80" placeholder="Ex. DHL">
      <label>Numéro de suivi</label><input type="text" name="suivi_numero" maxlength="80">
      <label>Arrivée prévue</label><input type="date" name="arrivee_prevue">
      <div class="act"><button class="btn bp" type="submit">Marquer comme expédiée</button></div></form>
    <?php endif; ?>
  </div>
  <div class="card"><h2>Documents</h2>
    <?php foreach ($docs as $x): ?><div class="mut"><?= View::e($x['nom_original']) ?> · <?= View::e(BonSuivi::TYPES[$x['type']] ?? 'Autre') ?> · <?= $d(substr($x['created_at'], 0, 10)) ?> <span class="badge gr">Reçu</span></div><?php endforeach; ?>
    <?php if (!$docs): ?><div class="mut">Aucun document envoyé pour cette commande.</div><?php endif; ?>
    <div class="act"><a class="btn bs" href="<?= $base ?>/documents?bon=<?= (int) $bon['id'] ?>">Envoyer un document</a></div></div>
  <div class="act"><a href="<?= $base ?>/commandes">← Toutes mes commandes</a></div>
</div></body></html>
