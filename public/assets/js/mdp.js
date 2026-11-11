/*
 * Mots de passe : œil « afficher / masquer » DANS chaque champ (connexion, création, changement, confirmation),
 * et bouton « Générer un mot de passe » sous les champs de création / modification d'un utilisateur.
 * Le mot de passe n'est jamais stocké en clair : il n'est visible que dans le champ, à l'écran, quand on le demande.
 */
(function () {
  'use strict';
  var OEIL = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var OEIL_BARRE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19C5.6 19 2 12 2 12a18.7 18.7 0 0 1 5.1-5.9"/><path d="M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a18.6 18.6 0 0 1-2.2 3.2"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/><path d="M2 2l20 20"/></svg>';

  /* Place l'œil dans le champ, à droite. Retourne une fonction pour forcer l'état « visible ». */
  function ajouterOeil(champ) {
    var enveloppe = document.createElement('div');
    enveloppe.style.cssText = 'position:relative';
    var bouton = document.createElement('button');
    bouton.type = 'button';
    bouton.style.cssText = 'position:absolute;right:6px;top:50%;transform:translateY(-50%);width:36px;height:36px;display:flex;align-items:center;justify-content:center;border:0;background:transparent;color:#6b7a88;cursor:pointer;border-radius:8px;padding:0';
    function maj() {
      var visible = champ.type === 'text';
      bouton.innerHTML = visible ? OEIL_BARRE : OEIL;
      bouton.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      bouton.setAttribute('title', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      bouton.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }
    bouton.addEventListener('click', function () {
      champ.type = champ.type === 'password' ? 'text' : 'password';
      maj();
    });
    bouton.addEventListener('mouseenter', function () { bouton.style.color = '#0B7C8A'; });
    bouton.addEventListener('mouseleave', function () { bouton.style.color = '#6b7a88'; });
    champ.parentNode.insertBefore(enveloppe, champ);
    enveloppe.appendChild(champ);
    enveloppe.appendChild(bouton);
    champ.style.paddingRight = '48px';
    maj();
    return maj;
  }

  document.querySelectorAll('input[type="password"]').forEach(function (champ) {
    var maj = ajouterOeil(champ);

    // Création / modification d'un utilisateur : en plus, « Générer un mot de passe ».
    if (champ.name === 'mot_de_passe' && !champ.closest('form[action*="login"]')) {
      var gen = document.createElement('button');
      gen.type = 'button';
      gen.className = 'btn btn-secondary btn-sm';
      gen.style.marginTop = '6px';
      gen.textContent = 'Générer un mot de passe';
      gen.addEventListener('click', function () {
        var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        var tirage = new Uint32Array(12);
        (window.crypto || window.msCrypto).getRandomValues(tirage);
        var mdp = '';
        for (var i = 0; i < tirage.length; i++) { mdp += alphabet.charAt(tirage[i] % alphabet.length); }
        champ.value = mdp;
        champ.type = 'text'; // visible, pour pouvoir le noter avant d'enregistrer
        maj();
        champ.dispatchEvent(new Event('input', { bubbles: true }));
      });
      champ.parentNode.parentNode.insertBefore(gen, champ.parentNode.nextSibling);
    }
  });
})();
