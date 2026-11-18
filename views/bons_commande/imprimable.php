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
$aLiv = trim((string) ($bon['livraison_adresse'] ?? '')) !== '' ? (string) $bon['livraison_adresse'] : (string) ($bon['lieu_livraison'] ?? '');
$aFact = trim((string) ($bon['facturation_adresse'] ?? '')) !== '' ? (string) $bon['facturation_adresse'] : ($nomEmetteur . "\n" . trim((string) ($info['adresse'] ?? '')));
$droite = '<br>Date : ' . date('d/m/Y', strtotime($bon['date_emission']));
if (!empty($bon['dossier_reference'])) { $droite .= '<br>Notre dossier : ' . View::e($bon['dossier_reference']); }
$refDevis = trim((string) ($bon['ref_devis_fournisseur'] ?? ''));
if ($refDevis !== '') {
    $droite .= '<br><strong>Votre devis n° ' . View::e($refDevis) . '</strong>' . (!empty($bon['date_devis_fournisseur']) ? ' du ' . date('d/m/Y', strtotime($bon['date_devis_fournisseur'])) : '');
}
if (!empty($bon['reference_offre'])) { $droite .= '<br>Notre réf. offre : ' . View::e($bon['reference_offre']); }
$contact = array_filter([(string) ($bon['livraison_contact_nom'] ?? ''), !empty($bon['livraison_contact_tel']) ? 'Tél. ' . $bon['livraison_contact_tel'] : '', (string) ($bon['livraison_contact_email'] ?? '')]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bon de commande <?= View::e($bon['reference']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/../documents/_style.php'; ?>
<style>.annule{background:#fee2e2;color:#991b1b;padding:8px 12px;border-radius:6px;margin-bottom:14px;font-weight:700}.num{text-align:right;white-space:nowrap}.total td{font-weight:700;border-top:2px solid var(--c);border-bottom:0}.pied2{margin-top:22px;font-size:11.5px;color:#667}.corps table th{text-align:left}</style>
</head>
<body>
<div class="bar"><button onclick="window.print()">Imprimer / enregistrer en PDF</button><a class="s" href="/index.php?r=bons-commande/<?= (int) $bon['id'] ?>">Retour</a></div>
<div class="page">
<?php $titre = 'BON DE COMMANDE ' . $bon['reference']; $droiteLignes = $droite; include __DIR__ . '/../documents/_entete.php'; ?>
  <div class="corps">
<?php if ($bon['statut'] === 'annule'): ?><div class="annule">BON ANNULÉ<?= !empty($bon['annule_motif']) ? ' — ' . View::e($bon['annule_motif']) : '' ?></div><?php endif; ?>
<?php if ($bon['statut'] === 'brouillon'): ?><div class="noprint" style="color:#92400e;margin-bottom:10px;font-size:13px">Ce bon est encore un brouillon : vérifiez-le avant de l’envoyer.</div><?php endif; ?>
    <div class="bloc">
      <?php
        $fo = $fournisseur ?? [];
        $adrF = trim((string) $bon['fournisseur_adresse']) !== '' ? (string) $bon['fournisseur_adresse'] : ($fo ? \App\Models\BonCommandeFournisseur::adresseTexte($fo) : '');
        $telF = ''; $mailF = (string) ($bon['destinataire_email'] ?? '');
        if ($fo) {
            foreach (\App\Models\Fournisseur::contacts((int) $fo['id']) as $ct) {
                if ((int) ($ct['actif'] ?? 1) === 1 && trim($ct['prenom'] . ' ' . $ct['nom']) === trim((string) $bon['destinataire_nom'])) { $telF = (string) ($ct['telephone'] ?? ''); if ($mailF === '') { $mailF = (string) ($ct['email'] ?? ''); } break; }
            }
            if ($telF === '') { $telF = (string) ($fo['telephone'] ?? ''); }
            if ($mailF === '') { $mailF = (string) ($fo['email'] ?? ''); }
        }
        $idF = $fo ? array_filter([!empty($fo['siret']) ? 'SIRET : ' . $fo['siret'] : '', !empty($fo['tva']) ? 'TVA : ' . $fo['tva'] : '']) : [];
      ?>
      <div><h3>Fournisseur</h3><strong><?= View::e($bon['fournisseur_nom']) ?></strong>
        <?php if (trim($adrF) !== ''): ?><br><?= nl2br(View::e($adrF)) ?><?php endif; ?>
        <?php if ($bon['destinataire_nom'] !== ''): ?><br>À l’attention de : <?= View::e($bon['destinataire_nom']) ?><?php endif; ?>
        <?php if ($telF !== ''): ?><br>Tél. <?= View::e($telF) ?><?php endif; ?>
        <?php if ($mailF !== ''): ?><br><?= View::e($mailF) ?><?php endif; ?>
        <?php if ($idF): ?><br><span class="d"><?= View::e(implode(' · ', $idF)) ?></span><?php endif; ?></div>
      <div><h3>Conditions</h3>
        Devise : <strong><?= View::e($bon['devise']) ?></strong><br>
        <?php if ($bon['incoterm'] !== ''): ?>Incoterm : <?= View::e($bon['incoterm']) ?><br><?php endif; ?>
        <?php if ($bon['conditions_paiement'] !== ''): ?>Paiement : <?= View::e($bon['conditions_paiement']) ?><br><?php endif; ?>
        <?php if ($bon['date_livraison_souhaitee']): ?>Livraison souhaitée : <strong><?= date('d/m/Y', strtotime($bon['date_livraison_souhaitee'])) ?></strong><?php endif; ?></div>
    </div>
    <div class="bloc liv">
      <div><h3>Livraison</h3><strong><?= nl2br(View::e(trim($aLiv)) ?: 'À préciser') ?></strong>
        <?php if (!empty($bon['livraison_contact_nom'])): ?><br>Contact : <?= View::e($bon['livraison_contact_nom']) ?><?php endif; ?>
        <?php if (!empty($bon['livraison_contact_tel'])): ?><br>Tél. <?= View::e($bon['livraison_contact_tel']) ?><?php endif; ?>
        <?php if (!empty($bon['livraison_contact_email'])): ?><br><?= View::e($bon['livraison_contact_email']) ?><?php endif; ?>
        <?php if (!empty($bon['livraison_horaires'])): ?><br>Réception : <?= View::e($bon['livraison_horaires']) ?><?php endif; ?></div>
      <div><h3>Facturation</h3><?= nl2br(View::e(trim($aFact))) ?>
        <?php if ($juridique !== ''): ?><br><span class="d"><?= View::e($juridique) ?></span><?php endif; ?></div>
    </div>
    <div class="tw">
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
    </div>
<?php if (!empty($bon['notes'])): ?><div class="sec">Instructions</div><div style="white-space:pre-wrap"><?= View::e($bon['notes']) ?></div><?php endif; ?>
    <div class="pied2">Merci de nous confirmer la réception de ce bon et la date de livraison prévue. Édité le <?= date('d/m/Y H:i') ?> par <?= View::e($emetteur) ?>.</div>
  </div>
<?php include __DIR__ . '/../documents/_pied.php'; ?>
</div>
</body>
</html>
