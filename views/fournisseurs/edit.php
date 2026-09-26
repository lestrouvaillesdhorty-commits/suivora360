<?php use App\Core\View;
use App\Models\Demande;
use App\Models\Fournisseur;
$paysListe = ['Cameroun', 'France', "Côte d'Ivoire", 'Sénégal', 'Mali', 'Togo', 'Bénin', 'Gabon', 'Congo (Brazzaville)', 'RD Congo', 'Nigeria', 'Ghana', 'Maroc', 'Tunisie', 'Algérie', 'Belgique', 'Allemagne', 'Chine', 'Émirats arabes unis', 'Inde', 'Turquie', 'États-Unis'];
$incotermsPratiques = !empty($fournisseur['incoterms_pratiques']) ? explode(',', $fournisseur['incoterms_pratiques']) : [];
?>
<a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au fournisseur</a>

<h1>Modifier <?= View::e($fournisseur['nom']) ?></h1>
<div class="subtitle">Mettre à jour les informations du fournisseur</div>

<div class="card">
<form method="post" action="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>/modifier">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <div class="form-group">
    <label>Nom *</label>
    <input type="text" name="nom" value="<?= View::e($fournisseur['nom']) ?>" required>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Statut</label>
      <select name="statut">
        <?php foreach (Fournisseur::STATUTS as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($fournisseur['statut'] ?? 'a_qualifier') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Fonction du contact</label><input type="text" name="fonction_contact" value="<?= View::e($fournisseur['fonction_contact'] ?? '') ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= View::e($fournisseur['email']) ?>"></div>
    <div class="form-group"><label>Téléphone (avec indicatif, ex. +86...)</label><input type="tel" name="telephone" value="<?= View::e($fournisseur['telephone']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Pays</label>
      <select name="pays">
        <option value="">—</option>
        <?php if ($fournisseur['pays'] && !in_array($fournisseur['pays'], $paysListe, true)): ?>
          <option selected><?= View::e($fournisseur['pays']) ?></option>
        <?php endif; ?>
        <?php foreach ($paysListe as $p): ?>
          <option <?= $fournisseur['pays'] === $p ? 'selected' : '' ?>><?= View::e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Ville</label><input type="text" name="ville" value="<?= View::e($fournisseur['ville']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Devise</label>
      <select name="devise">
        <option value="">—</option>
        <?php foreach (['EUR', 'USD', 'XAF', 'GBP', 'CHF'] as $d): ?>
          <option <?= $fournisseur['devise'] === $d ? 'selected' : '' ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Site web</label><input type="text" name="site_web" value="<?= View::e($fournisseur['site_web']) ?>"></div>
  </div>

  <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= View::e($fournisseur['adresse']) ?>"></div>
  <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur" value="<?= View::e($fournisseur['secteur']) ?>"></div>

  <details style="margin-bottom:16px" open>
    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#555;margin-bottom:8px">Informations complémentaires</summary>
    <div style="margin-top:12px">
      <div class="form-row">
        <div class="form-group"><label>Catégories de produits</label><input type="text" name="categories_produits" value="<?= View::e($fournisseur['categories_produits'] ?? '') ?>"></div>
        <div class="form-group"><label>Marques proposées</label><input type="text" name="marques" value="<?= View::e($fournisseur['marques'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Pays desservis</label><input type="text" name="pays_desservis" value="<?= View::e($fournisseur['pays_desservis'] ?? '') ?>"></div>
        <div class="form-group"><label>Quantité minimale habituelle</label><input type="text" name="quantite_min" value="<?= View::e($fournisseur['quantite_min'] ?? '') ?>"></div>
      </div>
      <div class="form-group">
        <label>Incoterms pratiqués</label>
        <select name="incoterms_pratiques[]" multiple size="5">
          <?php foreach (Demande::INCOTERMS as $code => $label): ?>
            <option value="<?= $code ?>" <?= in_array($code, $incotermsPratiques, true) ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </details>

  <details style="margin-bottom:16px" open>
    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#555;margin-bottom:8px">Notation (0 à 5, laisser vide si non évalué)</summary>
    <div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
      <?php foreach (Fournisseur::CRITERES_NOTE as $champ => $label): ?>
        <div class="form-group">
          <label><?= $label ?></label>
          <select name="<?= $champ ?>">
            <option value="">—</option>
            <?php for ($n = 0; $n <= 5; $n++): ?>
              <option value="<?= $n ?>" <?= (string) ($fournisseur[$champ] ?? '') === (string) $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endfor; ?>
          </select>
        </div>
      <?php endforeach; ?>
    </div>
  </details>

  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($fournisseur['notes']) ?></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Enregistrer</button>
    <a href="/index.php?r=fournisseurs/<?= $fournisseur['id'] ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
