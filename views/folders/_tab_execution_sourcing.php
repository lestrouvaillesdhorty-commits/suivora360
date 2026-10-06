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
?>
<div class="card">
  <h2>Livraisons</h2>
  <?php if (!$commande): ?>
    <div class="empty-state">Aucune livraison enregistrée — la commande client doit d'abord être créée depuis l'onglet Cotations client.</div>
  <?php else: ?>
    <div class="kv-row"><span class="label">Étape Livraison</span><span class="value"><span class="badge <?= $livre ? 'badge-green' : (($liv['statut'] ?? '') === 'en_cours' ? 'badge-blue' : 'badge-gray') ?>"><?= View::e(Commande::libelleStatutEtape('livraison', $liv['statut'] ?? 'a_faire')) ?></span></span></div>
    <div class="kv-row"><span class="label">Prévue / réelle</span><span class="value"><?= !empty($liv['date_prevue']) ? date('d/m/Y', strtotime($liv['date_prevue'])) : '—' ?> · <?= !empty($liv['date_reelle']) ? date('d/m/Y', strtotime($liv['date_reelle'])) : 'en attente' ?></span></div>
    <?php if (!empty($liv['notes'])): ?><div class="kv-row"><span class="label">Note</span><span class="value"><?= View::e($liv['notes']) ?></span></div><?php endif; ?>
    <div class="hint"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/commande">Mettre à jour dans le suivi de la commande</a></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Conditions de clôture du dossier</h2>
  <div class="kv-row"><span class="label">Livraison effectuée</span><span class="value"><?= $cond($livre) ?></span></div>
  <div class="kv-row"><span class="label">Facturation complète</span><span class="value"><?= $cond($factOk) ?></span></div>
  <div class="kv-row"><span class="label">Aucune réserve ouverte</span><span class="value"><span class="badge badge-gray">Non suivi</span></span></div>
  <div class="hint">Livraison : étape de la commande terminée. Facturation : au moins une facture et toutes payées. Les réserves n'ont pas encore de registre dédié.</div>
</div>
