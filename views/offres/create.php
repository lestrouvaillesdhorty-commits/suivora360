<?php use App\Core\View; use App\Models\Demande; use App\Models\Client; use App\Models\Offre; use App\Models\Dossier; ?>
<?php $manuel = $manuel ?? false; $edition = $edition ?? false; ?>
<?php if ($manuel || $edition): ?>
<a href="/index.php?r=dossiers/<?= (int) $dossier['id'] ?><?= $edition ? '&onglet=achats&sous=offres' : '&onglet=achats' ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>
<?php else: ?>
<a href="/index.php?r=consultations/<?= $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>
<?php endif; ?>

<?php
$typeDossier = $consultation['type_dossier'] ?? 'autre';
$libelleFournisseur = Dossier::libelleFournisseur($typeDossier);
// [ajouté 06/10, étape 2 du découpage Dossiers] $offrePrecedente (passée
// par OffreController::create() via ?version_de=<offreId>) préremplit le
// formulaire avec les valeurs de la version qu'on révise — le fournisseur
// a renvoyé une offre modifiée sur la même consultation, demande
// explicite de Marie Laure le 06/10. v() lit la valeur précédente si
// présente, sinon une chaîne vide (formulaire neutre pour une 1ère offre).
$offrePrecedente = $offrePrecedente ?? null;
$v = fn(string $champ) => $offrePrecedente[$champ] ?? '';
?>
<h1 style="margin-top:8px"><?= $edition ? 'Modifier l\'offre ' . View::e($offrePrecedente['reference']) : ($manuel ? 'Saisir une offre manuellement' : ($offrePrecedente ? 'Nouvelle version (v' . ((int) $offrePrecedente['version'] + 1) . ') de l\'offre ' . View::e($offrePrecedente['reference']) : 'Enregistrer une offre reçue')) ?></h1>
<div class="subtitle"><?php if ($manuel): ?>Prix trouvé sur internet, dans un catalogue ou par téléphone — sans consultation envoyée. Dossier <?= View::e($consultation['dossier_reference']) ?><?php else: ?><?= View::e($libelleFournisseur) ?> : <?= View::e($consultation['fournisseur_nom']) ?> — Dossier <?= View::e($consultation['dossier_reference']) ?><?php endif; ?></div>
<?php if ($edition): ?>
  <div class="info-note" style="margin-top:10px;margin-bottom:0">Vous corrigez l'offre en place (v<?= (int) $offrePrecedente['version'] ?>) : aucune nouvelle version n'est créée. Si le fournisseur a envoyé une offre révisée, utilisez plutôt « + Nouvelle version ».</div>
<?php elseif ($offrePrecedente): ?>
  <div class="info-note" style="margin-top:10px;margin-bottom:0">Le formulaire est prérempli avec les valeurs de la v<?= (int) $offrePrecedente['version'] ?> — l'ancienne version sera conservée dans l'historique et marquée « Remplacée ».</div>
<?php endif; ?>

