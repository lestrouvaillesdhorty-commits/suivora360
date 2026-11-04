<?php use App\Core\View; ?>
<h1>Mon entreprise</h1>
<div class="subtitle">Ces informations figurent sur vos devis : en-tête, pied de page, conditions et zone de signature. <a href="/index.php?r=parametres">← Paramètres de calcul</a></div>

<?php if (count($filiales) > 1): ?>
<form method="get" action="/index.php" style="margin:14px 0">
  <input type="hidden" name="r" value="parametres/entreprise">
  <div class="form-group" style="max-width:280px">
    <label>Filiale</label>
    <select name="filiale_id" onchange="this.form.submit()">
      <?php foreach ($filiales as $f): ?><option value="<?= $f['id'] ?>" <?= $filialeId === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?>
    </select>
  </div>
</form>
<?php endif; ?>

<?php if (!$disponible): ?>
  <div class="card"><div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v36.php</strong> (voir les instructions de mise à jour).</div></div>
<?php elseif (!$filialeId): ?>
  <div class="card"><div class="empty-state">Aucune filiale disponible.</div></div>
<?php else: ?>
<form method="post" action="/index.php?r=parametres/entreprise" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <input type="hidden" name="filiale_id" value="<?= $filialeId ?>">

  <div class="card">
    <h2>Identité et coordonnées</h2>
    <div class="form-row">
      <div class="form-group"><label>Nom de l'entreprise (tel qu'il apparaît sur le devis)</label>
        <input type="text" name="raison_sociale" value="<?= View::e($info['raison_sociale']) ?>" placeholder="<?= View::e($filiale['nom'] ?? '') ?>"></div>
      <div class="form-group"><label>Site web</label><input type="text" name="site_web" value="<?= View::e($info['site_web']) ?>" placeholder="www.exemple.com"></div>
    </div>
    <div class="form-group"><label>Adresse</label><textarea name="adresse" rows="2" placeholder="Rue, code postal, ville, pays"><?= View::e($info['adresse']) ?></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" value="<?= View::e($info['telephone']) ?>"></div>
      <div class="form-group"><label>E-mail</label><input type="text" name="email" value="<?= View::e($info['email']) ?>"></div>
    </div>
    <div class="form-group"><label>Mentions légales (une par ligne)</label>
      <textarea name="identifiants" rows="3" placeholder="N° TVA : …&#10;RCCM / SIRET : …&#10;NIF : …"><?= View::e($info['identifiants']) ?></textarea>
      <div class="hint">Repris dans le pied de page de chaque devis.</div></div>
    <div class="form-group"><label>Logo (PNG ou JPG, 2 Mo maximum)</label>
      <?php if ($logoUri): ?><div style="margin:6px 0"><img src="<?= $logoUri ?>" alt="Logo actuel" style="max-height:70px;max-width:260px;border:1px solid #DCE9EC;border-radius:10px;padding:6px;background:#fff"></div>
        <label style="font-weight:500"><input type="checkbox" name="supprimer_logo" value="1"> Retirer le logo actuel</label><?php endif; ?>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
      <div class="hint">Le logo s'affiche sur l'aperçu et l'impression du devis. Le PDF téléchargé reprend le texte de l'en-tête, sans le logo.</div></div>
  </div>

  <div class="card">
    <h2>Règlement et conditions du devis</h2>
    <div class="form-group"><label>Coordonnées bancaires (affichées sur le devis)</label>
      <textarea name="coordonnees_bancaires" rows="3" placeholder="Banque : …&#10;IBAN : …&#10;BIC : …"><?= View::e($info['coordonnees_bancaires']) ?></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Validité d'un devis (jours)</label><input type="number" min="1" max="365" name="devis_validite_jours" value="<?= (int) $info['devis_validite_jours'] ?>">
        <div class="hint">Utilisée quand aucune date de validité n'est saisie sur la cotation.</div></div>
    </div>
    <div class="form-group"><label>Conditions générales (texte repris sur chaque devis)</label>
      <textarea name="devis_conditions" rows="4" placeholder="Ex. : Acompte de 50 % à la commande, solde avant expédition. Délai de livraison à confirmer à la commande."><?= View::e($info['devis_conditions']) ?></textarea></div>
  </div>

  <button type="submit" class="btn">Enregistrer</button>
</form>
<?php endif; ?>
