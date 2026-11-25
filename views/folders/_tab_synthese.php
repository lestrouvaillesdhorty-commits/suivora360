<?php
use App\Core\View;
use App\Models\Cotation;
use App\Models\Dossier;

// Synthèse du dossier (v257) : alerte de retard, chiffres clés, parcours par étape, notes et documents.
// L'action à faire est affichée une seule fois, dans le bandeau « Prochaine action » en haut de la fiche.
$etapes = \App\Models\Dossier::ETAPES;
$idxEtape = max(0, (int) array_search($dossier['etape'], $etapes, true));
$base = '/index.php?r=dossiers/' . (int) $dossier['id'];
$aujourdhui = date('Y-m-d');
$actif = ($dossier['statut'] ?? '') === 'actif' && $dossier['etape'] !== 'cloture';
$jours = function (?string $d) use ($aujourdhui) { return $d && $d < $aujourdhui ? (int) floor((strtotime($aujourdhui) - strtotime($d)) / 86400) : 0; };
$retardInterne = $actif ? $jours($dossier['echeance'] ?? null) : 0;
$retardClient = $actif ? $jours($demande['date_souhaitee_client'] ?? null) : 0;
$fmtMontant = function ($m, $dev) { return $m === null || $m === '' ? '—' : number_format((float) $m, 2, ',', ' ') . ' ' . View::e((string) $dev); };
$achat = $offreRetenue ? $fmtMontant($offreRetenue['montant_total'], $offreRetenue['devise']) : '—';
$transp = ($offreRetenue && (float) ($offreRetenue['transport_montant'] ?? 0) > 0) ? $fmtMontant($offreRetenue['transport_montant'], $offreRetenue['devise']) : '—';
$vente = $cotation ? $fmtMontant($cotation['montant_ht'] ?? $cotation['montant_total'], $cotation['devise']) : '—';
$marge = ($cotation && $cotation['marge_montant'] !== null) ? $fmtMontant($cotation['marge_montant'], $cotation['devise']) . ($cotation['marge_pourcentage'] !== null ? ' (' . rtrim(rtrim(number_format((float) $cotation['marge_pourcentage'], 1, ',', ''), '0'), ',') . ' %)' : '') : '—';

