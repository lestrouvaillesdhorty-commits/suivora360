<?php
use App\Core\View;
use App\Models\Cotation;
use App\Models\Dossier;

// [ajouté 06/10, report de la maquette Dossiers] Onglet Synthèse — vue
// d'ensemble cliquable vers les autres onglets (cahier des charges
// section 4). Les cartes résument des données déjà chargées par
// DossierController::show(), rien de nouveau n'est interrogé ici.
// "Prochaine action" est dérivée de l'étape courante (même esprit que
// DashboardController::construireActionsPrioritaires()) ; "Points
// bloquants" n'a pas de champ dédié à ce stade et réutilise les Notes
// internes plutôt que d'ajouter une colonne non demandée explicitement —
// à signaler si Marie Laure veut les distinguer.
$prochainesActions = [
    'qualifie' => 'Lancer le sourcing / consulter des ' . $libelleFournisseurMin . 's',
    'sourcing' => 'Comparer les offres reçues et choisir un ' . $libelleFournisseurMin,
    'cotation' => 'Envoyer la cotation et obtenir l\'accord du client',
    'commande' => 'Suivre la commande jusqu\'à la livraison',
    'livraison' => 'Finaliser la livraison et préparer la clôture',
    'cloture' => 'Dossier clôturé',
];
?>
<div class="fiche-grid">
  <div class="card">
    <h2>Besoin et demande d'origine</h2>
    <?php if ($demande): ?>
      <div class="kv-row"><span class="label">Origine</span><span class="value">Demande <?= View::e($demande['reference']) ?></span></div>
      <div class="kv-row"><span class="label">Message</span><span class="value"><?= View::e(mb_strimwidth($demande['message'] ?? '', 0, 160, '…')) ?: 'À préciser' ?></span></div>
      <div class="kv-row"><span class="label">Articles</span><span class="value"><?= count($articles) ?> ligne(s)</span></div>
    <?php else: ?>
      <div class="empty-state">Aucune demande d'origine liée à ce dossier.</div>
    <?php endif; ?>
    <div style="margin-top:12px"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=besoin" class="card-link-hint">Voir le besoin détaillé →</a></div>
  </div>

  <div class="card">
    <h2>État des achats</h2>
    <div class="kv-row"><span class="label"><?= View::e($libelleFournisseur) ?>s consultés</span><span class="value"><?= count($consultations) ?></span></div>
    <div class="kv-row"><span class="label">Offres reçues</span><span class="value"><?= count($offres) ?> / <?= count($consultations) ?></span></div>
    <div class="kv-row"><span class="label">Décision</span><span class="value">
      <?php if ($offreRetenue): ?>
        <span class="badge badge-green">✓ <?= View::e($offreRetenue['fournisseur_nom']) ?> retenu</span>
      <?php elseif (!empty($offres)): ?>
        <span class="badge badge-purple">Comparaison en cours</span>
      <?php else: ?>
        <span class="badge badge-blue">En attente d'offres</span>
      <?php endif; ?>
    </span></div>
    <div style="margin-top:12px"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=achats" class="card-link-hint">Ouvrir Achats et offres →</a></div>
  </div>

  <div class="card">
    <h2>Cotation et accord client</h2>
    <?php if (!$cotation): ?>
      <div class="empty-state">Aucune cotation envoyée pour l'instant.</div>
    <?php else: ?>
      <div class="kv-row"><span class="label">Référence</span><span class="value"><?= View::e($cotation['reference']) ?></span></div>
      <div class="kv-row"><span class="label">Montant</span><span class="value"><?= number_format((float) $cotation['montant_total'], 2, ',', ' ') ?> <?= View::e($cotation['devise']) ?></span></div>
      <div class="kv-row"><span class="label">Statut</span><span class="value"><?= Cotation::STATUTS[$cotation['statut']] ?? $cotation['statut'] ?></span></div>
    <?php endif; ?>
    <div style="margin-top:12px"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=cotations" class="card-link-hint">Voir l'onglet Cotations client →</a></div>
  </div>

  <div class="card">
    <h2>Prochaine action</h2>
    <div class="kv-row"><span class="label">Action</span><span class="value"><?= View::e($prochainesActions[$dossier['etape']] ?? 'À déterminer') ?></span></div>
    <div class="kv-row"><span class="label">Responsable</span><span class="value"><?= View::e(\App\Models\Utilisateur::nameOf($dossier['responsable_id'])) ?></span></div>
    <div class="kv-row"><span class="label">Échéance</span><span class="value"><?= $dossier['echeance'] ? date('d/m/Y', strtotime($dossier['echeance'])) : 'À préciser' ?></span></div>
  </div>

  <div class="card">
    <h2>Documents récents</h2>
    <?php if (empty($piecesJointes)): ?>
      <div class="empty-state">Aucun document pour le moment.</div>
    <?php else: ?>
      <?php foreach (array_slice($piecesJointes, 0, 4) as $p): ?>
        <div class="kv-row"><span class="label"><?= View::e($p['nom_original']) ?></span><span class="value"><?= date('d/m/Y', strtotime($p['created_at'])) ?></span></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <div style="margin-top:12px"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=documents" class="card-link-hint">Voir tous les documents →</a></div>
  </div>

  <div class="card">
    <h2><?= $typeDossier === 'prestation_entreprise' ? 'Intervention' : 'Livraison' ?></h2>
    <?php if ($typeDossier === 'prestation_entreprise'): ?>
      <div class="kv-row"><span class="label">Type de prestation</span><span class="value"><?= View::e(Dossier::TYPES_PRESTATION_LABELS[$dossier['type_prestation'] ?? ''] ?? 'À préciser') ?></span></div>
      <div class="kv-row"><span class="label">Visite terrain</span><span class="value"><?= empty($dossier['visite_terrain_necessaire']) ? 'Non nécessaire' : 'Nécessaire' ?></span></div>
    <?php else: ?>
      <div class="kv-row"><span class="label">Destination</span><span class="value"><?= View::e(trim(($demande['lieu_livraison'] ?? '') . (($demande['lieu_livraison'] ?? '') && ($demande['destination_pays'] ?? '') ? ' — ' : '') . ($demande['destination_pays'] ?? ''))) ?: 'À préciser' ?></span></div>
      <div class="kv-row"><span class="label">Date souhaitée (client)</span><span class="value"><?= !empty($demande['date_souhaitee_client']) ? date('d/m/Y', strtotime($demande['date_souhaitee_client'])) : 'À préciser' ?></span></div>
    <?php endif; ?>
    <div style="margin-top:12px"><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=execution" class="card-link-hint">Voir l'onglet Exécution →</a></div>
  </div>

  <div class="card" style="grid-column:1/-1">
    <h2>Notes internes</h2>
    <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/notes">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <textarea name="notes" rows="4" placeholder="Notes internes, points bloquants..."><?= View::e($dossier['notes']) ?></textarea>
      <button type="submit" class="btn btn-sm" style="margin-top:10px">Enregistrer</button>
    </form>
    <div style="font-size:12px;color:#999;margin-top:10px">Dernière modification : <?= date('d/m/Y H:i', strtotime($dossier['updated_at'])) ?></div>
  </div>
</div>
