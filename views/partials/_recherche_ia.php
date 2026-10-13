<?php
use App\Core\View;

/**
 * Bloc « Chercher les informations avec l'IA » (fournisseurs et clients, 13/10).
 * Variables : $rechCible, $rechFormId, $rechAvecSite, $rechTexte (champs texte/liste à remplir), $rechCoches (groupes de cases), $csrfToken.
 */
?>
      <div class="card" id="blocRechercheIa" style="background:#f6f8ff;border:1px solid #dbe3fb;box-shadow:none">
        <strong>Chercher les informations avec l'IA</strong>
        <?php if (\App\Services\AiExtracteur::estDisponible()): ?>
        <div style="font-size:13px;color:#555;margin:4px 0 10px">Saisissez le nom (et le pays<?= $rechAvecSite ? '' : ' ou le site internet' ?> si vous les avez) : l'IA cherche les informations publiques et remplit <strong>les champs vides</strong>. Vous complétez le reste et vérifiez tout avant d'enregistrer.</div>
        <?php if ($rechAvecSite): ?>
        <div class="form-group" style="max-width:360px"><label for="rechIaSite">Site internet de l'entreprise (facultatif, non enregistré)</label><input type="text" id="rechIaSite" placeholder="www.exemple.com"></div>
        <?php endif; ?>
        <button type="button" class="btn btn-sm" id="rechIaBtn">Rechercher et préremplir</button>
        <div id="rechIaMsg" style="font-size:13px;margin-top:8px"></div>
        <?php else: ?>
        <div style="font-size:13px;color:#888;margin-top:4px">Recherche automatique non activée sur cette installation (clé IA absente).</div>
        <?php endif; ?>
      </div>


<script>
(function () {
  var btn = document.getElementById('rechIaBtn');
  if (!btn) return;
  var form = document.getElementById(<?= json_encode($rechFormId) ?>), msg = document.getElementById('rechIaMsg');
  var token = <?= json_encode($csrfToken) ?>;
  var modifies = {};
  form.addEventListener('input', function (e) { if (e.target.name) modifies[e.target.name] = true; });
  form.addEventListener('change', function (e) { if (e.target.name) modifies[e.target.name] = true; });
  function jaune(el) { el.style.background = '#fff8dc'; }
  function vide(el) { return !el.value || !el.value.trim(); }
  function texte(nom, val) {
    var el = form.elements[nom];
    if (!el || el.length !== undefined && el.tagName !== 'SELECT') el = form.querySelector('[name="' + nom + '"]');
    if (!el || !val || !vide(el)) return 0;
    if (el.tagName === 'SELECT') {
      for (var i = 0; i < el.options.length; i++) { if (el.options[i].value === val || el.options[i].text === val) { el.selectedIndex = i; jaune(el); return 1; } }
      return 0;
    }
    el.value = val; jaune(el); return 1;
  }
  function coches(nom, vals) {
    var n = 0;
    (vals || []).forEach(function (v) {
      var el = form.querySelector('input[name="' + nom + '[]"][value="' + v + '"]');
      if (el && !el.checked) { el.checked = true; el.parentNode.style.background = '#fff8dc'; n++; }
    });
    return n;
  }
  btn.addEventListener('click', function () {
    var fd = new FormData();
    fd.append('csrf_token', token);
    fd.append('nom', form.querySelector('[name="nom"]').value);
    var champSite = document.getElementById('rechIaSite') || form.querySelector('[name="site_web"]');
    fd.append('cible', <?= json_encode($rechCible) ?>);
    fd.append('site_web', champSite ? champSite.value : '');
    fd.append('pays', form.querySelector('[name="pays"]').value);
    if (!form.querySelector('[name="nom"]').value.trim() && !(champSite && champSite.value.trim())) {
      msg.style.color = '#991b1b'; msg.textContent = 'Saisissez d\u2019abord la raison sociale ou le site internet.'; return;
    }
    btn.disabled = true; msg.style.color = '#555'; msg.textContent = 'Recherche en cours… (jusqu\u2019à deux minutes)';
    fetch('/index.php?r=<?= $rechCible === 'client' ? 'clients' : 'fournisseurs' ?>/recherche-ia', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false;
        if (d.erreur) { msg.style.color = '#991b1b'; msg.textContent = d.erreur; return; }
        var c = d.champs, n = 0;
        // Le pays est prérempli par défaut (pays de la filiale) : l'IA peut le remplacer tant que vous ne l'avez pas saisi.
        if (c.pays && !modifies.pays) { var ep = form.querySelector('[name="pays"]'); if (ep && ep.value !== c.pays) { ep.value = c.pays; jaune(ep); n++; } }
        <?= json_encode(array_merge(['nom'], $rechTexte)) ?>.forEach(function (k) { n += texte(k, c[k]); });
        if (c.tel_numero) {
          var num = form.querySelector('[name="tel_numero"]');
          if (vide(num)) {
            num.value = c.tel_numero; jaune(num); n++;
          }
          var ind = form.querySelector('[name="tel_indicatif"]');
          if (ind && c.tel_indicatif && !modifies.tel_indicatif && ind.value !== c.tel_indicatif) { ind.value = c.tel_indicatif; jaune(ind); }
        }
        <?php foreach ($rechCoches as $rc): ?>n += coches(<?= json_encode($rc) ?>, c[<?= json_encode($rc) ?>]);
        <?php endforeach; ?>
        if (c.description) {
          var notes = form.querySelector('[name="notes"]');
          if (notes && vide(notes)) { notes.value = c.description + '\n(Informations recherchées par l\u2019IA le ' + new Date().toLocaleDateString('fr-FR') + ' \u2014 à vérifier.)'; jaune(notes); n++; }
        }
        Array.prototype.forEach.call(form.querySelectorAll('details'), function (dt) { if (dt.querySelector('[style*="fff8dc"]')) dt.open = true; });
        msg.style.color = '#166534'; msg.innerHTML = '';
        msg.appendChild(document.createTextNode(n + ' information(s) ajoutée(s), surlignées en jaune. Vérifiez-les et complétez le reste avant d\u2019enregistrer.'));
        if (d.remarque) { var r = document.createElement('div'); r.style.color = '#92400e'; r.textContent = 'Remarque : ' + d.remarque; msg.appendChild(r); }
        if (d.sources && d.sources.length) {
          var s = document.createElement('div'); s.style.color = '#555'; s.appendChild(document.createTextNode('Sources : '));
          d.sources.forEach(function (u, i) { var a = document.createElement('a'); a.href = u; a.target = '_blank'; a.rel = 'noopener'; a.textContent = u.replace(/^https?:\/\/(www\.)?/, '').slice(0, 40); s.appendChild(a); if (i < d.sources.length - 1) s.appendChild(document.createTextNode(' · ')); });
          msg.appendChild(s);
        }
      })
      .catch(function () { btn.disabled = false; msg.style.color = '#991b1b'; msg.textContent = 'Recherche impossible, réessayez.'; });
  });
})();
</script>
