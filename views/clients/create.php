<?php use App\Core\View;
use App\Models\Client;
use App\Models\Demande;
$paysListe = ['Cameroun', 'France', "Côte d'Ivoire", 'Sénégal', 'Mali', 'Togo', 'Bénin', 'Gabon', 'Congo (Brazzaville)', 'RD Congo', 'Nigeria', 'Ghana', 'Maroc', 'Tunisie', 'Algérie', 'Belgique', 'Allemagne', 'Chine', 'Émirats arabes unis', 'Inde', 'Turquie', 'États-Unis'];
?>
<h1>Nouveau client</h1>
<div class="subtitle">Enregistrer un client</div>

<div class="card">
<form method="post" action="/index.php?r=clients">
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
      <label>Type</label>
      <select name="type">
        <option value="">—</option>
        <?php foreach (Client::TYPES as $code => $label): ?>
          <option value="<?= $code ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Statut</label>
      <select name="statut">
        <?php foreach (Client::STATUTS as $code => $label): ?>
          <option value="<?= $code ?>" <?= $code === 'actif' ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email"></div>
    <div class="form-group"><label>Téléphone (avec indicatif, ex. +237...)</label><input type="tel" name="telephone"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Fonction du contact principal</label><input type="text" name="fonction_contact"></div>
    <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur"></div>
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
    <div class="form-group"><label>Adresse</label><input type="text" name="adresse"></div>
    <div class="form-group"><label>Code postal</label><input type="text" name="code_postal"></div>
  </div>

  <details style="margin-bottom:16px">
    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#555;margin-bottom:8px">Informations complémentaires (facultatif)</summary>
    <div style="margin-top:12px">
      <div class="form-group"><label>Adresse de livraison (si différente)</label><input type="text" name="adresse_livraison"></div>
      <div class="form-row">
        <div class="form-group"><label>SIREN / SIRET</label><input type="text" name="siret"></div>
        <div class="form-group"><label>N° TVA / identifiant fiscal</label><input type="text" name="tva"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Incoterm habituel</label>
          <select name="incoterm_habituel">
            <option value="">—</option>
            <?php foreach (Demande::INCOTERMS as $code => $label): ?>
              <option value="<?= $code ?>"><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Mode de transport habituel</label>
          <select name="mode_transport_habituel">
            <option value="">—</option>
            <?php foreach (Client::MODES_TRANSPORT as $code => $label): ?>
              <option value="<?= $code ?>"><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Conditions de paiement habituelles</label>
        <select name="conditions_paiement">
          <option value="">—</option>
          <?php foreach (Client::CONDITIONS_PAIEMENT as $code => $label): ?>
            <option value="<?= $code ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </details>

  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Créer le client</button>
    <a href="/index.php?r=clients" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
