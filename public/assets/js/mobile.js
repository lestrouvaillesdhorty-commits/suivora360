/*
 * [08/10] Confort mobile (≤ 768 px) : filtres repliés, cases vides masquées dans les cartes,
 * cartes des fiches repliables (accordéon). Le bureau n'est pas touché.
 */
(function () {
  'use strict';
  function mobile() { return window.innerWidth <= 768; }
  if (!mobile()) { return; }

  /* 0) Tableaux restants (onglets des dossiers, équipe, factures, lignes d'articles…) : passage en cartes automatique.
     Les lignes ajoutées plus tard (bouton « Ajouter une ligne ») reçoivent aussi leurs étiquettes. */
  document.querySelectorAll('.content table:not(.responsive-cards)').forEach(function (t) {
    if (t.closest('table') !== t || t.querySelector('table')) { return; }
    var ths = t.querySelectorAll('thead th');
    if (ths.length < 2 || t.querySelector('td[colspan]')) { return; }
    var noms = Array.prototype.map.call(ths, function (th) { return th.textContent.replace(/\s+/g, ' ').trim(); });
    function etiqueter(tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        if (noms[i] && !td.hasAttribute('data-label')) { td.setAttribute('data-label', noms[i]); }
      });
    }
    var corps = t.querySelector('tbody');
    if (!corps) { return; }
    corps.querySelectorAll('tr').forEach(etiqueter);
    if (window.MutationObserver) {
      new MutationObserver(function (muts) {
        muts.forEach(function (m) {
          Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1 && n.tagName === 'TR') { etiqueter(n); } });
        });
      }).observe(corps, { childList: true });
    }
    t.classList.add('responsive-cards');
  });

  /* 1) Cases vides (« — ») masquées dans les listes en cartes */
  document.querySelectorAll('table.responsive-cards.liste-cartes tbody tr').forEach(function (tr) {
    Array.prototype.forEach.call(tr.children, function (td, i) {
      if (i < 2) { return; }
      var t = td.textContent.replace(/\s+/g, ' ').trim();
      if ((t === '—' || t === '') && !td.querySelector('a, button, input, select, img, svg')) { td.classList.add('mob-vide'); }
    });
  });

  /* 2) Filtres repliés : on garde la recherche + le bouton, le reste derrière « Plus de filtres » */
  var vus = [];
  document.querySelectorAll('.filter-bar form, form.dos-filtres, .content form[method="get"]').forEach(function (form) {
    if (vus.indexOf(form) !== -1) { return; }
    vus.push(form);
    var recherche = form.querySelector('input[name="q"], input[type="search"]');
    var champs = form.querySelectorAll('select, input:not([type=hidden]):not([type=submit]):not([type=search]):not([name="q"])');
    if (!recherche || champs.length < 2) { return; }
    var racine = form;
    var enfants = Array.prototype.slice.call(form.children);
    var groupeRecherche = enfants.filter(function (c) { return c === recherche || c.contains(recherche); })[0];
    if (!groupeRecherche) { return; }
    var aCacher = [], boutons = [];
    enfants.forEach(function (c) {
      if (c === groupeRecherche) { return; }
      if (c.tagName === 'INPUT' && c.type === 'hidden') { return; }
      var estBouton = c.matches('button, .btn, .f-actions') || (c.querySelector('button[type=submit]') && !c.querySelector('select, input:not([type=hidden])'));
      if (estBouton) { boutons.push(c); } else { aCacher.push(c); }
    });
    if (aCacher.length === 0) { return; }
    var actifs = 0;
    aCacher.forEach(function (c) {
      c.querySelectorAll('select, input:not([type=hidden])').forEach(function (el) {
        if (el.value && el.name !== 'tri' && el.name !== 'sens' && !(el.tagName === 'SELECT' && el.selectedIndex === 0)) { actifs++; }
      });
    });
    form.classList.add('filtres-mobile');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-secondary btn-filtres';
    var ouvert = actifs > 0 ? false : false;
    function maj() {
      aCacher.forEach(function (c) { c.classList.toggle('mob-cache', !ouvert); });
      boutons.forEach(function (c) { if (c.tagName === 'A' || c.matches('.btn-reset')) { c.classList.toggle('mob-cache', !ouvert); } });
      btn.textContent = ouvert ? 'Masquer les filtres' : (actifs > 0 ? 'Filtres (' + actifs + ' actif' + (actifs > 1 ? 's' : '') + ')' : 'Plus de filtres');
    }
    btn.addEventListener('click', function () { ouvert = !ouvert; maj(); });
    if (boutons.length && boutons[0].parentNode === form) { form.insertBefore(btn, boutons[0]); btn.classList.add('btn-filtres-in'); groupeRecherche.classList.add('mob-recherche'); }
    else { racine.parentNode.insertBefore(btn, racine.nextSibling); }
    maj();
  });

  /* 3) Fiches : les cartes sans formulaire se replient (les deux premières restent ouvertes) */
  if (document.querySelector('.dos-tabs, .fiche-header-meta, .fiche-grid, .detail-grid')) {
    var cartes = Array.prototype.filter.call(document.querySelectorAll('.content .card'), function (c) {
      if (c.parentElement && c.parentElement.closest('.card')) { return false; }
      var premier = c.firstElementChild;
      if (!premier || premier.tagName !== 'H2') { return false; }
      return !c.querySelector('input:not([type=hidden]), select, textarea');
    });
    cartes.forEach(function (c, i) {
      c.classList.add('acc');
      if (i >= 2) { c.classList.add('acc-ferme'); }
      c.firstElementChild.setAttribute('role', 'button');
      c.firstElementChild.addEventListener('click', function (e) {
        if (e.target.closest('a, button')) { return; }
        c.classList.toggle('acc-ferme');
      });
    });
  }

  /* 4) Longues listes en cartes : 10 premières, puis « Afficher la suite » */
  document.querySelectorAll('table.responsive-cards.liste-cartes').forEach(function (t) {
    var lignes = Array.prototype.slice.call(t.querySelectorAll('tbody tr'));
    var pas = 10;
    if (lignes.length <= pas + 2) { return; }
    var visibles = pas;
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-secondary btn-suite';
    function maj() {
      lignes.forEach(function (tr, i) { tr.classList.toggle('mob-cache', i >= visibles); });
      var reste = lignes.length - visibles;
      b.style.display = reste > 0 ? '' : 'none';
      b.textContent = 'Afficher la suite (' + reste + ' de plus)';
    }
    b.addEventListener('click', function () { visibles += pas; maj(); });
    t.parentNode.insertBefore(b, t.nextSibling);
    maj();
  });

  /* 5) Longs formulaires (fiche client / fournisseur) : sections repliables, première ouverte */
  var fm = document.querySelector('form#fournisseurForm, form#clientForm');
  if (fm) {
    var secs = Array.prototype.filter.call(fm.querySelectorAll('.card'), function (c) {
      return c.firstElementChild && c.firstElementChild.tagName === 'H2' && !c.parentElement.closest('.card');
    });
    secs.forEach(function (c, i) {
      c.classList.add('acc');
      var erreur = c.querySelector('[style*="b42318"], .field-error, .erreur');
      if (i > 0 && !erreur) { c.classList.add('acc-ferme'); }
      c.firstElementChild.setAttribute('role', 'button');
      c.firstElementChild.addEventListener('click', function () { c.classList.toggle('acc-ferme'); });
    });
  }
})();