<div class="card" style="max-width:760px">
  <form method="post" enctype="multipart/form-data" action="<?= $edition ? '/index.php?r=offres/' . (int) $offrePrecedente['id'] . '/modifier' : ($manuel ? '/index.php?r=dossiers/' . (int) $dossier['id'] . '/offres/manuelle' : '/index.php?r=consultations/' . $consultation['id'] . '/offres') ?>">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <?php if (!$offrePrecedente || $edition): ?>
    <div class="card" id="blocIa" style="background:#f6f8ff;box-shadow:none;border:1px solid #dbe3fb;padding:12px 14px;margin-bottom:14px">
      <strong>Fichier de l'offre (PDF, photo, Excel, Word)</strong>
      <div style="font-size:13px;color:#555;margin:4px 0 8px">Le fichier est <strong>enregistré dans les documents du dossier</strong> (Offres fournisseurs) quand vous enregistrez l'offre.<?php if (\App\Services\AiExtracteur::estDisponible()): ?> Vous pouvez aussi le faire lire par l'IA pour préremplir le formulaire : vous relisez et corrigez avant d'enregistrer.<?php endif; ?></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="file" name="fichier_offre" id="iaFichier" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt" style="max-width:100%">
        <?php if (\App\Services\AiExtracteur::estDisponible()): ?><button type="button" class="btn btn-sm" id="iaLire">Lire avec l'IA</button><?php endif; ?>
      </div>
      <div id="iaMessage" style="font-size:13px;margin-top:8px"></div>
    </div>
    <?php endif; ?>
    <?php if ($manuel): ?>
    <div class="form-row">
      <div class="form-group">
        <label>Source du prix *</label>
        <select name="source_type" required>
          <?php foreach (Offre::SOURCES as $code => $lib): ?><option value="<?= $code ?>"><?= View::e($lib) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Lien de la page (si site web)</label><input type="url" name="source_url" placeholder="https://…" maxlength="500"></div>
    </div>
    <div class="form-group" style="max-width:260px"><label>Prix consulté le *</label><input type="date" name="source_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
    <div class="form-row">
      <div class="form-group">
        <label>Vendeur — fournisseur existant</label>
        <select name="fournisseur_id">
          <option value="">— Aucun (nouveau vendeur ci-contre) —</option>
          <?php foreach ($fournisseurs as $f): ?><option value="<?= (int) $f['id'] ?>"><?= View::e($f['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>…ou nouveau vendeur</label><input type="text" name="nouveau_vendeur" placeholder="Ex : Alibaba — Shenzhen Tech Co"></div>
    </div>
    <div class="info-note" style="margin-bottom:12px">Un nouveau vendeur est créé comme fournisseur « à qualifier ». Ne saisissez que ce que la source indique : laissez vide tout ce qui n'est pas précisé.</div>
    <?php endif; ?>
    <?php if ($edition): ?>
      <div class="form-group"><label>Fournisseur</label><div><strong><?= View::e($consultation['fournisseur_nom'] ?? '') ?></strong> <span style="color:#888;font-size:12px">(non modifiable)</span></div></div>
      <?php if (!empty($offrePrecedente['source_type'])): ?>
      <div class="form-row">
        <div class="form-group"><label>Source du prix</label>
          <select name="source_type"><?php foreach (Offre::SOURCES as $code => $lib): ?><option value="<?= $code ?>" <?= $offrePrecedente['source_type'] === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Lien de la page</label><input type="url" name="source_url" maxlength="500" value="<?= View::e((string) ($offrePrecedente['source_url'] ?? '')) ?>"></div>
        <div class="form-group"><label>Prix consulté le</label><input type="date" name="source_date" value="<?= View::e((string) ($offrePrecedente['source_date'] ?? '')) ?>" max="<?= date('Y-m-d') ?>"></div>
      </div>
      <?php endif; ?>
    <?php elseif ($offrePrecedente): ?>
      <input type="hidden" name="offre_precedente_id" value="<?= (int) $offrePrecedente['id'] ?>">
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group"><label>Montant total de l'offre</label><input type="number" step="0.01" name="montant_total" value="<?= View::e((string) $v('montant_total')) ?>" required></div>
      <div class="form-group">
        <label>Devise</label>
        <select name="devise">
          <?php foreach (['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'] as $d): ?><option <?= $v('devise') === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Incoterm négocié</label>
        <select name="incoterm_negocie">
          <option value="">— Non précisé —</option>
          <?php foreach (Demande::INCOTERMS as $code => $label): ?>
            <option value="<?= $code ?>" <?= $v('incoterm_negocie') === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Délai de livraison annoncé</label><input type="text" name="delai_livraison" value="<?= View::e((string) $v('delai_livraison')) ?>" placeholder="ex: 4 à 6 semaines"></div>
    </div>
    <div class="form-group"><label>Validité de l'offre</label><input type="date" name="validite_offre" value="<?= View::e((string) $v('validite_offre')) ?>"></div>

    <?php if ($typeDossier === 'transport_logistique'): ?>
      <div class="form-group"><label>Mode de transport</label><input type="text" name="mode_transport" value="<?= View::e((string) $v('mode_transport')) ?>" placeholder="ex: Maritime FCL, Aérien, Routier"></div>
    <?php elseif ($typeDossier === 'prestation_entreprise'): ?>
      <div class="form-group"><label>Périmètre de mission proposé</label><textarea name="perimetre_mission" rows="2" placeholder="ex: Représentation commerciale, zone, durée..."><?= View::e((string) $v('perimetre_mission')) ?></textarea></div>
    <?php endif; ?>

    <details open style="margin:16px 0">
      <summary style="cursor:pointer;font-weight:600;color:#374151">Comparaison détaillée (optionnel, mais utile pour le Comparateur)</summary>
      <div style="margin-top:12px">
        <div class="form-row">
          <div class="form-group"><label>Pays d'origine</label><input type="text" name="pays_origine" value="<?= View::e((string) $v('pays_origine')) ?>"></div>
          <div class="form-group"><label>Lieu de départ</label><input type="text" name="lieu_depart" value="<?= View::e((string) $v('lieu_depart')) ?>" placeholder="ex: Guangzhou"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Quantité minimale (MOQ)</label><input type="text" name="quantite_min" value="<?= View::e((string) $v('quantite_min')) ?>"></div>
          <div class="form-group"><label>Disponibilité</label><input type="text" name="disponibilite" value="<?= View::e((string) $v('disponibilite')) ?>" placeholder="ex: en stock / à produire, 3 semaines"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Poids (kg)</label><input type="number" step="0.01" name="poids_kg" value="<?= View::e((string) $v('poids_kg')) ?>"></div>
          <div class="form-group"><label>Nombre de colis</label><input type="number" name="nombre_colis" value="<?= View::e((string) $v('nombre_colis')) ?>"></div>
          <div class="form-group"><label>Volume (m³)</label><input type="number" step="0.001" name="volume_m3" value="<?= View::e((string) $v('volume_m3')) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Conformité technique</label>
            <select name="conformite_technique">
              <option value="">— Non évaluée —</option>
              <?php foreach (Offre::CONFORMITE as $code => $label): ?>
                <option value="<?= $code ?>" <?= $v('conformite_technique') === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Conditions de paiement proposées</label>
            <select name="conditions_paiement">
              <option value="">— Non précisées —</option>
              <?php foreach (Client::CONDITIONS_PAIEMENT as $code => $label): ?>
                <option value="<?= $code ?>" <?= $v('conditions_paiement') === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Garantie</label><input type="text" name="garantie" value="<?= View::e((string) $v('garantie')) ?>" placeholder="ex: 12 mois pièces"></div>

        <h2 style="font-size:13px;color:#666;margin-top:16px">Coût rendu — frais complémentaires (dans la devise de l'offre)</h2>
        <div class="form-row">
          <div class="form-group"><label>Transport</label><input type="number" step="0.01" name="transport_montant" value="<?= View::e((string) $v('transport_montant')) ?>"></div>
          <div class="form-group"><label>Assurance</label><input type="number" step="0.01" name="assurance_montant" value="<?= View::e((string) $v('assurance_montant')) ?>"></div>
          <div class="form-group"><label>Emballage</label><input type="number" step="0.01" name="emballage_montant" value="<?= View::e((string) $v('emballage_montant')) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Douane / droits estimés</label><input type="number" step="0.01" name="douane_montant" value="<?= View::e((string) $v('douane_montant')) ?>"></div>
          <div class="form-group"><label>Dédouanement</label><input type="number" step="0.01" name="dedouanement_montant" value="<?= View::e((string) $v('dedouanement_montant')) ?>"></div>
          <div class="form-group"><label>Autres frais</label><input type="number" step="0.01" name="autres_frais_montant" value="<?= View::e((string) $v('autres_frais_montant')) ?>"></div>
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
        <tbody id="items-body">
          <?php foreach ($itemsPrecedents ?? [] as $item): ?>
          <tr>
            <td><input type="text" name="item_designation[]" value="<?= View::e($item['designation']) ?>"></td>
            <td><input type="number" step="0.01" name="item_quantite[]" style="width:90px" value="<?= View::e((string) ($item['quantite'] ?? '')) ?>"></td>
            <td>
              <select name="item_unite[]">
                <option value="">—</option>
                <?php foreach (['Pièce', 'Carton', 'Kg', 'Tonne', 'Litre', 'm³', 'Sac', 'Palette', "Conteneur 20'", "Conteneur 40'"] as $u): ?>
                  <option <?= $item['unite'] === $u ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" step="0.01" name="item_prix_unitaire[]" style="width:110px" value="<?= View::e((string) ($item['prix_unitaire'] ?? '')) ?>"></td>
            <td><button type="button" class="btn btn-sm btn-secondary remove-item">&times;</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e((string) $v('notes')) ?></textarea></div>

    <button type="submit" class="btn"><?= $edition ? 'Enregistrer les modifications' : 'Enregistrer l\'offre' ?></button>
    <a href="<?= $edition ? '/index.php?r=dossiers/' . (int) $dossier['id'] . '&onglet=achats&sous=offres' : ($manuel ? '/index.php?r=dossiers/' . (int) $dossier['id'] . '&onglet=achats' : '/index.php?r=consultations/' . $consultation['id']) ?>" class="btn btn-secondary">Annuler</a>
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
  document.getElementById('items-body').appendChild(clone);
});
// [modifié 06/10, étape 2 du découpage Dossiers] Délégation sur le tbody
// plutôt qu'un listener par bouton : les lignes préremplies depuis une
// version précédente (voir $itemsPrecedents) existent déjà au chargement,
// pas seulement celles ajoutées dynamiquement via le template.
document.getElementById('items-body').addEventListener('click', function (e) {
  if (e.target.classList.contains('remove-item')) {
    e.target.closest('tr').remove();
  }
});
</script>

<script>
(function () {
  var btn = document.getElementById('iaLire');
  if (!btn) return;
  var form = btn.closest('form'), msg = document.getElementById('iaMessage');
  var token = <?= json_encode($csrfToken) ?>;
  function marquer(el) { el.style.background = '#fff8dc'; }
  function poser(nom, val) {
    var el = form.querySelector('[name="' + nom + '"]');
    if (!el || val === undefined || val === null || val === '') return false;
    if (el.tagName === 'SELECT') {
      var ok = false;
      for (var i = 0; i < el.options.length; i++) {
        if (el.options[i].value === String(val) || el.options[i].text === String(val)) { el.selectedIndex = i; ok = true; break; }
      }
      if (!ok) return false;
    } else { el.value = val; }
    marquer(el); return true;
  }
  btn.addEventListener('click', function () {
    var f = document.getElementById('iaFichier').files[0];
    if (!f) { msg.style.color = '#991b1b'; msg.textContent = 'Choisissez d\u2019abord un fichier.'; return; }
    var fd = new FormData(); fd.append('csrf_token', token); fd.append('fichier', f);
    btn.disabled = true; msg.style.color = '#555'; msg.textContent = 'Lecture en cours… (jusqu\u2019à une minute)';
    fetch('/index.php?r=offres/extraire-ia', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false;
        if (d.erreur) { msg.style.color = '#991b1b'; msg.textContent = d.erreur; return; }
        var c = d.champs, n = 0;
        Object.keys(c).forEach(function (k) {
          if (k === 'articles' || k === 'fournisseur') return;
          if (poser(k, c[k])) n++;
        });
        // Vendeur : fournisseur existant au nom proche, sinon nouveau vendeur (saisie manuelle seulement)
        var sel = form.querySelector('select[name="fournisseur_id"]'), nv = form.querySelector('[name="nouveau_vendeur"]');
        if (c.fournisseur && sel && nv && !sel.value) {
          var cible = c.fournisseur.toLowerCase(), trouve = null;
          for (var i = 1; i < sel.options.length; i++) {
            var o = sel.options[i].text.toLowerCase();
            if (o === cible || o.indexOf(cible) >= 0 || cible.indexOf(o) >= 0) { trouve = sel.options[i]; break; }
          }
          if (trouve) { sel.value = trouve.value; marquer(sel); } else { nv.value = c.fournisseur; marquer(nv); }
          n++;
        }
        var tbody = document.getElementById('items-body'), tpl = document.getElementById('item-row-template');
        if (c.articles && c.articles.length) {
          Array.prototype.slice.call(tbody.querySelectorAll('tr')).forEach(function (tr) {
            var des = tr.querySelector('[name="item_designation[]"]');
            if (!des || !des.value.trim()) tr.remove();
          });
          c.articles.forEach(function (a) {
            tbody.appendChild(tpl.content.cloneNode(true));
            var tr = tbody.lastElementChild;
            tr.querySelector('[name="item_designation[]"]').value = a.designation;
            if (a.quantite !== null) tr.querySelector('[name="item_quantite[]"]').value = a.quantite;
            if (a.prix_unitaire !== null) tr.querySelector('[name="item_prix_unitaire[]"]').value = a.prix_unitaire;
            var u = tr.querySelector('[name="item_unite[]"]');
            for (var i = 0; i < u.options.length; i++) { if (u.options[i].text.toLowerCase() === String(a.unite).toLowerCase()) { u.selectedIndex = i; break; } }
            tr.querySelectorAll('input').forEach(marquer);
          });
          n += c.articles.length;
        }
        var det = form.querySelector('details'); if (det) det.open = true;
        msg.style.color = '#166534';
        msg.textContent = 'Formulaire prérempli (' + n + ' éléments, surlignés en jaune). Vérifiez chaque valeur avant d\u2019enregistrer.';
      })
      .catch(function () { btn.disabled = false; msg.style.color = '#991b1b'; msg.textContent = 'Lecture impossible, réessayez.'; });
  });
})();
</script>
