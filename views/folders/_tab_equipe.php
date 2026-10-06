<?php
use App\Core\View;
use App\Models\ConsultationFournisseur;
use App\Models\Cotation;
use App\Models\Utilisateur;

// [réécrit 06/10, étape 4 du découpage Dossiers] Onglet Équipe et historique
// conforme à la maquette Equipe.dc.html : tableau "Équipe du dossier"
// (responsable, collaborateurs assignés avec leur rôle, fournisseurs
// consultés), formulaire d'affectation, puis frise chronologique.
//
// $actionLabelsDossier vit ici car l'historique est le seul endroit qui
// s'en sert. Le retrait d'un collaborateur supprime son affectation (la
// trace reste dans l'historique via l'audit) : pas de statut "Terminée"
// pour les collaborateurs, seulement pour les fournisseurs consultés.
$actionLabelsDossier = [
    'creation_dossier' => 'Dossier créé',
    'maj_etape_dossier' => 'Étape mise à jour',
    'maj_prestation_dossier' => 'Informations de prestation mises à jour',
    'maj_budget_dossier' => 'Budget prévisionnel/réalisé mis à jour',
    'ajout_piece_jointe' => 'Document ajouté',
    'suppression_piece_jointe' => 'Document supprimé',
    'classement_piece_jointe' => 'Document classé',
    'creation_consultation' => 'Consultation fournisseur envoyée',
    'creation_offre' => 'Offre fournisseur enregistrée',
    'revision_offre' => 'Nouvelle version d\'une offre enregistrée',
    'offre_retenue' => 'Offre retenue',
    'creation_cotation' => 'Cotation créée',
    'revision_cotation' => 'Nouvelle version de la cotation créée',
    'changement_statut_cotation' => 'Statut de la cotation changé',
    'creation_commande' => 'Commande créée',
    'maj_etape_commande' => 'Étape de la commande mise à jour',
    'creation_facture' => 'Facture créée',
    'changement_statut_facture' => 'Statut de la facture changé',
    'collaborateur_assigne' => 'Collaborateur assigné',
    'collaborateur_retire' => 'Collaborateur retiré',
];
$initiales = function (?string $nom): string {
    $mots = preg_split('/\s+/', trim((string) $nom)) ?: [];
    $i = '';
    foreach (array_slice($mots, 0, 2) as $m) {
        $i .= mb_strtoupper(mb_substr($m, 0, 1));
    }
    return $i !== '' ? $i : '?';
};
$responsableNom = Utilisateur::nameOf($dossier['responsable_id'] ?? null);
$fournisseurRetenuId = !empty($offreRetenue['fournisseur_id']) ? (int) $offreRetenue['fournisseur_id'] : null;
?>
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:20px 22px 0"><h2 style="margin:0">Équipe du dossier</h2></div>
  <div class="table-scroll">
  <table class="dtable">
    <thead><tr><th>Collaborateur</th><th>Rôle dans le dossier</th><th>Affectation</th><th>Tâches / contributions</th></tr></thead>
    <tbody>
      <tr>
        <td><span class="avatar-sm" style="background:#5B4FE5"><?= View::e($initiales($responsableNom)) ?></span><?= View::e($responsableNom ?: 'Non assigné') ?></td>
        <td>Responsable du dossier</td>
        <td><span class="badge badge-green">Active</span></td>
        <td>Pilotage du dossier</td>
      </tr>
      <?php foreach ($collaborateurs as $c): ?>
      <tr>
        <td><span class="avatar-sm" style="background:#3B82F6"><?= View::e($initiales($c['utilisateur_nom'])) ?></span><?= View::e($c['utilisateur_nom']) ?></td>
        <td><?= View::e($c['role'] ?? '') ?: 'Collaborateur' ?></td>
        <td><span class="badge badge-green">Active</span></td>
        <td>
          Suivi du fournisseur <?= View::e($c['fournisseur_nom']) ?>
          <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/collaborateurs/<?= $c['id'] ?>/retirer" style="display:inline;margin-left:8px">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm btn-secondary">Retirer</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php foreach ($consultations as $cf):
        $termine = in_array($cf['statut'], ['reponse_recue', 'sans_reponse'], true);
        $retenu = $fournisseurRetenuId !== null && (int) $cf['fournisseur_id'] === $fournisseurRetenuId;
        $role = 'Fournisseur consulté' . ($fournisseurRetenuId !== null ? ($retenu ? ' — retenu' : ' — non retenu') : '');
      ?>
      <tr>
        <td><span class="avatar-sm" style="background:#9ca3af"><?= View::e($initiales($cf['fournisseur_nom'])) ?></span><?= View::e($cf['fournisseur_nom']) ?> (fournisseur)</td>
        <td><?= View::e($role) ?></td>
        <td><span class="badge <?= $termine ? 'badge-gray' : 'badge-green' ?>"><?= $termine ? 'Terminée' : 'Active' ?></span></td>
        <td>Consultation<?= !empty($cf['date_envoi']) ? ' envoyée le ' . date('d/m', strtotime($cf['date_envoi'])) : '' ?> — <?= View::e(mb_strtolower(ConsultationFournisseur::STATUTS[$cf['statut']] ?? $cf['statut'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <div style="padding:16px 22px 20px;border-top:1px solid var(--border)">
    <?php if (empty($fournisseursFiliale)): ?>
      <div class="empty-state">Aucun fournisseur actif dans cette filiale : créez-en un pour pouvoir y assigner un collaborateur dédié.</div>
    <?php elseif (empty($collaborateursPossibles)): ?>
      <div class="empty-state">Aucun autre utilisateur n'a accès à cette filiale pour être assigné.</div>
    <?php else: ?>
    <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/collaborateurs">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <div class="form-row" style="align-items:flex-end">
        <div class="form-group" style="flex:1.6">
          <label>Collaborateur</label>
          <select name="utilisateur_id" required>
            <option value="">— Sélectionner —</option>
            <?php foreach ($collaborateursPossibles as $u): ?>
              <option value="<?= $u['id'] ?>"><?= View::e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Fournisseur suivi</label>
          <select name="fournisseur_id" required>
            <option value="">— Choisir —</option>
            <?php foreach ($fournisseursFiliale as $fo): ?>
              <option value="<?= $fo['id'] ?>"><?= View::e($fo['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Rôle</label><input type="text" name="role" maxlength="100" placeholder="Rôle dans le dossier"></div>
        <div class="form-group" style="flex:0 0 auto"><button type="submit" class="btn">+ Affecter</button></div>
      </div>
      <div class="hint">Le collaborateur est notifié (cloche + e-mail) dès l'affectation, et retiré automatiquement si son fournisseur n'est finalement pas retenu. L'historique n'est jamais supprimé.</div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($historique)): ?>
<div class="card">
  <h2>Historique</h2>
  <div class="dos-timeline">
    <?php foreach (array_reverse($historique) as $h):
      $titre = $actionLabelsDossier[$h['action']] ?? $h['action'];
      if ($h['action'] === 'changement_statut_cotation' && !empty($h['details']) && isset(Cotation::STATUTS[$h['details']])) {
          $titre = 'Cotation marquée « ' . Cotation::STATUTS[$h['details']] . ' »';
      }
    ?>
    <div class="tl-item">
      <div class="tl-date"><?= date('d/m/Y — H:i', strtotime($h['created_at'])) ?></div>
      <div class="tl-title"><?= View::e($titre) ?></div>
      <?php if (!empty($h['utilisateur_nom'])): ?><div class="tl-author"><?= View::e($h['utilisateur_nom']) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
