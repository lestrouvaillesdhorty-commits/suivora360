<?php
use App\Core\View;
use App\Models\ConsultationFournisseur;
use App\Models\ConsultationPartage;

// [ajouté 06/10, étape 2 du découpage Dossiers] Sous-onglet "Consultations"
// — reprend la liste déjà chargée par DossierController::show()
// ($consultations, via ConsultationFournisseur::forDossier()) sans
// requête supplémentaire. "Échéance de réponse" et "Pièces jointes"
// viennent du dernier lien de partage "Demander une offre" envoyé pour
// chaque consultation (voir ConsultationPartage) plutôt que de nouvelles
// colonnes — ces deux notions existaient déjà, non documentées avant ce
// jour (voir claude/modules-verrouilles.md).
?>
<div class="info-note"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Une consultation envoyée n'est pas une offre reçue — le statut ne passe à « Réponse reçue » que lorsqu'une offre est enregistrée en face.</div>

<div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;margin:14px 0">
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/consultations/nouvelle" class="btn">+ Nouvelle consultation</a>
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/offres/manuelle" class="btn btn-secondary">+ Saisir une offre (prix trouvé en ligne…)</a>
</div>

<?php if (empty($consultations)): ?>
  <div class="card"><div class="empty-state">Aucune consultation envoyée pour le moment.</div></div>
<?php else: ?>
  <div class="card" style="padding:0;overflow:hidden">
    <div class="table-scroll">
    <table class="dtable">
      <thead><tr><th>Fournisseur</th><th>Besoin envoyé</th><th>Date d'envoi</th><th>Échéance de réponse</th><th>Pièces jointes</th><th>État</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($consultations as $c): ?>
          <?php
            $enRetardReponse = ConsultationFournisseur::estEnRetard($c);
            $nbPiecesPartage = count(ConsultationPartage::piecesIds(['pieces_ids' => $c['dernier_partage_pieces_ids'] ?? '[]']));
          ?>
          <tr>
            <td class="row-title"><?= View::e($c['fournisseur_nom']) ?></td>
            <td><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=besoin" style="color:var(--primary);text-decoration:none">Voir le Besoin (<?= View::e($dossier['reference']) ?>)</a></td>
            <td><?= !empty($c['date_envoi']) ? date('d/m/Y', strtotime($c['date_envoi'])) : '—' ?></td>
            <td>
              <?php if (!empty($c['dernier_partage_echeance'])): ?>
                <?= date('d/m/Y', strtotime($c['dernier_partage_echeance'])) ?>
                <?php if ($enRetardReponse): ?> <span class="badge badge-red">En retard</span><?php endif; ?>
              <?php else: ?>
                <span style="color:#9ca3af;font-style:italic">—</span>
              <?php endif; ?>
            </td>
            <td><?= $nbPiecesPartage > 0 ? $nbPiecesPartage . ' fichier' . ($nbPiecesPartage > 1 ? 's' : '') : '—' ?></td>
            <td><span class="badge <?= $c['statut'] === 'reponse_recue' ? 'badge-green' : ($c['statut'] === 'sans_reponse' ? 'badge-red' : ($c['statut'] === 'relance' ? 'badge-orange' : 'badge-blue')) ?>"><?= ConsultationFournisseur::STATUTS[$c['statut']] ?? $c['statut'] ?></span></td>
            <td><a href="/index.php?r=consultations/<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Voir</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>
