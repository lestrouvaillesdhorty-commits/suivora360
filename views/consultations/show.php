<?php use App\Core\View; use App\Models\ConsultationFournisseur; ?>
<?php
$badges = [
    'a_envoyer' => 'badge-orange',
    'envoyee' => 'badge-blue',
    'relance' => 'badge-yellow',
    'reponse_recue' => 'badge-green',
    'sans_reponse' => 'badge-gray',
];
?>
<a href="/index.php?r=dossiers/<?= $consultation['dossier_id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($consultation['dossier_reference']) ?></a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($consultation['reference']) ?></h1>
    <div class="subtitle">Consultation <?= $consultation['statut'] === 'a_envoyer' ? 'à envoyer à' : 'envoyée à' ?> <?= View::e($consultation['fournisseur_nom']) ?></div>
  </div>
  <div>
    <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/recap" target="_blank" class="btn btn-secondary">Voir le récap</a>
    <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/recap/pdf" class="btn btn-secondary">Télécharger le PDF</a>
    <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/nouvelle" class="btn btn-secondary">Demander une offre (lien + e-mail / WhatsApp)</a>
    <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/offres/nouvelle" class="btn">+ Enregistrer une offre reçue</a>
  </div>
</div>

<?php if ($consultation['statut'] === 'a_envoyer'): ?>
<div class="alert" style="background:#fffbeb;color:#92400e;padding:12px 16px;border-radius:8px;font-size:14px;margin-top:12px">
  Cette consultation n'a pas encore été envoyée. <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/nouvelle"><strong>Préparer le récapitulatif et choisir le canal d'envoi</strong></a>.
</div>
<?php endif; ?>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Offres reçues</h2>
      <?php if (empty($offres)): ?>
        <div class="empty-state">Aucune offre reçue pour cette consultation.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Référence</th><th>Version</th><th>Montant</th><th>Incoterm négocié</th><th>Délai</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($offres as $o): ?>
            <tr>
              <td><?= View::e($o['reference']) ?></td>
              <td>v<?= (int) $o['version'] ?></td>
              <td><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?> <?= View::e($o['devise']) ?></td>
              <td><?= View::e($o['incoterm_negocie']) ?></td>
              <td><?= View::e($o['delai_livraison']) ?></td>
              <td><span class="badge <?= $o['statut'] === 'retenue' ? 'badge-green' : ($o['statut'] === 'rejetee' ? 'badge-red' : ($o['statut'] === 'remplacee' ? 'badge-gray' : 'badge-blue')) ?>"><?= \App\Models\Offre::STATUTS[$o['statut']] ?? $o['statut'] ?></span></td>
              <td>
                <?php if ($o['statut'] !== 'remplacee'): ?>
                  <?php if (\App\Core\Auth::canWrite() && $o['statut'] !== 'retenue'): ?><a href="/index.php?r=offres/<?= (int) $o['id'] ?>/modifier" class="btn btn-sm btn-secondary">Modifier</a><?php endif; ?>
                  <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/offres/nouvelle&version_de=<?= $o['id'] ?>" class="btn btn-sm btn-secondary">+ Nouvelle version</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <?php
        $ligneDossier = \App\Models\Dossier::find((int) $consultation['dossier_id']);
        $artsDemande = !empty($ligneDossier['demande_id']) ? \App\Models\DemandeArticle::forDemande((int) $ligneDossier['demande_id']) : [];
        $artsConcernes = ConsultationFournisseur::filtrerArticles($consultation, $artsDemande);
      ?>
      <?php if ($artsDemande): ?>
        <h2>Articles concernés (<?= count($artsConcernes) ?> sur <?= count($artsDemande) ?>)</h2>
        <ul style="margin:0 0 14px 18px;padding:0;font-size:14px">
          <?php foreach ($artsConcernes as $a): ?><li><?= View::e(trim(rtrim(rtrim(number_format((float) $a['quantite'], 2, ',', ''), '0'), ',') . ' ' . ($a['unite'] ?? '') . ' ' . $a['designation'])) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <h2>Besoins communiqués</h2>
      <div style="white-space:pre-wrap;font-size:14px"><?= View::e($consultation['articles_demandes']) ?: '—' ?></div>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Suivi</h2>
      <div class="info-row"><span class="label">Statut</span><span><span class="badge <?= $badges[$consultation['statut']] ?? 'badge-gray' ?>"><?= ConsultationFournisseur::STATUTS[$consultation['statut']] ?? $consultation['statut'] ?></span></span></div>
      <div class="info-row"><span class="label">Date d'envoi</span><span><?= $consultation['date_envoi'] ? date('d/m/Y', strtotime($consultation['date_envoi'])) : '—' ?></span></div>
      <div class="info-row"><span class="label">Canal d'envoi</span><span><?= !empty($consultation['canal_envoi']) ? View::e(ConsultationFournisseur::CANAUX[$consultation['canal_envoi']] ?? $consultation['canal_envoi']) : '—' ?></span></div>
      <div class="info-row"><span class="label">Relance</span><span><?= $consultation['date_relance'] ? date('d/m/Y', strtotime($consultation['date_relance'])) : '—' ?></span></div>
      <div class="info-row"><span class="label">Fournisseur</span><span><?= View::e($consultation['fournisseur_nom']) ?></span></div>

      <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/statut" style="margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label>Mettre à jour le statut</label>
        <select name="statut">
          <?php foreach (ConsultationFournisseur::STATUTS as $code => $label): ?>
            <option value="<?= $code ?>" <?= $consultation['statut'] === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (ConsultationFournisseur::colonneCanal()): ?>
        <label style="margin-top:10px">Canal utilisé (si envoyée)</label>
        <select name="canal_envoi">
          <option value="">—</option>
          <?php foreach (ConsultationFournisseur::CANAUX as $code => $label): ?>
            <option value="<?= $code ?>" <?= ($consultation['canal_envoi'] ?? '') === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label style="margin-top:10px">Date de relance</label>
        <input type="date" name="date_relance" value="<?= View::e($consultation['date_relance'] ?? '') ?>">
        <button type="submit" class="btn btn-sm" style="margin-top:10px">Enregistrer</button>
      </form>
    </div>

    <div class="card">
      <h2>Notes internes</h2>
      <div style="white-space:pre-wrap;font-size:14px"><?= View::e($consultation['notes']) ?: '—' ?></div>
    </div>
  </div>
</div>
