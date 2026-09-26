<?php
use App\Core\View;
use App\Models\Commande;
use App\Models\ConsultationFournisseur;
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\Utilisateur;

$enRetard = $dossier['statut'] === 'actif' && $dossier['echeance'] && strtotime($dossier['echeance']) < strtotime('today');
$actionLabelsDossier = [
    'creation_dossier' => 'Dossier créé',
    'maj_etape_dossier' => 'Étape mise à jour',
    'ajout_piece_jointe' => 'Pièce jointe ajoutée',
    'suppression_piece_jointe' => 'Pièce jointe supprimée',
    'creation_consultation' => 'Consultation fournisseur envoyée',
    'creation_offre' => 'Offre fournisseur enregistrée',
    'offre_retenue' => 'Offre retenue',
    'creation_cotation' => 'Cotation créée',
    'changement_statut_cotation' => 'Statut de la cotation changé',
    'creation_commande' => 'Commande créée',
    'maj_etape_commande' => 'Étape de la commande mise à jour',
    'creation_facture' => 'Facture créée',
    'changement_statut_facture' => 'Statut de la facture changé',
];
?>
<a href="/index.php?r=dossiers" style="font-size:13px;color:#666">&larr; Retour aux dossiers</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($dossier['reference']) ?></h1>
    <div class="subtitle"><?= View::e($dossier['objet']) ?> — <?= View::e($filiale['nom'] ?? '') ?></div>
  </div>
  <a href="/index.php?r=demandes/<?= $demande['id'] ?>" class="btn btn-secondary">Voir la demande d'origine</a>
