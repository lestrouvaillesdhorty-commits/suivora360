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

/*
 * [10/11] Bouton « Afficher / Masquer » sur TOUS les autres champs mot de passe
 * (connexion, changement de mot de passe, confirmation). Masqué par défaut.
 */
(function () {
  'use strict';
  document.querySelectorAll('input[type="password"]').forEach(function (champ) {
    if (champ.name === 'mot_de_passe') { return; } // déjà géré (Afficher + Générer)
    // Le bouton se place DANS le champ, à droite (pas en dessous).
    var enveloppe = document.createElement('div');
    enveloppe.style.cssText = 'position:relative';
    var voir = document.createElement('button');
    voir.type = 'button';
    voir.textContent = 'Afficher';
    voir.setAttribute('aria-label', 'Afficher ou masquer le mot de passe');
    voir.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:transparent;color:#0B7C8A;font-weight:600;font-size:13px;cursor:pointer;padding:6px 8px;border-radius:6px';
    voir.addEventListener('click', function () {
      var cache = champ.type === 'password';
      champ.type = cache ? 'text' : 'password';
      voir.textContent = cache ? 'Masquer' : 'Afficher';
    });
    champ.parentNode.insertBefore(enveloppe, champ);
    enveloppe.appendChild(champ);
    enveloppe.appendChild(voir);
    champ.style.paddingRight = '84px';
  });
})();
