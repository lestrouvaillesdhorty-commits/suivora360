<?php use App\Core\View; use App\Models\Utilisateur; use App\Models\Demande; ?>
<a href="/index.php?r=demandes" style="font-size:13px;color:#666">&larr; Retour aux demandes</a>

<?php
$statutBadges = [
    'a_qualifier' => ['À qualifier', 'badge-yellow'],
    'en_attente_info' => ["En attente d'infos", 'badge-yellow'],
    'qualifiee' => ['Qualifiée', 'badge-blue'],
    'rattachee' => ['Rattachée', 'badge-blue'],
    'transformee' => ['Transformée en dossier', 'badge-green'],
    'rejetee' => ['Rejetée', 'badge-red'],
    'archivee' => ['Archivée', 'badge-gray'],
];
$statutInfo = $statutBadges[$demande['statut']] ?? [ucfirst($demande['statut']), 'badge-gray'];
$peutQualifier = in_array($demande['statut'], ['a_qualifier', 'en_attente_info'], true);
$peutCreerDossier = $demande['statut'] === 'qualifiee' && !$dossier;
$peutRejeter = in_array($demande['statut'], ['a_qualifier', 'en_attente_info', 'qualifiee'], true);
$echeanceEnRetard = !empty($demande['echeance']) && strtotime($demande['echeance']) < strtotime('today') && in_array($demande['statut'], ['a_qualifier', 'en_attente_info'], true);
$actionLabels = [
    'creation' => 'Demande créée',
    'qualification' => 'Qualifiée — nouvelle demande',
    'qualification_rattachement' => 'Qualifiée — rattachée à un élément existant',
    'qualification_reprise' => 'Qualifiée — reprise hors Suivora',
    'creation_dossier' => 'Dossier créé',
    'rejet_demande' => 'Demande rejetée',
    'ajout_piece_jointe' => 'Pièce jointe ajoutée',
    'suppression_piece_jointe' => 'Pièce jointe supprimée',
    'extraction_ia_articles' => 'Articles ajoutés via IA',
];
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1>
      <?= View::e($demande['reference']) ?>
      <span class="badge <?= $statutInfo[1] ?>"><?= View::e($statutInfo[0]) ?></span>
      <?php if (!empty($demande['qualification_type'])): ?>
        <span class="badge badge-gray"><?= View::e(Demande::QUALIFICATION_TYPES[$demande['qualification_type']] ?? $demande['qualification_type']) ?></span>
      <?php endif; ?>
      <?php if (($demande['priorite'] ?? 'normale') !== 'normale'): ?>
        <span class="badge <?= Demande::PRIORITE_BADGES[$demande['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$demande['priorite']] ?? ucfirst($demande['priorite']) ?></span>
      <?php endif; ?>
    </h1>
    <div class="subtitle"><?= View::e($demande['objet']) ?> — <?= View::e($filiale['nom'] ?? '') ?></div>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($peutQualifier): ?>
      <a href="/index.php?r=demandes/<?= $demande['id'] ?>/qualifier" class="btn">Qualifier la demande</a>
    <?php elseif ($peutCreerDossier): ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/creer-dossier">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn">Créer le dossier</button>
      </form>
    <?php elseif ($dossier): ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn">Voir le dossier <?= View::e($dossier['reference']) ?></a>
    <?php endif; ?>
    <?php if ($peutRejeter): ?>
      <button class="btn btn-secondary" onclick="document.getElementById('rejeter-form').style.display='block'">Rejeter</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($peutRejeter): ?>
<div class="card" id="rejeter-form" style="display:none">
  <h2>Rejeter la demande</h2>
  <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/rejeter">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label>Motif *</label>
      <textarea name="motif" rows="2" required></textarea>
    </div>
    <button type="submit" class="btn">Confirmer le rejet</button>
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejeter-form').style.display='none'">Annuler</button>
  </form>
</div>
<?php endif; ?>

