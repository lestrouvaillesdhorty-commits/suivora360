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
    <div class="form-group">
      <label>Articles / besoins communiqués au fournisseur</label>
      <textarea name="articles_demandes" rows="4" placeholder="Décrivez les articles, quantités et spécifications transmises pour consultation..."></textarea>
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