$boutonBon = null;
if (!in_array($dossier['etape'], ['qualifie', 'sourcing', 'cloture'], true) && $dossier['statut'] === 'actif' && \App\Core\Auth::canWrite() && \App\Core\Auth::canGererBonCommandeFournisseur() && \App\Models\BonCommandeFournisseur::schemaPret()) {
    $offRet = \App\Models\Offre::retenueForDossier((int) $dossier['id']);
    if ($offRet) {
        $bonExist = \App\Models\BonCommandeFournisseur::actifPourOffre((int) $offRet['id']);
        $boutonBon = $bonExist
            ? ['Voir le bon ' . $bonExist['reference'], '/index.php?r=bons-commande/' . (int) $bonExist['id']]
            : ['Créer le bon de commande fournisseur', '/index.php?r=fournisseurs/' . (int) $offRet['fournisseur_id'] . '/bons-commande/nouveau&offre_id=' . (int) $offRet['id']];
    }
}
$dest = trim(($demande['lieu_livraison'] ?? '') . (($demande['lieu_livraison'] ?? '') && ($demande['destination_pays'] ?? '') ? ' — ' : '') . ($demande['destination_pays'] ?? ''));
$parcours = [
    ['Besoin', $demande ? 'Demande ' . View::e($demande['reference']) . ' · ' . count($articles) . ' article(s)' : 'Aucune demande d\'origine liée', 'Voir le besoin', $base . '&onglet=besoin'],
    [ucfirst($libelleFournisseurMin) . ' et offre', $offreRetenue ? View::e($offreRetenue['fournisseur_nom']) . ' retenu' : (!empty($offres) ? 'Comparaison des offres en cours' : 'En attente d\'offres') . ' · ' . count($offres) . ' offre(s) reçue(s) sur ' . count($consultations) . ' consultation(s)', 'Achats et offres', $base . '&onglet=achats'],
    ['Cotation client', $cotation ? View::e($cotation['reference']) . ' · ' . $fmtMontant($cotation['montant_total'], $cotation['devise']) . ' · ' . (Cotation::STATUTS[$cotation['statut']] ?? $cotation['statut']) : 'À créer : aucune cotation pour l\'instant', 'Cotations client', $base . '&onglet=cotations'],
    ['Commande', $idxEtape >= 3 ? 'Suivre la commande jusqu\'à la livraison' : 'Possible après l\'accord du client', null, null],
    [$typeDossier === 'prestation_entreprise' ? 'Intervention' : 'Livraison', $typeDossier === 'prestation_entreprise' ? View::e(\App\Models\Dossier::TYPES_PRESTATION_LABELS[$dossier['type_prestation'] ?? ''] ?? 'À préciser') : (($dest !== '' ? View::e($dest) : 'Destination à préciser') . (!empty($demande['date_souhaitee_client']) ? ' · souhaitée le ' . date('d/m/Y', strtotime($demande['date_souhaitee_client'])) : '')), 'Exécution', $base . '&onglet=execution'],
];
?>
<div class="sy">
  <?php if ($retardInterne > 0 || $retardClient > 0): ?>
  <div class="sy-alerte"><span aria-hidden="true" style="font-size:18px">&#9888;</span><span><b>En retard de <?= max($retardInterne, $retardClient) ?> jour<?= max($retardInterne, $retardClient) > 1 ? 's' : '' ?></b>
    <?php if ($retardInterne > 0): ?> · échéance interne le <?= date('d/m/Y', strtotime($dossier['echeance'])) ?><?php endif; ?>
    <?php if ($retardClient > 0): ?> · échéance client le <?= date('d/m/Y', strtotime($demande['date_souhaitee_client'])) ?><?php endif; ?></span></div>
  <?php endif; ?>

  <div class="sy-kpis">
    <div class="sy-k"><span>Coût d'achat</span><b><?= $achat ?></b></div>
    <div class="sy-k"><span>Transport</span><b><?= $transp ?></b></div>
    <div class="sy-k"><span>Prix de vente HT</span><b><?= $vente ?></b></div>
    <div class="sy-k"><span>Marge</span><b><?= $marge ?></b></div>
  </div>

  <div class="card">
    <h2>Parcours du dossier</h2>
    <?php foreach ($parcours as $i => [$titre, $texte, $lienLib, $lienUrl]): $etat = $i < $idxEtape ? 'ok' : ($i === $idxEtape ? 'now' : 'fut'); ?>
    <div class="sy-st <?= $etat ?>">
      <span class="sy-d"><?= $etat === 'ok' ? '✓' : ($i + 1) ?></span>
      <div class="sy-t"><div class="sy-n"><?= View::e($titre) ?></div><div class="sy-s"><?= $texte ?></div></div>
      <?php if ($i === 3 && $boutonBon): ?><a href="<?= View::e($boutonBon[1]) ?>" class="btn btn-sm"><?= View::e($boutonBon[0]) ?></a>
      <?php elseif ($lienUrl): ?><a href="<?= View::e($lienUrl) ?>" class="card-link-hint"><?= View::e($lienLib) ?> →</a><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="fiche-grid" style="margin-top:18px">
    <div class="card">
      <h2>Notes internes</h2>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/notes">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <textarea name="notes" rows="3" placeholder="Notes internes, points bloquants…"><?= View::e($dossier['notes']) ?></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;gap:8px;flex-wrap:wrap">
          <button type="submit" class="btn btn-sm">Enregistrer</button>
          <a href="<?= $base ?>&onglet=suivi" class="card-link-hint">Notes et échanges →</a>
        </div>
      </form>
      <div style="font-size:12px;color:#999;margin-top:8px">Dernière modification : <?= date('d/m/Y H:i', strtotime($dossier['updated_at'])) ?></div>
    </div>
    <div class="card">
      <h2>Documents récents</h2>
      <?php if (empty($piecesJointes)): ?>
        <div class="empty-state">Aucun document pour le moment.</div>
      <?php else: foreach (array_slice($piecesJointes, 0, 3) as $p): ?>
        <div class="sy-doc"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5B6B78" stroke-width="1.8" aria-hidden="true" style="flex:none;margin-top:2px"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg><div><div><?= View::e($p['nom_original']) ?></div><div class="sy-s"><?= View::e(\App\Models\DossierPieceJointe::CATEGORIES[$p['categorie'] ?? 'autre'] ?? 'Autres documents') ?> · <?= date('d/m/Y', strtotime($p['created_at'])) ?></div></div></div>
      <?php endforeach; endif; ?>
      <div style="margin-top:12px"><a href="<?= $base ?>&onglet=documents" class="card-link-hint">Voir tous les documents →</a></div>
    </div>
  </div>
</div>