<?php if (!empty($demande['qualification_type']) || $demande['statut'] === 'rejetee'): ?>
<div class="card">
  <h2>Qualification</h2>
  <?php if ($demande['qualification_type'] === 'NEW'): ?>
    <div class="info-row"><span class="label">Client</span><span><?= View::e($client['nom'] ?? 'Prospect / non enregistré') ?></span></div>
    <div class="info-row"><span class="label">Destination</span><span><?= View::e(trim(($demande['lieu_livraison'] ?? '') . (($demande['lieu_livraison'] ?? '') && ($demande['destination_pays'] ?? '') ? ' — ' : '') . ($demande['destination_pays'] ?? ''))) ?: '—' ?></span></div>
    <div class="info-row"><span class="label">Incoterm souhaité</span><span><?= View::e(Demande::INCOTERMS[$demande['incoterm_souhaite'] ?? ''] ?? '—') ?></span></div>
    <div class="info-row"><span class="label">Mode de paiement souhaité</span><span><?= View::e(Demande::MODES_PAIEMENT[$demande['mode_paiement_souhaite'] ?? ''] ?? '—') ?></span></div>
  <?php elseif ($demande['qualification_type'] === 'ADDITION'): ?>
    <div class="info-row"><span class="label">Rattachée à</span><span>
      <?php if ($linkedDemande): ?>
        <a href="/index.php?r=demandes/<?= $linkedDemande['id'] ?>">Demande <?= View::e($linkedDemande['reference']) ?></a>
      <?php elseif ($linkedDossier): ?>
        <a href="/index.php?r=dossiers/<?= $linkedDossier['id'] ?>">Dossier <?= View::e($linkedDossier['reference']) ?></a>
      <?php else: ?>—<?php endif; ?>
    </span></div>
  <?php elseif ($demande['qualification_type'] === 'EXTERNAL_TAKEOVER'): ?>
    <div class="info-row"><span class="label">Étape actuelle</span><span><?= View::e(Demande::TAKEOVER_STAGES[$demande['takeover_stage']] ?? $demande['takeover_stage']) ?></span></div>
    <div class="info-row"><span class="label">Démarré le</span><span><?= $demande['original_started_at'] ? date('d/m/Y', strtotime($demande['original_started_at'])) : '—' ?></span></div>
    <div class="info-row"><span class="label">Enregistré dans Suivora le</span><span><?= $demande['registered_in_suivora_at'] ? date('d/m/Y', strtotime($demande['registered_in_suivora_at'])) : '—' ?></span></div>
    <div class="info-row"><span class="label">Origine externe</span><span><?= View::e($demande['external_source']) ?: '—' ?></span></div>
    <div class="info-row"><span class="label">Référence externe</span><span><?= View::e($demande['external_reference']) ?: '—' ?></span></div>
  <?php endif; ?>
  <?php if (!empty($demande['qualification_notes'])): ?>
    <div class="info-row"><span class="label">Notes</span><span><?= nl2br(View::e($demande['qualification_notes'])) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($demande['qualified_at'])): ?>
    <div class="info-row"><span class="label">Qualifiée le</span><span><?= date('d/m/Y H:i', strtotime($demande['qualified_at'])) ?> par <?= View::e(Utilisateur::nameOf($demande['qualified_by'] ?? null)) ?></span></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Demande et message original</h2>
      <div class="info-row"><span class="label">Objet</span><span><?= View::e($demande['objet']) ?></span></div>
      <div class="info-row"><span class="label">Message</span><span><?= nl2br(View::e($demande['message'])) ?></span></div>
      <div class="info-row"><span class="label">Canal</span><span><?= View::e($demande['canal']) ?></span></div>
      <div class="info-row"><span class="label">Reçue le</span><span><?= $demande['recue_le'] ? date('d/m/Y', strtotime($demande['recue_le'])) : '—' ?></span></div>
      <?php if (!empty(trim($demande['message'] ?? ''))): ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/extraction-ia" style="margin-top:12px;padding-top:12px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-sm btn-secondary">Extraire les articles avec l'IA</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Pièces jointes</h2>
      <?php if (empty($piecesJointes)): ?>
        <div class="empty-state">Aucune pièce jointe.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Fichier</th><th>Taille</th><th>Ajouté le</th><th>Par</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($piecesJointes as $p): ?>
            <tr>
              <td><a href="/index.php?r=demandes/<?= $demande['id'] ?>/pieces/<?= $p['id'] ?>/telecharger"><?= View::e($p['nom_original']) ?></a></td>
              <td><?= number_format($p['taille'] / 1024, 0) ?> Ko</td>
              <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
              <td><?= View::e($p['uploaded_by_nom']) ?></td>
              <td>
                <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/pieces/<?= $p['id'] ?>/supprimer" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <button type="submit" class="btn btn-sm btn-secondary">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/pieces" enctype="multipart/form-data" style="margin-top:12px;padding-top:12px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="file" name="fichier" required>
        <button type="submit" class="btn btn-sm" style="margin-top:8px">Ajouter</button>
      </form>
    </div>

    <div class="card">
      <h2>Expéditeur</h2>
      <div class="info-row"><span class="label">Nom</span><span><?= View::e($demande['expediteur_nom']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Entreprise</span><span><?= View::e($demande['expediteur_entreprise']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">E-mail</span><span><?= View::e($demande['expediteur_email']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Téléphone</span><span><?= View::e($demande['expediteur_telephone']) ?: '—' ?></span></div>
    </div>

    <div class="card">
      <h2>Articles</h2>
      <?php if (empty($articles)): ?>
        <div class="empty-state">Aucun article pour le moment.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Référence</th><th>Marque</th></tr></thead>
          <tbody>
          <?php foreach ($articles as $a): ?>
            <tr>
              <td><?= View::e($a['designation']) ?></td>
              <td><?= View::e((string) $a['quantite']) ?></td>
              <td><?= View::e($a['unite']) ?></td>
              <td><?= View::e($a['reference']) ?></td>
              <td><?= View::e($a['marque']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Suivi</h2>
      <div class="info-row"><span class="label">Activité</span><span><?= View::e($demande['activite']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Responsable</span><span><?= View::e(Utilisateur::nameOf($demande['responsable_id'])) ?></span></div>
      <div class="info-row"><span class="label">Urgence</span><span><?= Demande::PRIORITES[$demande['priorite']] ?? ucfirst($demande['priorite']) ?></span></div>
      <div class="info-row">
        <span class="label">Échéance</span>
        <span>
          <?= $demande['echeance'] ? date('d/m/Y', strtotime($demande['echeance'])) : '—' ?>
          <?php if ($echeanceEnRetard): ?> <span class="badge badge-red">En retard</span><?php endif; ?>
        </span>
      </div>
    </div>

    <?php if (!empty($historique)): ?>
    <div class="card">
      <h2>Historique</h2>
      <?php foreach ($historique as $h): ?>
        <div class="info-row">
          <span class="label"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></span>
          <span><?= View::e($actionLabels[$h['action']] ?? $h['action']) ?><?php if ($h['utilisateur_nom']): ?> — <?= View::e($h['utilisateur_nom']) ?><?php endif; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
