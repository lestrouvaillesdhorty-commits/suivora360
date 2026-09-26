<?php use App\Core\View;
use App\Models\Demande;
use App\Models\Fournisseur;
$paysListe = ['Cameroun', 'France', "Côte d'Ivoire", 'Sénégal', 'Mali', 'Togo', 'Bénin', 'Gabon', 'Congo (Brazzaville)', 'RD Congo', 'Nigeria', 'Ghana', 'Maroc', 'Tunisie', 'Algérie', 'Belgique', 'Allemagne', 'Chine', 'Émirats arabes unis', 'Inde', 'Turquie', 'États-Unis'];
?>
<h1>Nouveau fournisseur</h1>
<div class="subtitle">Enregistrer un fournisseur</div>

<div class="card">
<form method="post" action="/index.php?r=fournisseurs">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <?php if (count($filiales) > 1): ?>
  <div class="form-group">
    <label>Filiale</label>
    <select name="filiale_id" required>
      <?php foreach ($filiales as $f): ?>
        <option value="<?= $f['id'] ?>"><?= View::e($f['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php elseif (count($filiales) === 1): ?>
    <input type="hidden" name="filiale_id" value="<?= $filiales[0]['id'] ?>">
  <?php else: ?>
    <div class="alert alert-erreur">Aucune filiale ne vous est assignée.</div>
  <?php endif; ?>

  <div class="form-group">
    <label>Nom *</label>
    <input type="text" name="nom" required>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Statut</label>
      <select name="statut">
        <?php foreach (Fournisseur::STATUTS as $code => $label): ?>
          <option value="<?= $code ?>" <?= $code === 'a_qualifier' ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Fonction du contact</label><input type="text" name="fonction_contact"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
    <div class="form-group"><label>Téléphone (avec indicatif, ex. +86...)</label><input type="tel" name="telephone"></div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Pays</label>
      <select name="pays">
        <option value="">—</option>
        <?php foreach ($paysListe as $p): ?>
          <option><?= View::e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Ville</label><input type="text" name="ville"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Devise</label>
      <select name="devise">
        <option value="">—</option>
        <option>EUR</option><option>USD</option><option>XAF</option><option>GBP</option><option>CHF</option>
      </select>
    </div>
    <div class="form-group"><label>Site web</label><input type="text" name="site_web"></div>
  </div>

  <div class="form-group"><label>Adresse</label><input type="text" name="adresse"></div>
  <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur"></div>

  <details style="margin-bottom:16px">
    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#555;margin-bottom:8px">Informations complémentaires (facultatif)</summary>
    <div style="margin-top:12px">
      <div class="form-row">
        <div class="form-group"><label>Catégories de produits</label><input type="text" name="categories_produits" placeholder="ex. Riz, huile, conserves"></div>
        <div class="form-group"><label>Marques proposées</label><input type="text" name="marques"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Pays desservis</label><input type="text" name="pays_desservis" placeholder="ex. Cameroun, Gabon"></div>
        <div class="form-group"><label>Quantité minimale habituelle</label><input type="text" name="quantite_min" placeholder="ex. 1 conteneur 20'"></div>
      </div>
      <div class="form-group">
        <label>Incoterms pratiqués</label>
        <select name="incoterms_pratiques[]" multiple size="5">
          <?php foreach (Demande::INCOTERMS as $code => $label): ?>
            <option value="<?= $code ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </details>

  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Créer le fournisseur</button>
    <a href="/index.php?r=fournisseurs" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
