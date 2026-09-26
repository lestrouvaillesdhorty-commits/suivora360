<?php use App\Core\View; use App\Models\Demande; use App\Models\Client; use App\Models\Offre; ?>
<a href="/index.php?r=consultations/<?= $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Enregistrer une offre reçue</h1>
<div class="subtitle">Fournisseur : <?= View::e($consultation['fournisseur_nom']) ?> — Dossier <?= View::e($consultation['dossier_reference']) ?></div>

<div class="card" style="max-width:760px">
  <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/offres">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <div class="form-row">
      <div class="form-group"><label>Montant total de l'offre</label><input type="number" step="0.01" name="montant_total" required></div>
      <div class="form-group">
        <label>Devise</label>
        <select name="devise">
          <?php foreach (['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'] as $d): ?><option><?= $d ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Incoterm négocié</label>
        <select name="incoterm_negocie">
          <option value="">— Non précisé —</option>
          <?php foreach (Demande::INCOTERMS as $code => $label): ?>
            <option value="<?= $code ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Délai de livraison annoncé</label><input type="text" name="delai_livraison" placeholder="ex: 4 à 6 semaines"></div>
    </div>
    <div class="form-group"><label>Validité de l'offre</label><input type="date" name="validite_offre"></div>

    <details open style="margin:16px 0">
      <summary style="cursor:pointer;font-weight:600;color:#374151">Comparaison détaillée (optionnel, mais utile pour le Comparateur)</summary>
      <div style="margin-top:12px">
        <div class="form-row">
          <div class="form-group"><label>Pays d'origine</label><input type="text" name="pays_origine"></div>
          <div class="form-group"><label>Lieu de départ</label><input type="text" name="lieu_depart" placeholder="ex: Guangzhou"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Quantité minimale (MOQ)</label><input type="text" name="quantite_min"></div>
          <div class="form-group"><label>Disponibilité</label><input type="text" name="disponibilite" placeholder="ex: en stock / à produire, 3 semaines"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Poids (kg)</label><input type="number" step="0.01" name="poids_kg"></div>
          <div class="form-group"><label>Nombre de colis</label><input type="number" name="nombre_colis"></div>
          <div class="form-group"><label>Volume (m³)</label><input type="number" step="0.001" name="volume_m3"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Conformité technique</label>
            <select name="conformite_technique">
              <option value="">— Non évaluée —</option>
              <?php foreach (Offre::CONFORMITE as $code => $label): ?>
                <option value="<?= $code ?>"><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Conditions de paiement proposées</label>
            <select name="conditions_paiement">
              <option value="">— Non précisées —</option>
              <?php foreach (Client::CONDITIONS_PAIEMENT as $code => $label): ?>
                <option value="<?= $code ?>"><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Garantie</label><input type="text" name="garantie" placeholder="ex: 12 mois pièces"></div>

        <h2 style="font-size:13px;color:#666;margin-top:16px">Coût rendu — frais complémentaires (dans la devise de l'offre)</h2>
        <div class="form-row">
          <div class="form-group"><label>Transport</label><input type="number" step="0.01" name="transport_montant"></div>
          <div class="form-group"><label>Assurance</label><input type="number" step="0.01" name="assurance_montant"></div>
          <div class="form-group"><label>Emballage</label><input type="number" step="0.01" name="emballage_montant"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Douane / droits estimés</label><input type="number" step="0.01" name="douane_montant"></div>
          <div class="form-group"><label>Dédouanement</label><input type="number" step="0.01" name="dedouanement_montant"></div>
          <div class="form-group"><label>Autres frais</label><input type="number" step="0.01" name="autres_frais_montant"></div>
        </div>
      </div>
    </details>

    <div class="form-group">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="margin:0">Détail des articles (optionnel)</label>
        <button type="button" class="btn btn-sm btn-secondary" id="add-item">+ Ligne</button>
      </div>
      <table style="margin-top:10px" id="items-table">
        <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Prix unitaire</th><th></th></tr></thead>
        <tbody id="items-body"></tbody>
      </table>
    </div>

    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

    <button type="submit" class="btn">Enregistrer l'offre</button>
    <a href="/index.php?r=consultations/<?= $consultation['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>

<template id="item-row-template">
  <tr>
    <td><input type="text" name="item_designation[]"></td>
    <td><input type="number" step="0.01" name="item_quantite[]" style="width:90px"></td>
    <td>
      <select name="item_unite[]">
        <option value="">—</option>
        <?php foreach (['Pièce', 'Carton', 'Kg', 'Tonne', 'Litre', 'm³', 'Sac', 'Palette', "Conteneur 20'", "Conteneur 40'"] as $u): ?>
          <option><?= $u ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="number" step="0.01" name="item_prix_unitaire[]" style="width:110px"></td>
    <td><button type="button" class="btn btn-sm btn-secondary remove-item">&times;</button></td>
  </tr>
</template>

<script>
document.getElementById('add-item').addEventListener('click', function () {
  var tpl = document.getElementById('item-row-template');
  var clone = tpl.content.cloneNode(true);
  clone.querySelector('.remove-item').addEventListener('click', function (e) {
    e.target.closest('tr').remove();
  });
  document.getElementById('items-body').appendChild(clone);
});
</script>
