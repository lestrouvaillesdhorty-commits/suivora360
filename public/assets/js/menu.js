/*
 * Menu de gauche en groupes dépliables.
 * Le groupe de la page ouverte reste déplié ; les autres se replient. Ce que l'utilisateur ouvre ou ferme est mémorisé
 * sur cet appareil (localStorage) — si le stockage est indisponible, le menu fonctionne quand même.
 */
(function () {
  'use strict';
  var cle = function (g) { return 'sv_nav_' + g; };
  function lire(g) { try { return window.localStorage.getItem(cle(g)); } catch (e) { return null; } }
  function ecrire(g, v) { try { window.localStorage.setItem(cle(g), v); } catch (e) { /* ignoré */ } }
  document.querySelectorAll('.nav-group').forEach(function (grp) {
    var id = grp.getAttribute('data-group');
    var actif = grp.getAttribute('data-actif') === '1';
    var tete = grp.querySelector('.nav-group-head');
    var sauve = lire(id);
    var ouvert = actif || sauve === '1';
    function appliquer(o) {
      grp.classList.toggle('ouvert', o);
      tete.setAttribute('aria-expanded', o ? 'true' : 'false');
    }
    appliquer(ouvert);
    tete.addEventListener('click', function () {
      var o = !grp.classList.contains('ouvert');
      appliquer(o);
      ecrire(id, o ? '1' : '0');
    });
  });
})();
