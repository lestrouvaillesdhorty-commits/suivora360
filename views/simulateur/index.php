<?php use App\Core\View; ?>
<h1>Simulateur de prix</h1>
<div class="subtitle">Coût de revient, prix de vente et conversion en FCFA</div>

<div class="card">
<form method="get" action="/index.php">
  <input type="hidden" name="r" value="simulateur">
  <?php if (count($filiales) > 1): ?>
  <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">
  <?php endif; ?>

  <h2 style="font-size:15px;margin-top:0">Achat et logistique</h2>
  <div class="form-row">
    <div class="form-group"><label>Montant d'achat total (€)</label><input type="number" step="0.01" name="achat" value="<?= View::e($input['achat'] ?? '') ?>" required></div>
    <div class="form-group"><label>Quantité</label><input type="number" step="1" min="1" name="quantite" value="<?= View::e($input['quantite'] ?? '') ?>" required></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Poids total (kg) — informatif</label><input type="number" step="0.1" name="poids" value="<?= View::e($input['poids'] ?? '') ?>"></div>
    <div class="form-group"><label>Transport (€)</label><input type="number" step="0.01" name="transport" value="<?= View::e($input['transport'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Emballage (€)</label><input type="number" step="0.01" name="emballage" value="<?= View::e($input['emballage'] ?? '') ?>"></div>
    <div class="form-group"><label>Assurance (€)</label><input type="number" step="0.01" name="assurance" value="<?= View::e($input['assurance'] ?? $parametres['assurance_defaut']) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Droits de douane (€)</label>
      <input type="number" step="0.01" name="douane" value="<?= View::e($input['douane'] ?? '') ?>">
      <div style="font-size:12px;color:#888;margin-top:4px">Estimation manuelle à confirmer selon le code douanier, l'origine et la destination.</div>
    </div>
    <div class="form-group"><label>Frais de dédouanement (€)</label><input type="number" step="0.01" name="dedouanement" value="<?= View::e($input['dedouanement'] ?? $parametres['dedouanement_defaut']) ?>"></div>
  </div>
  <div class="form-group"><label>Autres frais (€)</label><input type="number" step="0.01" name="autres_frais" value="<?= View::e($input['autres_frais'] ?? '') ?>"></div>

  <h2 style="font-size:15px">Marge et taxes</h2>
  <div class="form-row">
    <div class="form-group"><label>Majoration sur le coût (%)</label><input type="number" step="0.1" name="majoration_pourcentage" value="<?= View::e($input['majoration_pourcentage'] ?? $parametres['marge_defaut_pourcentage']) ?>"></div>
    <div class="form-group"><label>TVA (%)</label><input type="number" step="0.1" name="tva_pourcentage" value="<?= View::e($input['tva_pourcentage'] ?? $parametres['tva_defaut_pourcentage']) ?>"></div>
  </div>
  <div class="form-group" style="max-width:260px">
    <label>Taux — 1 EUR = X FCFA</label>
    <input type="number" step="0.001" name="taux_fcfa" value="<?= View::e($input['taux_fcfa'] ?? $parametres['taux_eur_fcfa']) ?>">
  </div>

  <button type="submit" class="btn">Calculer</button>
  <a href="/index.php?r=simulateur" class="btn btn-secondary" style="margin-left:8px">Réinitialiser</a>
</form>
</div>

<?php if ($resultat): ?>
<div class="card">
  <h2>Résultat</h2>
  <div class="info-row"><span class="label">Coût de revient total</span><span><?= number_format($resultat['cout_revient_total'], 2, ',', ' ') ?> €</span></div>
  <?php if ($resultat['cout_revient_unitaire'] !== null): ?>
  <div class="info-row"><span class="label">Coût de revient unitaire</span><span><?= number_format($resultat['cout_revient_unitaire'], 2, ',', ' ') ?> €</span></div>
  <?php endif; ?>
  <div class="info-row"><span class="label">Majoration (<?= View::e((string) $resultat['majoration_pourcentage']) ?>%)</span><span><?= number_format($resultat['majoration_montant'], 2, ',', ' ') ?> €</span></div>
  <div class="info-row"><span class="label">Prix HT</span><span><strong><?= number_format($resultat['prix_ht'], 2, ',', ' ') ?> €</strong></span></div>
  <div class="info-row"><span class="label">TVA (<?= View::e((string) $resultat['tva_pourcentage']) ?>%)</span><span><?= number_format($resultat['tva_montant'], 2, ',', ' ') ?> €</span></div>
  <div class="info-row"><span class="label">Prix TTC</span><span><strong><?= number_format($resultat['prix_ttc'], 2, ',', ' ') ?> €</strong></span></div>
  <?php if ($resultat['prix_ttc_fcfa'] !== null): ?>
  <div class="info-row"><span class="label">Prix TTC en FCFA (taux <?= View::e((string) $resultat['taux_fcfa']) ?>)</span><span><strong><?= number_format($resultat['prix_ttc_fcfa'], 0, ',', ' ') ?> FCFA</strong></span></div>
  <?php endif; ?>
</div>
<?php endif; ?>
