<?php
use App\Core\View;
use App\Models\Commande;

// [ajouté 06/10, étape 6 du découpage Dossiers] Onglet Exécution, déclinaison
// Achat / Sourcing (maquette ExecutionSourcing.dc.html), sans nouvelle table :
// carte "Livraisons" et "Conditions de clôture" dérivées de la commande, de
// ses étapes et des factures. NON repris : le tableau "Commande fournisseur &
// réception" (Commandé / Reçu / Livré / Restant par ligne) — il suppose un
// suivi des quantités reçues et livrées par ligne qui n'existe pas en base
// (voir doc projet, étape 6).
$stepsS = $commande ? Commande::steps((int) $commande['id']) : [];
$stepS = [];
foreach ($stepsS as $st) { $stepS[$st['libelle']] = $st; }
$liv = $stepS['livraison'] ?? null;
$livre = $liv && $liv['statut'] === 'termine';
$facts = $factures ?? [];
$factOk = !empty($facts) && count(array_filter($facts, fn($f) => $f['statut'] !== 'annulee' && $f['statut'] !== 'payee')) === 0;
$cond = function (bool $ok) { return '<span class="badge ' . ($ok ? 'badge-green' : 'badge-gray') . '">' . ($ok ? 'Atteint' : 'Non atteint') . '</span>'; };
$etapesX = \App\Models\Dossier::ETAPES;
$idxX = max(0, (int) array_search($dossier['etape'], $etapesX, true));
$baseX = '/index.php?r=dossiers/' . (int) $dossier['id'];
$fmtX = function ($m, $d) { return number_format((float) $m, 2, ',', ' ') . ' ' . View::e((string) $d); };
$peutEcrireX = \App\Core\Auth::canWrite();
// Une étape : [rang, titre, résumé, condition d'ouverture si à venir]
$lignes = [
    [0, 'Qualifié', $demande ? 'Demande ' . View::e($demande['reference']) . ' · ' . count($articles ?? []) . ' article(s)' : '', null],
    [1, ucfirst($libelleFournisseurMin ?? 'fournisseur') . ' et offre', $offreRetenue ? View::e($offreRetenue['fournisseur_nom']) . ' retenu' : 'Offres en cours de comparaison', null],
    [2, 'Cotation client', $cotation ? View::e($cotation['reference']) . ' · ' . $fmtX($cotation['montant_total'], $cotation['devise']) : 'À créer', null],
    [3, 'Commande', $commande ? 'Commande client en cours' : '', 'Possible après l\'accord du client sur la cotation'],
    [4, 'Livraison', $livre ? 'Livrée' : '', 'Possible après la commande client'],
    [5, 'Clôture', '', 'Livraison effectuée et factures payées'],
];
?>
<?php ob_start(); ?>
  <?php try { include __DIR__ . '/_card_expedition.php'; } catch (\Throwable $eExp) { } ?>
<?php $contenu[2] = ob_get_clean(); ?>
<?php ob_start(); ?>
  <?php if ($commande): ?><a href="<?= $baseX ?>/commande" class="btn btn-sm">Ouvrir le suivi de la commande</a><?php else: ?><div class="empty-state">La commande client se crée depuis l'onglet Cotations client, une fois la cotation acceptée.</div><?php endif; ?>
<?php $contenu[3] = ob_get_clean(); ?>
<?php ob_start(); ?>
  <?php if (!$commande): ?>
    <div class="empty-state">Aucune livraison enregistrée : la commande client doit d'abord être créée depuis l'onglet Cotations client.</div>
  <?php else: ?>
    <div class="kv-row"><span class="label">Étape Livraison</span><span class="value"><span class="badge <?= $livre ? 'badge-green' : (($liv['statut'] ?? '') === 'en_cours' ? 'badge-blue' : 'badge-gray') ?>"><?= View::e(Commande::libelleStatutEtape('livraison', $liv['statut'] ?? 'a_faire')) ?></span></span></div>
    <div class="kv-row"><span class="label">Prévue / réelle</span><span class="value"><?= !empty($liv['date_prevue']) ? date('d/m/Y', strtotime($liv['date_prevue'])) : '—' ?> · <?= !empty($liv['date_reelle']) ? date('d/m/Y', strtotime($liv['date_reelle'])) : 'en attente' ?></span></div>
    <?php if (!empty($liv['notes'])): ?><div class="kv-row"><span class="label">Note</span><span class="value"><?= View::e($liv['notes']) ?></span></div><?php endif; ?>
    <div class="hint"><a href="<?= $baseX ?>/commande">Mettre à jour dans le suivi de la commande</a></div>
  <?php endif; ?>
<?php $contenu[4] = ob_get_clean(); ?>
<?php ob_start(); ?>
  <div class="ex-pills"><span>Livraison <?= $cond($livre) ?></span><span>Facturation <?= $cond($factOk) ?></span><span>Réserves <span class="badge badge-gray">Non suivi</span></span></div>
  <div class="hint">Livraison : étape de la commande terminée. Facturation : au moins une facture et toutes payées. Les réserves n'ont pas encore de registre dédié.</div>
<?php $contenu[5] = ob_get_clean(); ?>
<?php if (\App\Models\DossierTransport::disponible() && $offreRetenue): ?><div class="card" id="transport"><?php include __DIR__ . '/_card_transport_transitaire.php'; ?></div><?php endif; ?>
<div class="ex">
<?php foreach ($lignes as [$rang, $titre, $resume, $condition]):
    $etat = $rang < $idxX ? 'ok' : ($rang === $idxX ? 'now' : 'fut'); ?>
  <?php if ($etat === 'fut'): ?>
    <div class="ex-et fut"><div class="ex-sum"><span class="sy-d"><?= $rang + 1 ?></span><div class="sy-t"><div class="sy-n"><?= View::e($titre) ?></div><div class="sy-s"><?= View::e($condition ?? '') ?></div></div></div></div>
  <?php elseif (!isset($contenu[$rang])): ?>
    <div class="ex-et <?= $etat ?>"><div class="ex-sum"><span class="sy-d">✓</span><div class="sy-t"><div class="sy-n"><?= View::e($titre) ?></div><div class="sy-s"><?= $resume ?></div></div></div></div>
  <?php else: ?>
    <details class="ex-et <?= $etat ?>" <?= $etat === 'now' ? 'open' : '' ?>><summary class="ex-sum"><span class="sy-d"><?= $etat === 'ok' ? '✓' : $rang + 1 ?></span><div class="sy-t"><div class="sy-n"><?= View::e($titre) ?></div><div class="sy-s"><?= $resume ?></div></div><span class="ex-chev" aria-hidden="true">▾</span></summary>
      <div class="ex-body"><?= $contenu[$rang] ?></div></details>
  <?php endif; ?>
<?php endforeach; ?>
</div>