</div>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Étape et suivi</h2>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/etape" style="margin-bottom:16px">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label>Étape</label>
        <select name="etape" class="stage-select" onchange="this.form.submit()">
          <?php foreach (Dossier::ETAPES as $etape): ?>
            <option value="<?= $etape ?>" <?= $dossier['etape'] === $etape ? 'selected' : '' ?>><?= Dossier::ETAPES_LABELS[$etape] ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="info-row"><span class="label">Statut</span><span><?= ucfirst($dossier['statut']) ?><?php if ($enRetard): ?> <span class="badge badge-red">En retard</span><?php endif; ?></span></div>
      <div class="info-row"><span class="label">Responsable</span><span><?= View::e(Utilisateur::nameOf($dossier['responsable_id'])) ?></span></div>
      <div class="info-row"><span class="label">Urgence</span><span><?= Demande::PRIORITES[$dossier['priorite']] ?? ucfirst($dossier['priorite']) ?></span></div>
      <div class="info-row"><span class="label">Échéance</span><span><?= $dossier['echeance'] ? date('d/m/Y', strtotime($dossier['echeance'])) : '—' ?></span></div>
    </div>

    <div class="card">
      <h2>Demande d'origine</h2>
      <div class="info-row"><span class="label">Expéditeur</span><span><?= View::e($demande['expediteur_nom']) ?> (<?= View::e($demande['expediteur_entreprise']) ?>)</span></div>
      <div class="info-row"><span class="label">Message</span><span><?= nl2br(View::e($demande['message'])) ?></span></div>
      <div class="info-row"><span class="label">Canal</span><span><?= View::e($demande['canal']) ?></span></div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Articles</h2>
        <button class="btn btn-sm" onclick="document.getElementById('article-form').style.display='block'">+ Ajouter</button>
      </div>

      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/articles" id="article-form" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-row">
          <div class="form-group"><label>Désignation</label><input type="text" name="designation" required></div>
          <div class="form-group"><label>Quantité</label><input type="number" step="0.01" name="quantite"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Unité</label>
            <select name="unite">
              <option value="">—</option>
              <?php foreach (['Pièce', 'Carton', 'Kg', 'Tonne', 'Litre', 'm³', 'Sac', 'Palette', "Conteneur 20'", "Conteneur 40'"] as $u): ?>
                <option><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Référence</label><input type="text" name="reference"></div>
        </div>
        <div class="form-group"><label>Marque</label><input type="text" name="marque"></div>
        <button type="submit" class="btn">Enregistrer</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('article-form').style.display='none'">Annuler</button>
      </form>

      <?php if (empty($articles)): ?>
        <div class="empty-state" style="margin-top:12px">Aucun article pour le moment.</div>
      <?php else: ?>
        <table style="margin-top:16px">
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

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Sourcing / Comparateur d'offres</h2>
        <div style="display:flex;gap:8px">
          <?php if (!empty($offres)): ?>
            <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur" class="btn btn-sm btn-secondary">Comparateur (<?= count($offres) ?>)</a>
          <?php endif; ?>
          <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/consultations/nouvelle" class="btn btn-sm">+ Consulter un fournisseur</a>
        </div>
      </div>

      <?php if (empty($consultations)): ?>
        <div class="empty-state" style="margin-top:12px">Aucune consultation envoyée pour le moment.</div>
      <?php else: ?>
        <table style="margin-top:16px">
          <thead><tr><th>Référence</th><th>Fournisseur</th><th>Statut</th><th>Offres</th></tr></thead>
          <tbody>
          <?php foreach ($consultations as $c): ?>
            <tr>
              <td><a href="/index.php?r=consultations/<?= $c['id'] ?>"><?= View::e($c['reference']) ?></a></td>
              <td><?= View::e($c['fournisseur_nom']) ?></td>
              <td><?= ConsultationFournisseur::STATUTS[$c['statut']] ?? $c['statut'] ?></td>
              <td><?= (int) $c['nb_offres'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($offreRetenue): ?>
        <div class="alert alert-succes" style="margin-top:16px;margin-bottom:0">Offre retenue : <?= View::e($offreRetenue['fournisseur_nom']) ?> — <?= number_format((float) $offreRetenue['montant_total'], 2, ',', ' ') ?> <?= View::e($offreRetenue['devise']) ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Cotation</h2>
        <?php if (!$cotation): ?>
          <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/cotations/nouvelle" class="btn btn-sm">+ Créer une cotation</a>
        <?php endif; ?>
      </div>
      <?php if (!$cotation): ?>
        <div class="empty-state" style="margin-top:12px">Aucune cotation créée pour ce dossier.</div>
      <?php else: ?>
        <div class="info-row" style="margin-top:8px"><span class="label">Référence</span><span><a href="/index.php?r=cotations/<?= $cotation['id'] ?>"><?= View::e($cotation['reference']) ?></a></span></div>
        <div class="info-row"><span class="label">Client</span><span><?= View::e($cotation['client_nom']) ?></span></div>
        <div class="info-row"><span class="label">Montant</span><span><?= number_format((float) $cotation['montant_total'], 2, ',', ' ') ?> <?= View::e($cotation['devise']) ?></span></div>
        <div class="info-row"><span class="label">Statut</span><span><?= Cotation::STATUTS[$cotation['statut']] ?? $cotation['statut'] ?></span></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Commande &amp; Facturation</h2>
        <?php if ($commande): ?>
          <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/commande" class="btn btn-sm btn-secondary">Voir le suivi</a>
        <?php endif; ?>
      </div>
      <?php if ($commande): ?>
        <div class="info-row" style="margin-top:8px"><span class="label">Référence</span><span><?= View::e($commande['reference']) ?></span></div>
        <div class="info-row"><span class="label">Étape courante</span><span><?= Commande::ETAPES_STEPS[$commande['etape']] ?? ($commande['etape'] === 'terminee' ? 'Terminée' : $commande['etape']) ?></span></div>
      <?php elseif ($cotation && $cotation['statut'] === 'acceptee'): ?>
        <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/commande" style="margin-top:12px">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <input type="hidden" name="cotation_id" value="<?= $cotation['id'] ?>">
          <button type="submit" class="btn btn-sm">Créer la commande</button>
        </form>
      <?php else: ?>
        <div class="empty-state" style="margin-top:12px">En attente d'une cotation acceptée par le client.</div>
      <?php endif; ?>

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
              <td><a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/telecharger"><?= View::e($p['nom_original']) ?></a></td>
              <td><?= number_format($p['taille'] / 1024, 0) ?> Ko</td>
              <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
              <td><?= View::e($p['uploaded_by_nom']) ?></td>
              <td>
                <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces/<?= $p['id'] ?>/supprimer" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <button type="submit" class="btn btn-sm btn-secondary">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/pieces" enctype="multipart/form-data" style="margin-top:12px;padding-top:12px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="file" name="fichier" required>
        <button type="submit" class="btn btn-sm" style="margin-top:8px">Ajouter</button>
      </form>
    </div>

    <?php if (!empty($historique)): ?>
    <div class="card">
      <h2>Historique</h2>
      <?php foreach ($historique as $h): ?>
        <div class="info-row">
          <span class="label"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></span>
          <span><?= View::e($actionLabelsDossier[$h['action']] ?? $h['action']) ?><?php if ($h['utilisateur_nom']): ?> — <?= View::e($h['utilisateur_nom']) ?><?php endif; ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h2>Notes internes</h2>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/notes">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <textarea name="notes" rows="6" placeholder="Notes internes..."><?= View::e($dossier['notes']) ?></textarea>
        <button type="submit" class="btn btn-sm" style="margin-top:10px">Enregistrer</button>
      </form>
      <div style="font-size:12px;color:#999;margin-top:10px">
        Dernière modification : <?= date('d/m/Y H:i', strtotime($dossier['updated_at'])) ?>
      </div>
    </div>
  </div>
</div>
