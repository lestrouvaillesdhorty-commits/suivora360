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
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1>
      <?= View::e($demande['reference']) ?>
      <span class="badge <?= $statutInfo[1] ?>"><?= View::e($statutInfo[0]) ?></span>
      <?php if (!empty($demande['qualification_type'])): ?>
        <span class="badge badge-gray"><?= View::e(Demande::QUALIFICATION_TYPES[$demande['qualification_type']] ?? $demande['qualification_type']) ?></span>
      <?php endif; ?>
      <?php if ($demande['priorite'] === 'haute'): ?><span class="badge badge-red">Haute</span><?php endif; ?>
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
  <?php if ($demande['qualification_type'] === 'ADDITION'): ?>
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
      <div class="info-row"><span class="label">Priorité</span><span><?= $demande['priorite'] === 'haute' ? 'Haute' : 'Normale' ?></span></div>
      <div class="info-row"><span class="label">Échéance</span><span><?= $demande['echeance'] ? date('d/m/Y', strtotime($demande['echeance'])) : '—' ?></span></div>
    </div>
  </div>
</div>
