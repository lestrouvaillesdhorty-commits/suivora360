<?php use App\Core\View; use App\Models\Parametres; ?>
<h1>Paramètres de calcul</h1>
<div class="subtitle">Valeurs par défaut utilisées pour préremplir le Simulateur de prix</div>

<?php if (count($filiales) > 1): ?>
<form method="get" action="/index.php" style="margin-bottom:16px">
  <input type="hidden" name="r" value="parametres">
  <div class="form-group" style="max-width:280px">
    <label>Filiale</label>
    <select name="filiale_id" onchange="this.form.submit()">
      <?php foreach ($filiales as $f): ?>
        <option value="<?= $f['id'] ?>" <?= $filialeId === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>
<?php endif; ?>

<?php if (!$filialeId): ?>
  <div class="card"><div class="empty-state">Aucune filiale disponible.</div></div>
<?php else: ?>
<div class="card">
<form method="post" action="/index.php?r=parametres">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">

  <div class="form-row">
    <div class="form-group">
      <label>Taux de conversion — 1 EUR = X FCFA</label>
      <input type="number" step="0.001" min="0.001" name="taux_eur_fcfa" value="<?= View::e((string) $parametres['taux_eur_fcfa']) ?>" required>
    </div>
    <div class="form-group">
      <label>Majoration sur le coût par défaut (%)</label>
      <input type="number" step="0.1" name="marge_defaut_pourcentage" value="<?= View::e((string) $parametres['marge_defaut_pourcentage']) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>TVA par défaut</label>
      <select name="tva_defaut_pourcentage">
        <?php foreach (Parametres::TVA_OPTIONS as $taux): ?>
          <option value="<?= $taux ?>" <?= (float) $parametres['tva_defaut_pourcentage'] === (float) $taux ? 'selected' : '' ?>><?= $taux ?>%</option>
        <?php endforeach; ?>
        <option value="<?= View::e((string) $parametres['tva_defaut_pourcentage']) ?>" <?= !in_array((float) $parametres['tva_defaut_pourcentage'], Parametres::TVA_OPTIONS, true) ? 'selected' : '' ?>>Personnalisé (<?= View::e((string) $parametres['tva_defaut_pourcentage']) ?>%)</option>
      </select>
    </div>
    <div class="form-group">
      <label>Assurance par défaut (€)</label>
      <input type="number" step="0.01" name="assurance_defaut" value="<?= View::e((string) $parametres['assurance_defaut']) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Frais de dédouanement par défaut (€)</label>
      <input type="number" step="0.01" name="dedouanement_defaut" value="<?= View::e((string) $parametres['dedouanement_defaut']) ?>">
    </div>
    <div class="form-group">
      <label>Date de mise à jour du taux</label>
      <input type="date" name="taux_date_maj" value="<?= View::e((string) ($parametres['taux_date_maj'] ?? '')) ?>">
    </div>
  </div>

  <div class="form-group">
    <label>Source du taux</label>
    <input type="text" name="taux_source" placeholder="ex. Banque Centrale, XE.com..." value="<?= View::e($parametres['taux_source'] ?? '') ?>">
  </div>

  <div class="alert" style="background:#eef2ff;color:#3730a3;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px">
    Modifier ces valeurs ne recalcule jamais une simulation, une offre ou une commande déjà enregistrée — elles ne servent qu'à préremplir les futures simulations.
  </div>

  <button type="submit" class="btn">Enregistrer</button>
  <a href="/index.php?r=simulateur" class="btn btn-secondary" style="margin-left:8px">Aller au simulateur</a>
</form>
</div>
<?php endif; ?>
