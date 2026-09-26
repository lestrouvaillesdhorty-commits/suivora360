<?php use App\Core\View; use App\Models\Commande; use App\Models\Facture; ?>
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
      <h2>Étapes de suivi</h2>
      <?php foreach ($steps as $step): ?>
        <div style="padding:14px 0;border-bottom:1px solid #f1f2f5">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <strong><?= Commande::ETAPES_STEPS[$step['libelle']] ?? $step['libelle'] ?></strong>
            <span class="badge <?= $step['statut'] === 'termine' ? 'badge-green' : ($step['statut'] === 'en_cours' ? 'badge-blue' : 'badge-gray') ?>"><?= Commande::STEP_STATUTS[$step['statut']] ?? $step['statut'] ?></span>
          </div>
          <form method="post" action="/index.php?r=commandes/<?= $commande['id'] ?>/etapes/<?= $step['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div class="form-row">
              <div class="form-group" style="margin-bottom:8px">
                <select name="statut">
                  <?php foreach (Commande::STEP_STATUTS as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $step['statut'] === $code ? 'selected' : '' ?>><?= $label ?></option>
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
