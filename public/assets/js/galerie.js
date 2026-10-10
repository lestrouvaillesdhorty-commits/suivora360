/* Galerie photos : miniatures + visionneuse plein écran (flèches, clavier, glissement).
   S'appuie sur les liens « Aperçu » marqués data-galerie (images uniquement). */
(function () {
  'use strict';
  var liens = Array.prototype.slice.call(document.querySelectorAll('a[data-galerie]'));
  if (!liens.length) return;

  var photos = liens.map(function (a) {
    var ligne = a.closest('tr, .info-row');
    var dl = ligne ? ligne.querySelector('a[href*="telecharger"]:not([data-galerie])') : null;
    return {
      src: a.href,
      nom: a.getAttribute('data-nom') || '',
      ligne: ligne,
      telecharger: dl ? dl.href : null,
      suppr: ligne ? ligne.querySelector('form[action*="/supprimer"]') : null
    };
  });

  /* --- Visionneuse --- */
  var ov = document.createElement('div');
  ov.className = 'gal-ov';
  ov.hidden = true;
  ov.innerHTML = '<button type="button" class="gal-x" aria-label="Fermer">&times;</button>' +
    '<button type="button" class="gal-nav gal-prev" aria-label="Photo précédente">&#8249;</button>' +
    '<figure class="gal-fig"><img alt=""><figcaption></figcaption></figure>' +
    '<button type="button" class="gal-nav gal-next" aria-label="Photo suivante">&#8250;</button>';
  document.body.appendChild(ov);
  var img = ov.querySelector('img'), cap = ov.querySelector('figcaption');
  var idx = 0;

  function montrer(i) {
    idx = (i + photos.length) % photos.length;
    img.src = photos[idx].src;
    cap.textContent = photos[idx].nom + (photos.length > 1 ? '  (' + (idx + 1) + ' / ' + photos.length + ')' : '');
    var seul = photos.length < 2;
    ov.querySelector('.gal-prev').style.display = seul ? 'none' : '';
    ov.querySelector('.gal-next').style.display = seul ? 'none' : '';
  }
  function ouvrir(i) { montrer(i); ov.hidden = false; document.body.style.overflow = 'hidden'; }
  function fermer() { ov.hidden = true; img.removeAttribute('src'); document.body.style.overflow = ''; }

  ov.querySelector('.gal-x').addEventListener('click', fermer);
  ov.querySelector('.gal-prev').addEventListener('click', function (e) { e.stopPropagation(); montrer(idx - 1); });
  ov.querySelector('.gal-next').addEventListener('click', function (e) { e.stopPropagation(); montrer(idx + 1); });
  ov.addEventListener('click', function (e) { if (e.target === ov || e.target.classList.contains('gal-fig')) fermer(); });
  document.addEventListener('keydown', function (e) {
    if (ov.hidden) return;
    if (e.key === 'Escape') fermer();
    else if (e.key === 'ArrowLeft') montrer(idx - 1);
    else if (e.key === 'ArrowRight') montrer(idx + 1);
  });
  var x0 = null;
  ov.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  ov.addEventListener('touchend', function (e) {
    if (x0 === null) return;
    var dx = e.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 50) montrer(idx + (dx < 0 ? 1 : -1));
  }, { passive: true });

  /* Un clic sur « Aperçu » d'une image ouvre la visionneuse au lieu d'un nouvel onglet */
  liens.forEach(function (a, i) {
    a.removeAttribute('target');
    a.addEventListener('click', function (e) { e.preventDefault(); ouvrir(i); });
  });

  /* --- Bandeau de miniatures, placé avant la carte contenant le premier fichier --- */
  var carte = liens[0].closest('.card') || liens[0].parentNode;
  var bande = document.createElement('div');
  bande.className = 'card gal-card';
  var titre = document.createElement('h2');
  titre.textContent = 'Photos (' + photos.length + ')';
  titre.style.marginBottom = '10px';
  var grille = document.createElement('div');
  grille.className = 'gal-grid';
  photos.forEach(function (p, i) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'gal-thumb';
    b.title = p.nom;
    var im = document.createElement('img');
    im.src = p.src; im.alt = p.nom; im.loading = 'lazy';
    var sp = document.createElement('span');
    sp.textContent = p.nom;
    b.appendChild(im); b.appendChild(sp);
    b.addEventListener('click', function () { ouvrir(i); });
    var cell = document.createElement('div');
    cell.className = 'gal-cell';
    cell.appendChild(b);
    if (p.telecharger || p.suppr) {
      var act = document.createElement('div');
      act.className = 'gal-act';
      if (p.telecharger) {
        var d = document.createElement('a');
        d.href = p.telecharger; d.textContent = 'Télécharger'; d.className = 'btn btn-sm btn-secondary';
        act.appendChild(d);
      }
      if (p.suppr) {
        var s = document.createElement('button');
        s.type = 'button'; s.textContent = 'Supprimer'; s.className = 'btn btn-sm btn-secondary';
        s.addEventListener('click', function () {
          if (confirm('Supprimer cette photo ?')) { p.suppr.submit(); }
        });
        act.appendChild(s);
      }
      cell.appendChild(act);
    }
    grille.appendChild(cell);
  });
  bande.appendChild(titre); bande.appendChild(grille);
  carte.parentNode.insertBefore(bande, carte);

  /* Les photos ne figurent plus dans la liste des fichiers (PDF, Word, Excel…) */
  photos.forEach(function (p) { if (p.ligne) p.ligne.style.display = 'none'; });
  Array.prototype.slice.call(document.querySelectorAll('table')).forEach(function (tb) {
    var lignes = tb.querySelectorAll('tbody tr');
    if (!lignes.length) return;
    var visible = Array.prototype.some.call(lignes, function (r) { return r.style.display !== 'none'; });
    if (!visible && tb.querySelector('a[href*="telecharger"]')) {
      var blocTb = tb.closest('.table-scroll') || tb;
      blocTb.style.display = 'none';
      var prec = blocTb.previousElementSibling;
      if (prec && /doc-cat-head/.test(prec.className)) prec.style.display = 'none';
    }
  });
})();
