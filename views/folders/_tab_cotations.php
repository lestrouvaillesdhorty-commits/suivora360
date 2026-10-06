<?php
use App\Core\Auth;
use App\Core\View;
use App\Models\Commande;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\Facture;

// [réécrit 06/10, étape 3 du découpage Dossiers] Onglet Cotations client
// conforme à la maquette Cotations.dc.html : liste des versions (courante
// mise en avant, anciennes "Remplacée" conservées pour historique), actions
// d'accord/refus sur la version courante, puis carte "Accord client &
// commande" (reprend l'ancienne carte "Commande & Facturation").
//
// Versions = lignes `cotations` du dossier (migrate_v18 : version +
// cotation_precedente_id). "Envoyée le … par …" est lu dans le journal
// d'audit (Cotation::envoiInfo), pas dans de nouvelles colonnes. La ligne
// "Document" (PDF) de la maquette n'est pas reprise : pas de génération PDF
// côté serveur sur cet hébergement — la fiche cotation s'imprime depuis le
// navigateur.
$peutGerer = Auth::canGererCotations();
$badgesCot = [
    'brouillon' => 'badge-gray',
    'envoyee' => 'badge-blue',
    'acceptee' => 'badge-green',
    'refusee' => 'badge-red',
    'remplacee' => 'badge-gray',
];
$versions = $cotationVersions ?? [];
?>
<div class="info-note" style="margin-bottom:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Module de cotation existant, éléments liés à ce dossier. Une cotation n'est marquée « Envoyée » que sur action explicite (ouvrir un e-mail ou WhatsApp n'est pas une preuve d'envoi).</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
  <h2 style="margin:0">Versions</h2>
  <?php if ($peutGerer): ?>
    <?php if ($cotation): ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/cotations/nouvelle&version_de=<?= (int) $cotation['id'] ?>" class="btn btn-secondary">+ Nouvelle version</a>
    <?php else: ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/cotations/nouvelle" class="btn btn-secondary">+ Créer une cotation</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if (empty($versions)): ?>
  <div class="card"><div class="empty-state">Aucune cotation créée pour ce dossier.</div></div>
<?php endif; ?>

<?php foreach ($versions as $cv):
  $estCourante = $cotation && (int) $cv['id'] === (int) $cotation['id'];
  $envoi = ($cv['statut'] === 'brouillon') ? null : Cotation::envoiInfo((int) $cv['id']);
  $nbLignes = (int) $cv['nb_lignes'];
