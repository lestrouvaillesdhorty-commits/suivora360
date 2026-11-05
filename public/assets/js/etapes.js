/* Formulaires guidés : un formulaire marqué data-etapes est présenté en étapes (Retour / Continuer).
   Les champs et l'envoi restent ceux du formulaire d'origine : seule la présentation change.
   Chaque bloc porte data-etape="Titre" ; les blocs de même titre forment une seule étape. */
(function () {
  function init(form) {
    var blocs = Array.prototype.slice.call(form.querySelectorAll('[data-etape]'));
    var fin = form.querySelector('[data-etapes-fin]');
    if (!blocs.length || !fin) { return; }
    // Les blocs peuvent être répartis en deux colonnes : on les regroupe en une seule, dans l'ordre.
    var colonne = blocs[0].parentNode;
    var grille = colonne.parentNode;
    Array.prototype.slice.call(grille.children).forEach(function (col) {
      if (col !== colonne && col.tagName === 'DIV') {
        Array.prototype.slice.call(col.children).forEach(function (k) { colonne.appendChild(k); });
        col.style.display = 'none';
      }
    });
    // Un élément sans titre d'étape (ex. section repliable) suit l'étape du bloc précédent.
    var courantTitre = blocs[0].getAttribute('data-etape');
    Array.prototype.slice.call(colonne.children).forEach(function (k) {
      if (k === fin || k.id === 'blocRechercheIa') { return; }
      if (k.hasAttribute('data-etape')) { courantTitre = k.getAttribute('data-etape'); }
      else if (k.tagName !== 'INPUT') { k.setAttribute('data-etape', courantTitre); }
    });
    blocs = Array.prototype.slice.call(colonne.querySelectorAll(':scope > [data-etape]'));
    var titres = [];
    blocs.forEach(function (b) { var t = b.getAttribute('data-etape'); if (titres.indexOf(t) < 0) { titres.push(t); } });
    if (titres.length < 2) { return; }
    var ia = form.querySelector('#blocRechercheIa'); // l'aide IA accompagne la première étape
    var courant = 0;
    form.noValidate = true; // la vérification se fait ici, étape par étape (sinon un champ invalide caché bloque l'envoi sans rien dire)
    var onglets = document.getElementById('formTabs'); if (onglets) { onglets.style.display = 'none'; }

    var barre = document.createElement('ol');
    barre.className = 'etapes-barre';
    titres.forEach(function (t, i) {
      var li = document.createElement('li');
      li.innerHTML = '<span class="etapes-num">' + (i + 1) + '</span><span class="etapes-titre"></span>';
      li.querySelector('.etapes-titre').textContent = t;
      barre.appendChild(li);
    });
    var ref = ia || blocs[0];
    colonne.insertBefore(barre, ref);

    var nav = document.createElement('div');
    nav.className = 'etapes-nav';
    nav.innerHTML = '<button type="button" class="btn btn-secondary" data-act="retour">Retour</button>' +
      '<span style="flex:1"></span>' +
      '<button type="button" class="btn" data-act="suivant">Continuer</button>';
    colonne.appendChild(nav);
    colonne.appendChild(fin);

    var msg = document.createElement('div');
    msg.className = 'etapes-erreur';
    msg.setAttribute('role', 'alert');
    msg.style.display = 'none';
    barre.parentNode.insertBefore(msg, barre.nextSibling);

    function afficher(i) {
      courant = i;
      msg.style.display = 'none';
      blocs.forEach(function (b) { b.classList.toggle('etape-off', b.getAttribute('data-etape') !== titres[i]); });
      if (ia) { ia.style.display = (i === 0) ? '' : 'none'; }
      var derniere = i === titres.length - 1;
      fin.style.display = derniere ? '' : 'none';
      nav.querySelector('[data-act=suivant]').style.display = derniere ? 'none' : '';
      nav.querySelector('[data-act=retour]').style.visibility = i === 0 ? 'hidden' : 'visible';
      Array.prototype.forEach.call(barre.children, function (li, k) {
        li.className = k < i ? 'fait' : (k === i ? 'actif' : '');
      });
      window.scrollTo({ top: Math.max(0, barre.getBoundingClientRect().top + window.pageYOffset - 70), behavior: 'smooth' });
    }
    function controlesEtape(i) {
      var out = [];
      blocs.forEach(function (b) {
        if (b.getAttribute('data-etape') === titres[i]) {
          Array.prototype.forEach.call(b.querySelectorAll('input, select, textarea'), function (c) {
            if (c.type === 'hidden' || c.type === 'submit' || c.type === 'button' || c.disabled || c.closest('template')) { return; }
            out.push(c);
          });
        }
      });
      return out;
    }
    function libelle(c) {
      var g = c.closest('.form-group, .art-champ'), l = g && g.querySelector('label');
      return l ? l.textContent.replace(/\s+/g, ' ').replace('*', '').trim() : (c.name || 'un champ');
    }
    function pourquoi(c) {
      var v = c.validity;
      if (v.valueMissing) { return 'à renseigner'; }
      if (v.typeMismatch && c.type === 'url') { return 'le lien doit commencer par https://'; }
      if (v.typeMismatch && c.type === 'email') { return 'adresse e-mail invalide'; }
      if (v.rangeUnderflow || v.rangeOverflow) { return 'valeur hors limites'; }
      if (v.patternMismatch) { return 'format invalide'; }
      return 'valeur invalide';
    }
    function valider(i) {
      msg.style.display = 'none';
      var champs = controlesEtape(i);
      for (var k = 0; k < champs.length; k++) {
        var c = champs[k];
        if (c.type === 'url' && c.value && !/^[a-z][a-z0-9+.-]*:\/\//i.test(c.value.trim())) { c.value = 'https://' + c.value.trim(); }
        var v = c.validity;
        var ok = c.checkValidity() || (v.stepMismatch && !v.valueMissing && !v.typeMismatch && !v.rangeUnderflow && !v.rangeOverflow && !v.patternMismatch);
        if (!ok) {
          msg.textContent = 'Étape « ' + titres[i] + ' » — ' + libelle(c) + ' : ' + pourquoi(c) + '.';
          msg.style.display = '';
          try { c.focus(); } catch (e) {}
          if (c.reportValidity) { c.reportValidity(); }
          return false;
        }
      }
      return true;
    }
    nav.addEventListener('click', function (e) {
      var a = e.target.getAttribute && e.target.getAttribute('data-act');
      if (a === 'suivant' && valider(courant)) { afficher(courant + 1); }
      if (a === 'retour' && courant > 0) { afficher(courant - 1); }
    });
    barre.addEventListener('click', function (e) {
      var li = e.target.closest('li'); if (!li) { return; }
      var k = Array.prototype.indexOf.call(barre.children, li);
      if (k <= courant) { afficher(k); } else if (valider(courant)) { afficher(courant + 1); }
    });
    // Entrée dans un champ : étape suivante plutôt qu'envoi prématuré.
    form.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && courant < titres.length - 1 && e.target.tagName === 'INPUT' && e.target.type !== 'submit') {
        e.preventDefault();
        if (valider(courant)) { afficher(courant + 1); }
      }
    });
    // Envoi : si un champ obligatoire d'une étape précédente est vide, on y revient.
    form.addEventListener('submit', function (e) {
      for (var i = 0; i < titres.length; i++) {
        if (!valider(i)) { e.preventDefault(); afficher(i); valider(i); return; }
      }
    });
    afficher(0);
  }
  document.addEventListener('DOMContentLoaded', function () {
    Array.prototype.forEach.call(document.querySelectorAll('form[data-etapes]'), init);
  });
})();

/* Liens saisis sans « https:// » (collage depuis un site, mobile) : on complète au lieu de bloquer l'envoi. */
document.addEventListener('change', function (e) {
  var c = e.target;
  if (c && c.tagName === 'INPUT' && c.type === 'url' && c.value && !/^[a-z][a-z0-9+.-]*:\/\//i.test(c.value.trim())) {
    c.value = 'https://' + c.value.trim();
  }
});
