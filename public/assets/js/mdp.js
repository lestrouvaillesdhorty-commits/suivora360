/*
 * [08/10] Mots de passe provisoires : boutons « Afficher » et « Générer » à côté des champs
 * de création / modification, pour pouvoir noter le mot de passe avant d'enregistrer.
 * Le mot de passe n'est jamais stocké en clair : il n'est visible que dans le champ, à l'écran.
 */
(function () {
  'use strict';
  document.querySelectorAll('input[type="password"][name="mot_de_passe"]').forEach(function (champ) {
    if (champ.closest('form[action*="login"]')) { return; }
    var barre = document.createElement('div');
    barre.style.cssText = 'display:flex;gap:8px;margin-top:6px;flex-wrap:wrap';
    function bouton(texte) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-secondary btn-sm';
      b.textContent = texte;
      return b;
    }
    var voir = bouton('Afficher');
    var gen = bouton('Générer un mot de passe');
    voir.addEventListener('click', function () {
      var cache = champ.type === 'password';
      champ.type = cache ? 'text' : 'password';
      voir.textContent = cache ? 'Masquer' : 'Afficher';
    });
    gen.addEventListener('click', function () {
      var alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
      var tirage = new Uint32Array(12);
      (window.crypto || window.msCrypto).getRandomValues(tirage);
      var mdp = '';
      for (var i = 0; i < tirage.length; i++) { mdp += alphabet.charAt(tirage[i] % alphabet.length); }
      champ.value = mdp;
      champ.type = 'text';
      voir.textContent = 'Masquer';
      champ.dispatchEvent(new Event('input', { bubbles: true }));
    });
    barre.appendChild(voir);
    barre.appendChild(gen);
    champ.parentNode.insertBefore(barre, champ.nextSibling);
  });
})();
