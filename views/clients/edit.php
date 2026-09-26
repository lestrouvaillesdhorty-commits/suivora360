<?php use App\Core\View;
use App\Models\Client;
use App\Models\Demande;
$paysListe = ['Cameroun', 'France', "Côte d'Ivoire", 'Sénégal', 'Mali', 'Togo', 'Bénin', 'Gabon', 'Congo (Brazzaville)', 'RD Congo', 'Nigeria', 'Ghana', 'Maroc', 'Tunisie', 'Algérie', 'Belgique', 'Allemagne', 'Chine', 'Émirats arabes unis', 'Inde', 'Turquie', 'États-Unis'];
?>
<a href="/index.php?r=clients/<?= $client['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au client</a>

<h1>Modifier <?= View::e($client['nom']) ?></h1>
<div class="subtitle">Mettre à jour les informations du client</div>

<div class="card">
<form method="post" action="/index.php?r=clients/<?= $client['id'] ?>/modifier">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

  <div class="form-group">
    <label>Nom *</label>
    <input type="text" name="nom" value="<?= View::e($client['nom']) ?>" required>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Type</label>
      <select name="type">
        <option value="">—</option>
        <?php foreach (Client::TYPES as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($client['type'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Statut</label>
      <select name="statut">
        <?php foreach (Client::STATUTS as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($client['statut'] ?? 'actif') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= View::e($client['email']) ?>"></div>
    <div class="form-group"><label>Téléphone (avec indicatif, ex. +237...)</label><input type="tel" name="telephone" value="<?= View::e($client['telephone']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Fonction du contact principal</label><input type="text" name="fonction_contact" value="<?= View::e($client['fonction_contact'] ?? '') ?>"></div>
    <div class="form-group"><label>Secteur d'activité</label><input type="text" name="secteur" value="<?= View::e($client['secteur']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label>Pays</label>
      <select name="pays">
        <option value="">—</option>
        <?php if ($client['pays'] && !in_array($client['pays'], $paysListe, true)): ?>
          <option selected><?= View::e($client['pays']) ?></option>
        <?php endif; ?>
        <?php foreach ($paysListe as $p): ?>
          <option <?= $client['pays'] === $p ? 'selected' : '' ?>><?= View::e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Ville</label><input type="text" name="ville" value="<?= View::e($client['ville']) ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= View::e($client['adresse']) ?>"></div>
    <div class="form-group"><label>Code postal</label><input type="text" name="code_postal" value="<?= View::e($client['code_postal'] ?? '') ?>"></div>
  </div>

  <details style="margin-bottom:16px" open>
    <summary style="cursor:pointer;font-weight:600;font-size:13px;color:#555;margin-bottom:8px">Informations complémentaires</summary>
    <div style="margin-top:12px">
      <div class="form-group"><label>Adresse de livraison (si différente)</label><input type="text" name="adresse_livraison" value="<?= View::e($client['adresse_livraison'] ?? '') ?>"></div>
      <div class="form-row">
        <div class="form-group"><label>SIREN / SIRET</label><input type="text" name="siret" value="<?= View::e($client['siret'] ?? '') ?>"></div>
        <div class="form-group"><label>N° TVA / identifiant fiscal</label><input type="text" name="tva" value="<?= View::e($client['tva'] ?? '') ?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Incoterm habituel</label>
          <select name="incoterm_habituel">
            <option value="">—</option>
            <?php foreach (Demande::INCOTERMS as $code => $label): ?>
              <option value="<?= $code ?>" <?= ($client['incoterm_habituel'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Mode de transport habituel</label>
          <select name="mode_transport_habituel">
            <option value="">—</option>
            <?php foreach (Client::MODES_TRANSPORT as $code => $label): ?>
              <option value="<?= $code ?>" <?= ($client['mode_transport_habituel'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Conditions de paiement habituelles</label>
        <select name="conditions_paiement">
          <option value="">—</option>
          <?php foreach (Client::CONDITIONS_PAIEMENT as $code => $label): ?>
            <option value="<?= $code ?>" <?= ($client['conditions_paiement'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </details>

  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($client['notes']) ?></textarea></div>

  <div style="margin-top:24px">
    <button type="submit" class="btn">Enregistrer</button>
    <a href="/index.php?r=clients/<?= $client['id'] ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
