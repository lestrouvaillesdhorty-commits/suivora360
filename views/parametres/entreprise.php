<?php use App\Core\View; use App\Models\EntrepriseInfo;
$pays = (string) ($info['pays'] ?? ''); $couleur = EntrepriseInfo::couleurEffective($info); $mep = EntrepriseInfo::miseEnPage($info);
$v50 = \App\Core\Schema::colonne('entreprise_infos', 'pays'); ?>
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
    <?php if ($v50): ?>
    <div class="form-row">
      <div class="form-group"><label>Forme juridique</label><input type="text" name="forme_juridique" value="<?= View::e($info['forme_juridique']) ?>" placeholder="SAS, SARL, SA, entreprise individuelle…"></div>
      <div class="form-group"><label>Capital</label><input type="text" name="capital" value="<?= View::e($info['capital']) ?>" placeholder="10 000 €"></div>
    </div>
    <?php endif; ?>
    <div class="form-group"><label>Adresse</label><textarea name="adresse" rows="2" placeholder="Rue, code postal, ville, pays"><?= View::e($info['adresse']) ?></textarea></div>
    <div class="form-row">
      <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" value="<?= View::e($info['telephone']) ?>"></div>
      <div class="form-group"><label>E-mail</label><input type="text" name="email" value="<?= View::e($info['email']) ?>"></div>
    </div>
    <?php if ($v50): ?>
    <div class="form-row">
      <div class="form-group"><label>Pays de l'entreprise</label>
        <select name="pays" id="ent-pays">
          <option value="">— Choisir —</option>
          <?php foreach (EntrepriseInfo::PAYS as $code => $p): ?><option value="<?= $code ?>" <?= $pays === $code ? 'selected' : '' ?> data-ident="<?= View::e($p['ident']) ?>" data-regimes="<?= View::e(json_encode($p['regimes'], JSON_UNESCAPED_UNICODE)) ?>"><?= View::e($p['nom']) ?></option><?php endforeach; ?>
        </select>
        <div class="hint">Propose le nom des numéros à indiquer ; tout reste modifiable.</div></div>
      <div class="form-group"><label>Régime de TVA</label>
        <input type="text" name="regime_tva" id="ent-regime" list="ent-regimes" value="<?= View::e($info['regime_tva']) ?>" placeholder="Ex. : TVA sur les encaissements">
        <datalist id="ent-regimes"></datalist></div>
    </div>
    <?php endif; ?>
    <div class="form-group"><label>Numéros d'immatriculation et mentions légales (une par ligne)</label>
      <textarea name="identifiants" id="ent-ident" rows="3" placeholder="N° TVA : …&#10;RCCM / SIRET : …&#10;NIF : …"><?= View::e($info['identifiants']) ?></textarea>
      <div class="hint">Repris dans le pied de page de chaque devis. Les mentions obligatoires dépendent de votre pays : faites-les valider par votre comptable.</div></div>
    <?php if ($v50): ?>
    <div class="form-group"><label>Texte libre en pied de page (optionnel)</label>
      <textarea name="mentions_pied" rows="2" placeholder="Ex. : pénalités de retard, indemnité forfaitaire de recouvrement…"><?= View::e($info['mentions_pied']) ?></textarea></div>
    <?php endif; ?>
    <div class="form-group"><label>Logo (PNG ou JPG, 2 Mo maximum)</label>
      <?php if ($logoUri): ?><div style="margin:6px 0"><img src="<?= $logoUri ?>" alt="Logo actuel" style="max-height:70px;max-width:260px;border:1px solid #DCE9EC;border-radius:10px;padding:6px;background:#fff"></div>
        <label style="font-weight:500"><input type="checkbox" name="supprimer_logo" value="1"> Retirer le logo actuel</label><?php endif; ?>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,image/png,image/jpeg">
      <div class="hint">Le logo s'affiche sur l'aperçu et l'impression du devis. Le PDF téléchargé reprend le texte de l'en-tête, sans le logo.</div></div>
  </div>

  <?php if ($v50): ?>
  <div class="card">
    <h2>Apparence des documents</h2>
    <div class="form-group"><label>Couleur principale</label>
      <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px">
        <?php foreach (EntrepriseInfo::COULEURS_PROPOSEES as $c): ?><button type="button" class="ent-sw" data-c="<?= $c ?>" title="<?= $c ?>" style="width:30px;height:30px;border-radius:8px;border:0;background:<?= $c ?>;cursor:pointer"></button><?php endforeach; ?>
        <input type="color" name="couleur" id="ent-couleur" value="<?= View::e($couleur) ?>" style="width:44px;height:34px;padding:0;border:1px solid #C9D3DD;border-radius:8px">
        <?php if ($logoUri): ?><button type="button" class="btn btn-sm btn-secondary" id="ent-detect">Détecter depuis mon logo</button><?php endif; ?>
      </div>
      <div class="hint">Couleur du bandeau, du tableau et du total sur le devis et le bon de commande (aperçu et PDF).</div></div>
    <div class="form-group"><label>Mise en page</label>
      <select name="mise_en_page" id="ent-mep" style="max-width:260px">
        <?php foreach (EntrepriseInfo::MISES_EN_PAGE as $k => $l): ?><option value="<?= $k ?>" <?= $mep === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
      </select>
      <div class="hint">Classique : bandeau fin sous l'en-tête. Moderne : grand bandeau coloré. Sobre : sans aplat de couleur. Le PDF reprend la couleur ; l'aperçu et l'impression reprennent aussi la mise en page.</div></div>
  </div>
  <?php endif; ?>

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
<script>
(function () {
  var p = document.getElementById('ent-pays'); if (!p) return;
  var ident = document.getElementById('ent-ident'), dl = document.getElementById('ent-regimes');
  function maj(initial) {
    var o = p.options[p.selectedIndex]; if (!o || !o.dataset.ident) { return; }
    ident.placeholder = o.dataset.ident;
    dl.innerHTML = ''; try { JSON.parse(o.dataset.regimes).forEach(function (r) { var x = document.createElement('option'); x.value = r; dl.appendChild(x); }); } catch (e) {}
  }
  p.addEventListener('change', maj); maj();
  var col = document.getElementById('ent-couleur');
  document.querySelectorAll('.ent-sw').forEach(function (b) { b.addEventListener('click', function () { col.value = b.dataset.c; }); });
  var d = document.getElementById('ent-detect');
  if (d) d.addEventListener('click', function () {
    var img = document.querySelector('img[alt="Logo actuel"]'); if (!img) return;
    var c = document.createElement('canvas'), w = 48; c.width = c.height = w; var g = c.getContext('2d'); g.drawImage(img, 0, 0, w, w);
    var px = g.getImageData(0, 0, w, w).data, best = null, bs = -1, buckets = {};
    for (var i = 0; i < px.length; i += 4) {
      var r = px[i], gg = px[i + 1], b = px[i + 2], a = px[i + 3]; if (a < 200) continue;
      var mx = Math.max(r, gg, b), mn = Math.min(r, gg, b), sat = mx ? (mx - mn) / mx : 0; if (mx > 235 && sat < 0.15) continue; if (mx < 30) continue;
      var k = (r >> 5) + ',' + (gg >> 5) + ',' + (b >> 5), e = buckets[k] || (buckets[k] = { n: 0, r: 0, g: 0, b: 0, s: 0 }); e.n++; e.r += r; e.g += gg; e.b += b; e.s += sat;
    }
    for (var key in buckets) { var e2 = buckets[key], sc = e2.n * (0.3 + e2.s / e2.n); if (sc > bs) { bs = sc; best = e2; } }
    if (!best) return;
    var h = function (v) { return ('0' + Math.round(v / best.n).toString(16)).slice(-2); };
    col.value = ('#' + h(best.r) + h(best.g) + h(best.b)).toUpperCase();
  });
})();
</script>