?>
<div class="cot-version <?= $estCourante ? 'current' : '' ?>">
  <div class="cot-version-head">
    <span class="cv-title">Version <?= (int) $cv['version'] ?> <span style="font-weight:400;color:#666;font-size:12.5px">· <a href="/index.php?r=cotations/<?= $cv['id'] ?>"><?= View::e($cv['reference']) ?></a></span></span>
    <span class="badge <?= $badgesCot[$cv['statut']] ?? 'badge-gray' ?>"><?= View::e(Cotation::STATUTS[$cv['statut']] ?? $cv['statut']) ?></span>
  </div>
  <div class="kv-row"><span class="label">Client</span><span class="value"><?= View::e($cv['client_nom']) ?></span></div>
  <div class="kv-row"><span class="label">Envoyée le</span><span class="value"><?php
    if ($envoi) {
        echo date('d/m/Y', strtotime($envoi['created_at'])) . ($envoi['utilisateur_nom'] ? ' par ' . View::e($envoi['utilisateur_nom']) : '');
    } else {
        echo $cv['statut'] === 'brouillon' ? 'Pas encore envoyée' : '—';
    }
  ?></span></div>
  <div class="kv-row"><span class="label">Lignes / Prix de vente</span><span class="value"><?= $nbLignes ?> ligne<?= $nbLignes > 1 ? 's' : '' ?> · <?= number_format((float) $cv['montant_total'], 2, ',', ' ') ?> <?= View::e($cv['devise']) ?></span></div>
  <?php if ($estCourante): ?>
    <div class="kv-row"><span class="label">Conditions</span><span class="value"><?= View::e(Demande::MODES_PAIEMENT[$cv['mode_paiement_negocie']] ?? $cv['mode_paiement_negocie']) ?: 'Non précisées' ?><?= $cv['incoterm_client'] ? ' · ' . View::e($cv['incoterm_client']) : '' ?></span></div>
    <div class="kv-row"><span class="label">Validité</span><span class="value"><?= $cv['validite_devis'] ? date('d/m/Y', strtotime($cv['validite_devis'])) : '—' ?></span></div>
    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
      <?php if ($peutGerer && in_array($cv['statut'], ['brouillon', 'envoyee'], true)): ?>
        <?php if ($cv['statut'] === 'brouillon'): ?>
          <form method="post" action="/index.php?r=cotations/<?= $cv['id'] ?>/statut">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="retour" value="dossier">
            <input type="hidden" name="statut" value="envoyee">
            <button type="submit" class="btn btn-sm">Marquer comme envoyée</button>
          </form>
        <?php else: ?>
          <form method="post" action="/index.php?r=cotations/<?= $cv['id'] ?>/statut">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="retour" value="dossier">
            <input type="hidden" name="statut" value="acceptee">
            <button type="submit" class="btn btn-sm">Enregistrer l'accord client</button>
          </form>
          <form method="post" action="/index.php?r=cotations/<?= $cv['id'] ?>/statut">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="retour" value="dossier">
            <input type="hidden" name="statut" value="refusee">
            <button type="submit" class="btn btn-sm btn-secondary">Marquer refusée</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <a href="/index.php?r=cotations/<?= $cv['id'] ?>" class="btn btn-sm btn-secondary">Voir la fiche détaillée</a>
    </div>
  <?php else: ?>
    <?php if ($cv['statut'] === 'remplacee'): ?>
      <div class="hint" style="margin-top:4px">Remplacée par une version plus récente — conservée pour historique, non modifiable. <a href="/index.php?r=cotations/<?= $cv['id'] ?>">Voir la fiche</a></div>
    <?php else: ?>
      <div class="hint" style="margin-top:4px"><a href="/index.php?r=cotations/<?= $cv['id'] ?>">Voir la fiche</a></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="card" style="margin-top:18px">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2 style="margin:0">Accord client &amp; commande</h2>
    <?php if ($commande): ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/commande" class="btn btn-sm btn-secondary">Voir le suivi</a>
    <?php endif; ?>
  </div>
  <?php if ($commande): ?>
    <div class="info-row" style="margin-top:8px"><span class="label">Référence</span><span><?= View::e($commande['reference']) ?></span></div>
    <div class="info-row"><span class="label">Étape courante</span><span><?= Commande::ETAPES_STEPS[$commande['etape']] ?? ($commande['etape'] === 'terminee' ? 'Terminée' : $commande['etape']) ?></span></div>
    <div class="info-row"><span class="label">Avancement</span><span><?= Commande::progression(Commande::steps((int) $commande['id'])) ?>%</span></div>
    <?php if (Commande::estEnRetard($commande)): ?>
      <div class="alert alert-erreur" style="margin-top:8px">À relancer (date dépassée)</div>
    <?php endif; ?>
  <?php elseif ($cotation && $cotation['statut'] === 'acceptee'): ?>
    <div class="info-row" style="margin-top:8px"><span class="label">Accord client</span><span>Enregistré sur la version <?= (int) $cotation['version'] ?> — la commande peut être créée.</span></div>
    <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/commande" style="margin-top:12px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="cotation_id" value="<?= $cotation['id'] ?>">
      <button type="submit" class="btn btn-sm">Créer la commande</button>
    </form>
  <?php elseif ($cotation && $cotation['statut'] === 'refusee'): ?>
    <div class="empty-state" style="margin-top:12px">La version <?= (int) $cotation['version'] ?> a été refusée par le client — créez une nouvelle version pour relancer la négociation.</div>
  <?php elseif ($cotation && $cotation['statut'] === 'envoyee'): ?>
    <div class="empty-state" style="margin-top:12px">Aucun accord enregistré pour l'instant — en attente de réponse du client sur la version <?= (int) $cotation['version'] ?>.</div>
  <?php elseif ($cotation): ?>
    <div class="empty-state" style="margin-top:12px">La version <?= (int) $cotation['version'] ?> n'a pas encore été envoyée au client.</div>
  <?php else: ?>
    <div class="empty-state" style="margin-top:12px">En attente d'une cotation acceptée par le client.</div>
  <?php endif; ?>
  <div class="hint">Une fois l'accord du client enregistré, la commande client peut être créée ici, sans doublon.</div>

  <?php if (!empty($factures)): ?>
    <table style="margin-top:16px">
      <thead><tr><th>Référence</th><th>Type</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($factures as $f): ?>
        <tr>
          <td><?= View::e($f['reference']) ?></td>
          <td><?= Facture::TYPES[$f['type']] ?? $f['type'] ?></td>
          <td><?= number_format((float) $f['montant'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></td>
          <td><span class="badge <?= $f['statut'] === 'payee' ? 'badge-green' : ($f['statut'] === 'annulee' ? 'badge-red' : 'badge-blue') ?>"><?= Facture::STATUTS[$f['statut']] ?? $f['statut'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
