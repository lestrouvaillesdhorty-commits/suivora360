/*
 * Alertes des notifications (rappels de tâches en retard, etc.) : son + notification à l'écran
 * tant que l'application est ouverte (ordinateur ou téléphone). Activation volontaire par l'utilisateur
 * (les navigateurs interdisent le son et les notifications sans un clic). Aucune donnée n'est envoyée à un tiers.
 */
(function () {
  var cle = 'suivora_alertes', vu = 'suivora_notif_vue';
  function lire(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function ecrire(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function bip() {
    try {
      var C = window.AudioContext || window.webkitAudioContext, c = new C(), t = c.currentTime;
      [880, 660, 880].forEach(function (f, i) {
        var o = c.createOscillator(), g = c.createGain();
        o.frequency.value = f; o.connect(g); g.connect(c.destination);
        g.gain.setValueAtTime(0.0001, t + i * 0.22); g.gain.exponentialRampToValueAtTime(0.25, t + i * 0.22 + 0.03); g.gain.exponentialRampToValueAtTime(0.0001, t + i * 0.22 + 0.2);
        o.start(t + i * 0.22); o.stop(t + i * 0.22 + 0.22);
      });
    } catch (e) {}
  }
  function actif() { return lire(cle) === '1'; }
  function verifier(premiere) {
    fetch('/index.php?r=notifications/nouvelles&depuis=' + (parseInt(lire(vu) || '0', 10) || 0), {credentials: 'same-origin'})
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        if (d.nouvelles && d.nouvelles.length) {
          var max = parseInt(lire(vu) || '0', 10) || 0;
          d.nouvelles.forEach(function (n) { if (n.id > max) max = n.id; });
          // Première visite : on mémorise sans sonner pour ne pas rejouer tout l'historique.
          if (!premiere && lire(vu) !== null && actif()) {
            bip();
            if (window.Notification && Notification.permission === 'granted') {
              d.nouvelles.slice(-3).forEach(function (n) {
                try { var x = new Notification(n.titre, {body: n.message, tag: 'suivora-' + n.id}); x.onclick = function () { window.focus(); if (n.lien) location.href = n.lien; }; } catch (e) {}
              });
            }
          }
          ecrire(vu, String(max));
        } else if (lire(vu) === null) { ecrire(vu, '0'); }
        var pt = document.querySelector('.bell-dot');
        if (pt && d.non_lues === 0) pt.remove();
      }).catch(function () {});
  }
  window.suivoraAlertes = {
    activer: function () {
      ecrire(cle, '1'); bip();
      if (window.Notification && Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
      return true;
    },
    desactiver: function () { ecrire(cle, '0'); },
    actif: actif,
    permission: function () { return window.Notification ? Notification.permission : 'indisponible'; }
  };
  verifier(true);
  setInterval(function () { if (!document.hidden) verifier(false); }, 60000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) verifier(false); });

  // Tableau de bord : mise à jour automatique (retour sur la page, onglet laissé ouvert, action traitée ailleurs).
  var tdb = !!document.querySelector('.dash-kpi-row');
  var masqueDepuis = 0;
  window.addEventListener('pageshow', function (e) { if (e.persisted) location.reload(); });
  function formulaireActif() { var a = document.activeElement; return !!a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName); }
  if (tdb) {
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { masqueDepuis = Date.now(); }
      else if (masqueDepuis && Date.now() - masqueDepuis > 15000 && !formulaireActif()) { location.reload(); }
    });
    setInterval(function () { if (!document.hidden && !formulaireActif()) location.reload(); }, 120000);
  }
})();
