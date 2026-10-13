<?php use App\Core\View;
$presel = (int) ($fournisseurPreselection ?? 0); ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Nouvelle consultation fournisseur</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<div class="card" style="max-width:640px">
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/consultations" id="formConsultation" data-brouillon="consultation-<?= (int) $dossier['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label for="selFournisseur">Fournisseur consulté</label>
      <select name="fournisseur_id" id="selFournisseur">
        <option value="">— Sélectionner —</option>
        <?php foreach ($fournisseurs as $f): ?>
          <option value="<?= $f['id'] ?>" <?= $presel === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?><?= $f['pays'] ? ' (' . View::e($f['pays']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (\App\Core\Auth::canWrite()): ?>
      <div style="margin-top:6px"><a href="#" id="lienNouveauFournisseur" style="font-size:13px">Le fournisseur n’est pas dans la liste ? Le créer ici</a></div>
      <?php endif; ?>
    </div>

    <div id="blocFournisseur" class="card" style="display:none;background:#f8f7fc;box-shadow:none;border:1px solid #e5e3f0">
      <h2 style="font-size:15px">Nouveau fournisseur</h2>
      <div class="form-group"><label for="nfNom">Raison sociale *</label><input type="text" id="nfNom" maxlength="150"></div>
      <div class="form-row">
        <div class="form-group"><label for="nfType">Type de partenaire</label>
          <select id="nfType"><option value="">—</option>
            <?php foreach (\App\Models\Fournisseur::TYPES as $code => $lib): ?><option value="<?= $code ?>"><?= View::e($lib) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group"><label for="nfPays">Pays</label><input type="text" id="nfPays" maxlength="80"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="nfEmail">E-mail</label><input type="email" id="nfEmail"></div>
        <div class="form-group"><label for="nfTel">Téléphone</label><input type="text" id="nfTel" placeholder="+33 …"></div>
      </div>
      <div id="nfMessage" style="font-size:13px;margin-bottom:8px"></div>
      <button type="button" class="btn btn-sm" id="nfEnregistrer">Créer et sélectionner</button>
      <button type="button" class="btn btn-sm btn-secondary" id="nfAnnuler">Annuler</button>
      <div style="font-size:12px;color:#888;margin-top:6px">Fiche créée « à qualifier » : complétez-la ensuite depuis Fournisseurs. La saisie de la consultation est conservée.</div>
    </div>

    <?php if (\App\Core\Auth::canWrite()): ?>
    <details class="card" style="background:#f8f9fb;box-shadow:none;border:1px solid #e3e6ec;padding:10px 14px">
      <summary style="cursor:pointer;font-weight:600">Consultation sur internet (Alibaba, site web…) ou plusieurs vendeurs</summary>
      <div style="margin-top:10px">
        <div class="form-row">
          <div class="form-group"><label for="srcType">Où avez-vous consulté ?</label>
            <select name="source_type" id="srcType">
              <option value="">Fournisseur habituel (rien à préciser)</option>
              <?php foreach (\App\Models\Offre::SOURCES as $code => $lib): ?><option value="<?= View::e($code) ?>"><?= View::e($lib) ?></option><?php endforeach; ?>
            </select></div>
          <div class="form-group"><label for="srcUrl">Lien (page, boutique, annonce)</label><input type="url" name="source_url" id="srcUrl" placeholder="https://…"></div>
        </div>
        <div class="form-group"><label for="autresVendeurs">Autres vendeurs consultés (un nom par ligne)</label>
          <textarea name="autres_vendeurs" id="autresVendeurs" rows="3" placeholder="Ex. : Shenzhen Tech Co.&#10;Guangzhou Trading Ltd"></textarea>
          <div style="font-size:12px;color:#888;margin-top:4px">Chaque nom crée une fiche fournisseur « à qualifier » et sa propre consultation, avec la même source et le même lien. Le fournisseur choisi ci-dessus est facultatif si vous remplissez cette liste.</div>
        </div>
      </div>
    </details>
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group"><label>Date d'envoi</label><input type="date" name="date_envoi" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <?php if (!empty($apercuArticles) || !empty($apercuDetails)): ?>
    <details class="card" style="background:#f8f9fb;box-shadow:none;border:1px solid #e3e6ec;padding:10px 14px" open>
      <summary style="cursor:pointer;font-weight:600">Ce qui est transmis au fournisseur (depuis la demande)</summary>
      <div style="font-size:13px;margin-top:8px">
        <?php if (!empty($apercuArticles)): ?>
          <?php $choixPossible = \App\Models\ConsultationFournisseur::colonneArticles(); ?>
          <div style="margin-bottom:6px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
            <strong>Articles à envoyer à ce fournisseur (<?= count($apercuArticles) ?> dans la demande)</strong>
            <?php if ($choixPossible && count($apercuArticles) > 1): ?>
              <span style="font-size:12px"><a href="#" data-cocher="1">Tout cocher</a> · <a href="#" data-cocher="0">Tout décocher</a></span>
            <?php endif; ?>
          </div>
          <?php if ($choixPossible): ?><input type="hidden" name="articles_choix" value="1"><?php endif; ?>
          <div style="margin:0 0 8px">
            <?php foreach ($apercuArticles as $a): ?>
              <label style="display:flex;gap:8px;align-items:flex-start;font-weight:400;padding:3px 0">
                <?php if ($choixPossible): ?><input type="checkbox" name="articles[]" value="<?= (int) $a['id'] ?>" checked style="width:auto;margin-top:3px"><?php endif; ?>
                <span><?= View::e(trim(($a['quantite'] !== null && $a['quantite'] !== '' ? rtrim(rtrim(number_format((float) $a['quantite'], 2, ',', ''), '0'), ',') . ' ' : '') . ($a['unite'] ?? '') . ' ' . $a['designation'])) ?>
                <?php $pre = array_filter([$a['conditionnement'] ?? '', $a['reference'] ? 'réf. ' . $a['reference'] : '', $a['marque'] ?? '', $a['caracteristiques'] ?? '']); if ($pre): ?><span style="color:#777">— <?= View::e(implode(' · ', $pre)) ?></span><?php endif; ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($choixPossible): ?><div style="font-size:12px;color:#888;margin-bottom:8px">Décochez les articles que ce fournisseur ne fournit pas : il ne verra que ceux qui sont cochés. Pour consulter plusieurs fournisseurs sur des articles différents, créez une consultation par fournisseur.</div><?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($apercuDetails)): ?>
          <div style="margin-bottom:4px"><strong>Détails</strong></div>
          <?php foreach ($apercuDetails as $lib => $val): ?><div><?= View::e($lib) ?> : <?= View::e($val) ?></div><?php endforeach; ?>
        <?php endif; ?>
        <div style="color:#888;margin-top:8px">Le récapitulatif envoyé (lien sécurisé) reprend aussi la destination et l'incoterm. Le nom du client est masqué par défaut. Vos notes internes ne sont jamais transmises. Complétez ci-dessous seulement ce qui s'ajoute.</div>
      </div>
    </details>
    <?php endif; ?>
    <div class="form-group">
      <label>Précisions complémentaires pour le fournisseur (spécifications, exigences, variantes acceptées…)</label>
      <textarea name="articles_demandes" rows="4" placeholder="Ex. : emballage export, certificat d'origine demandé, échantillon avant commande, alternatives acceptées…"></textarea>
    </div>
    <div class="form-group">
      <label>Notes internes</label>
      <textarea name="notes" rows="3"></textarea>
    </div>
    <button type="submit" class="btn">Enregistrer la consultation</button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>

<script>
(function () {
  var lien = document.getElementById('lienNouveauFournisseur');
  if (!lien) return;
  var bloc = document.getElementById('blocFournisseur'), msg = document.getElementById('nfMessage');
  var sel = document.getElementById('selFournisseur');
  var token = <?= json_encode($csrfToken) ?>, filiale = <?= (int) $dossier['filiale_id'] ?>;
  lien.addEventListener('click', function (e) { e.preventDefault(); bloc.style.display = 'block'; document.getElementById('nfNom').focus(); });
  document.getElementById('nfAnnuler').addEventListener('click', function () { bloc.style.display = 'none'; msg.textContent = ''; });
  function envoyer(force) {
    var fd = new FormData();
    fd.append('csrf_token', token); fd.append('filiale_id', filiale);
    fd.append('nom', document.getElementById('nfNom').value);
    fd.append('type', document.getElementById('nfType').value);
    fd.append('pays', document.getElementById('nfPays').value);
    fd.append('email', document.getElementById('nfEmail').value);
    fd.append('telephone', document.getElementById('nfTel').value);
    if (force) fd.append('force', '1');
    fetch('/index.php?r=fournisseurs/creation-rapide', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.erreur) { msg.style.color = '#991b1b'; msg.textContent = d.erreur; return; }
        if (d.doublon) {
          msg.style.color = '#92400e'; msg.innerHTML = '';
          msg.appendChild(document.createTextNode('Un fournisseur ressemble déjà à celui-ci : « ' + d.existant.nom + ' ». '));
          var a = document.createElement('a'); a.href = '#'; a.textContent = 'Utiliser l’existant';
          a.onclick = function (ev) { ev.preventDefault(); ajouter(d.existant.id, d.existant.nom, ''); };
          var b = document.createElement('a'); b.href = '#'; b.textContent = ' · Créer quand même'; b.onclick = function (ev) { ev.preventDefault(); envoyer(true); };
          msg.appendChild(a); msg.appendChild(b); return;
        }
        ajouter(d.id, d.nom, d.pays);
      })
      .catch(function () { msg.style.color = '#991b1b'; msg.textContent = 'Création impossible, réessayez.'; });
  }
  function ajouter(id, nom, pays) {
    var existe = null;
    for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].value == id) existe = sel.options[i]; }
    if (!existe) { existe = document.createElement('option'); existe.value = id; existe.textContent = nom + (pays ? ' (' + pays + ')' : ''); sel.appendChild(existe); }
    sel.value = String(id); bloc.style.display = 'none'; msg.textContent = '';
  }
  document.getElementById('nfEnregistrer').addEventListener('click', function () { envoyer(false); });
})();
</script>
<script>
Array.prototype.forEach.call(document.querySelectorAll('[data-cocher]'), function (a) {
  a.addEventListener('click', function (e) {
    e.preventDefault();
    var etat = a.getAttribute('data-cocher') === '1';
    Array.prototype.forEach.call(document.querySelectorAll('input[name="articles[]"]'), function (c) { c.checked = etat; });
  });
});
</script>
