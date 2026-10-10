/*
 * Sauvegarde automatique des formulaires de création (08/10) — dans le navigateur uniquement.
 * Un formulaire concerné porte data-brouillon="nom". Rien n'est envoyé au serveur : la saisie
 * reste sur cet appareil, 7 jours au maximum, et seulement pour l'utilisateur connecté.
 * Jamais sauvegardés : mots de passe, champs cachés (dont le jeton de sécurité), fichiers.
 * Option : data-brouillon-ajout="idBouton" = bouton qui ajoute une ligne (pour recréer les lignes saisies).
 */
(function () {
  'use strict';
  var DUREE = 7 * 24 * 3600 * 1000;
  var uid = document.body.getAttribute('data-uid') || '';
  if (!uid) { return; }
  var PREFIXE = 'suivora_brouillon:' + uid + ':';

  function lire(cle) { try { return JSON.parse(localStorage.getItem(cle) || 'null'); } catch (e) { return null; } }
  function ecrire(cle, v) { try { localStorage.setItem(cle, JSON.stringify(v)); } catch (e) { /* stockage indisponible : on ignore */ } }
  function effacer(cle) { try { localStorage.removeItem(cle); } catch (e) { } }

  // Purge des brouillons périmés et nettoyage à la déconnexion.
  try {
    for (var i = localStorage.length - 1; i >= 0; i--) {
      var k = localStorage.key(i);
      if (k && k.indexOf('suivora_brouillon:') === 0) {
        var d = lire(k);
        if (!d || !d.t || Date.now() - d.t > DUREE) { localStorage.removeItem(k); }
      }
    }
  } catch (e) { }
  var formLogout = document.querySelector('form[action$="r=logout"]');
  if (formLogout) {
    formLogout.addEventListener('submit', function () {
      try {
        for (var j = localStorage.length - 1; j >= 0; j--) {
          var kk = localStorage.key(j);
          if (kk && kk.indexOf(PREFIXE) === 0) { localStorage.removeItem(kk); }
        }
      } catch (e) { }
    });
  }

  function champs(form) {
    return Array.prototype.filter.call(form.elements, function (el) {
      if (!el.name || el.disabled) { return false; }
      var t = (el.type || '').toLowerCase();
      return ['hidden', 'password', 'file', 'submit', 'button', 'reset', 'image'].indexOf(t) === -1;
    });
  }

  function instantane(form) {
    var compte = {}, res = [];
    champs(form).forEach(function (el) {
      var idx = compte[el.name] = (compte[el.name] === undefined ? 0 : compte[el.name] + 1);
      var t = (el.type || '').toLowerCase();
      var val = (t === 'checkbox' || t === 'radio') ? (el.checked ? '1' : '0') : el.value;
      if (t === 'radio') { idx = Array.prototype.indexOf.call(form.querySelectorAll('input[type=radio][name="' + el.name + '"]'), el); }
      res.push([el.name, idx, val, t === 'radio' ? el.value : null]);
    });
    return res;
  }

  function signature(liste) { return JSON.stringify(liste); }

  function restaurer(form, liste) {
    var bouton = form.getAttribute('data-brouillon-ajout') ? document.getElementById(form.getAttribute('data-brouillon-ajout')) : null;
    liste.forEach(function (e) {
      var nom = e[0], idx = e[1], val = e[2];
      var els, garde = 0;
      var trouver = function () { return champs(form).filter(function (x) { return x.name === nom; }); };
      els = trouver();
      var t0 = els[0] ? (els[0].type || '').toLowerCase() : '';
      if (t0 === 'radio') {
        var radios = form.querySelectorAll('input[type=radio][name="' + nom + '"]');
        if (radios[idx]) { radios[idx].checked = (val === '1'); }
        return;
      }
      while (els.length <= idx && bouton && garde < 40) { bouton.click(); els = trouver(); garde++; }
      var el = els[idx];
      if (!el) { return; }
      var t = (el.type || '').toLowerCase();
      if (t === 'checkbox') { el.checked = (val === '1'); } else { el.value = val; }
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function il_y_a(ms) {
    var m = Math.round(ms / 60000);
    if (m < 1) { return 'à l’instant'; }
    if (m < 60) { return 'il y a ' + m + ' min'; }
    var h = Math.round(m / 60);
    if (h < 24) { return 'il y a ' + h + ' h'; }
    return 'il y a ' + Math.round(h / 24) + ' j';
  }

  document.querySelectorAll('form[data-brouillon]').forEach(function (form) {
    var cle = PREFIXE + form.getAttribute('data-brouillon');
    var drapeau = 'suivora_brouillon_envoye';

    // Après un envoi réussi (on n'est plus sur le formulaire), le brouillon est effacé.
    try {
      var envoye = sessionStorage.getItem(drapeau);
      if (envoye === cle) {
        sessionStorage.removeItem(drapeau);
        // Si on revoit ce formulaire (erreur de validation), le brouillon est conservé.
      }
    } catch (e) { }

    var initial = signature(instantane(form));
    var enAttente = null;

    function sauvegarder() {
      var courant = instantane(form);
      if (signature(courant) === initial) { effacer(cle); return; }
      ecrire(cle, { t: Date.now(), v: courant });
    }
    function planifier() { clearTimeout(enAttente); enAttente = setTimeout(sauvegarder, 500); }
    form.addEventListener('input', planifier);
    form.addEventListener('change', planifier);

    form.addEventListener('submit', function () {
      try { sessionStorage.setItem(drapeau, cle); } catch (e) { }
      sauvegarder();
    });

    var saved = lire(cle);
    if (saved && saved.v && signature(saved.v) !== initial && Date.now() - saved.t <= DUREE) {
      var bandeau = document.createElement('div');
      bandeau.className = 'alert';
      bandeau.style.cssText = 'background:#eef2ff;border:1px solid #c7d2fe;color:#312e81;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px';
      var txt = document.createElement('span');
      txt.textContent = 'Une saisie non enregistrée a été retrouvée (' + il_y_a(Date.now() - saved.t) + ', sur cet appareil).';
      var bR = document.createElement('button'); bR.type = 'button'; bR.className = 'btn btn-sm'; bR.textContent = 'Reprendre ma saisie';
      var bE = document.createElement('button'); bE.type = 'button'; bE.className = 'btn btn-sm btn-secondary'; bE.textContent = 'Effacer';
      bR.addEventListener('click', function () { restaurer(form, saved.v); bandeau.remove(); });
      bE.addEventListener('click', function () { effacer(cle); bandeau.remove(); });
      bandeau.appendChild(txt); bandeau.appendChild(bR); bandeau.appendChild(bE);
      form.parentNode.insertBefore(bandeau, form);
    }
  });

  // Page suivante après un envoi réussi : le formulaire n'est plus là → brouillon supprimé.
  try {
    var cleEnvoyee = sessionStorage.getItem('suivora_brouillon_envoye');
    if (cleEnvoyee) {
      var estToujoursLa = Array.prototype.some.call(document.querySelectorAll('form[data-brouillon]'), function (f) {
        return PREFIXE + f.getAttribute('data-brouillon') === cleEnvoyee;
      });
      if (!estToujoursLa) { localStorage.removeItem(cleEnvoyee); sessionStorage.removeItem('suivora_brouillon_envoye'); }
    }
  } catch (e) { }
})();
