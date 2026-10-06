<?php use App\Core\View; use App\Models\Commande; use App\Models\Facture; use App\Models\Dossier; ?>
<?php $typeDossier = $dossier['type_dossier'] ?? 'autre'; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($commande['reference']) ?></h1>
    <div class="subtitle">Suivi opérationnel — Dossier <?= View::e($dossier['reference']) ?></div>
  </div>
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/factures/nouvelle" class="btn">+ Ajouter une facture</a>
</div>

<div class="detail-grid">
  <div>
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <h2 style="margin:0">Étapes de suivi</h2>
        <strong style="font-size:14px"><?= $progression ?>%</strong>
      </div>
      <div style="background:#f1f2f5;border-radius:6px;height:8px;overflow:hidden;margin-bottom:16px">
        <div style="background:<?= $progression === 100 ? '#16a34a' : '#4f46e5' ?>;height:100%;width:<?= $progression ?>%"></div>
      </div>
      <?php foreach ($steps as $step): ?>
        <div style="padding:14px 0;border-bottom:1px solid #f1f2f5">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong><?= Commande::ETAPES_STEPS[$step['libelle']] ?? $step['libelle'] ?></strong>
            <span class="badge <?= $step['statut'] === 'termine' ? 'badge-green' : ($step['statut'] === 'en_cours' ? 'badge-blue' : 'badge-gray') ?>"><?= Commande::libelleStatutEtape($step['libelle'], $step['statut']) ?></span>
          </div>
          <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/etapes/<?= $step['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div class="form-row">
              <div class="form-group" style="margin-bottom:8px">
                <select name="statut">
                  <?php foreach (Commande::STEP_STATUTS as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $step['statut'] === $code ? 'selected' : '' ?>><?= Commande::libelleStatutEtape($step['libelle'], $code) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:8px">
                <input type="date" name="date_reelle" value="<?= View::e($step['date_reelle'] ?? '') ?>" placeholder="Date réelle">
              </div>
            </div>
            <input type="text" name="notes" value="<?= View::e($step['notes'] ?? '') ?>" placeholder="Note sur cette étape" style="margin-bottom:8px">
            <button type="submit" class="btn btn-sm">Mettre à jour</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Commande</h2>
      <div class="info-row"><span class="label">Statut</span><span><?= $commande['statut'] === 'terminee' ? 'Terminée' : 'En cours' ?></span></div>
      <div class="info-row"><span class="label">Étape courante</span><span><?= Commande::ETAPES_STEPS[$commande['etape']] ?? ($commande['etape'] === 'terminee' ? 'Terminée' : $commande['etape']) ?></span></div>
    </div>

    <div class="card">
      <h2>Suivi &amp; relance</h2>
      <?php if (Commande::estEnRetard($commande)): ?>
        <div class="alert alert-erreur">À relancer : la date prévue (<?= date('d/m/Y', strtotime($commande['date_relance'])) ?>) est dépassée.</div>
      <?php endif; ?>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/suivi">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><label>Prochaine action</label><input type="text" name="prochaine_action" value="<?= View::e($commande['prochaine_action'] ?? '') ?>" placeholder="ex: relancer le fournisseur pour la date d'expédition"></div>
        <div class="form-group"><label>Date de relance</label><input type="date" name="date_relance" value="<?= View::e($commande['date_relance'] ?? '') ?>"></div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>
      </form>
    </div>

    <?php if ($typeDossier === 'transport_logistique'): ?>
    <div class="card">
      <h2>Transport</h2>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/logistique">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><label>Numéro de tracking</label><input type="text" name="tracking_numero" value="<?= View::e($commande['tracking_numero'] ?? '') ?>"></div>
        <div class="form-row">
          <div class="form-group"><label>Début de transit</label><input type="date" name="date_transit_debut" value="<?= View::e($commande['date_transit_debut'] ?? '') ?>"></div>
          <div class="form-group"><label>Fin de transit</label><input type="date" name="date_transit_fin" value="<?= View::e($commande['date_transit_fin'] ?? '') ?>"></div>
        </div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>
      </form>
    </div>
    <?php elseif ($typeDossier === 'prestation_entreprise'): ?>
    <div class="card">
      <h2>Livrables</h2>
      <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/livrables">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group"><textarea name="livrables" rows="3" placeholder="ex: Rapport de prospection remis, 3 rendez-vous pris..."><?= View::e($commande['livrables'] ?? '') ?></textarea></div>
        <button type="submit" class="btn btn-sm btn-secondary">Enregistrer</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>Factures</h2>
      <?php if (empty($factures)): ?>
        <div class="empty-state">Aucune facture pour ce dossier.</div>
      <?php else: ?>
        <?php foreach ($factures as $f): ?>
          <div style="padding:10px 0;border-bottom:1px solid #f1f2f5">
            <div style="display:flex;justify-content:space-between;align-items:center">
              <div>
                <strong><?= View::e($f['reference']) ?></strong>
                <div style="font-size:12px;color:#666"><?= Facture::TYPES[$f['type']] ?? $f['type'] ?> — <?= number_format((float) $f['montant'], 2, ',', ' ') ?> <?= View::e($f['devise']) ?></div>
              </div>
              <span class="badge <?= $f['statut'] === 'payee' ? 'badge-green' : ($f['statut'] === 'annulee' ? 'badge-red' : 'badge-blue') ?>"><?= Facture::STATUTS[$f['statut']] ?? $f['statut'] ?></span>
            </div>
            <?php if ($f['statut'] === 'emise'): ?>
            <form method="post" action="/index.php?r=factures/<?= $f['id'] ?>/statut" style="margin-top:8px">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <input type="hidden" name="statut" value="payee">
              <button type="submit" class="btn btn-sm btn-secondary">Marquer payée</button>
            </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
