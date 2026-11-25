/* Grille de saisie des articles : une ligne par article, clavier (Tab, Entrée), coller depuis Excel, lignes de note libres.
   Utilisation : <div class="gr" data-grille> avec <tbody data-gr-body>, <template data-gr-tpl="article|note"> et des boutons [data-gr-add="article|note"]. */
(function () {
  function init(root) {
    var body = root.querySelector('[data-gr-body]');
    if (!body) { return; }
    var tpl = {};
    root.querySelectorAll('template[data-gr-tpl]').forEach(function (t) { tpl[t.dataset.grTpl] = t; });
    var foot = root.querySelector('[data-gr-count]');

    function saisies(tr) { return Array.prototype.slice.call(tr.querySelectorAll('input[type=text],input[type=number],select')).filter(function (i) { return !i.closest('[data-gr-hidden]') && !i.readOnly; }); }
    function maj() {
      var n = 0, k = 0, q = 0;
      Array.prototype.forEach.call(body.querySelectorAll('tr'), function (tr) {
        var no = tr.querySelector('[data-gr-no]');
        if (tr.dataset.type === 'note') { if (no) { no.textContent = '✎'; } k++; return; }
        n++; if (no) { no.textContent = n; }
        var qi = tr.querySelector('[data-gr-qte]'); if (qi && parseFloat(qi.value)) { q += parseFloat(qi.value); }
      });
      if (foot) { foot.textContent = n + ' article' + (n > 1 ? 's' : '') + (k ? ' · ' + k + ' note' + (k > 1 ? 's' : '') : '') + (q ? ' · ' + (Math.round(q * 100) / 100) + ' unités' : ''); }
    }
    function notifier() { body.dispatchEvent(new Event('input', { bubbles: true })); }
    function ajouter(type, apres) {
      var t = tpl[type]; if (!t) { return null; }
      var frag = t.content.cloneNode(true), tr = frag.querySelector('tr');
      if (body.dataset.grIndex !== undefined) { var k = parseInt(body.dataset.grIndex, 10) || 0; body.dataset.grIndex = k + 1; frag.querySelectorAll('[name*="__i__"]').forEach(function (el) { el.name = el.name.replace('__i__', k); }); }
      if (apres && apres.nextSibling) { body.insertBefore(frag, apres.nextSibling); } else { body.appendChild(frag); }
      maj(); return tr;
    }
    function focus(tr, col) { var s = tr ? saisies(tr) : []; if (s.length) { var c = s[Math.min(col || 0, s.length - 1)]; c.focus(); if (c.select) { try { c.select(); } catch (e) {} } } }

    root.querySelectorAll('[data-gr-add]').forEach(function (b) {
      b.addEventListener('click', function () { var tr = ajouter(b.dataset.grAdd); focus(tr, 0); });
    });
    body.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-gr-del]'), dup = e.target.closest('[data-gr-dup]');
      if (rm) { var tr = rm.closest('tr'); tr.remove(); maj(); notifier(); }
      if (dup) {
        var src = dup.closest('tr'), copie = ajouter(src.dataset.type, src);
        var a = saisies(src), b = saisies(copie); a.forEach(function (x, i) { if (b[i]) { b[i].value = x.value; } }); maj(); notifier();
      }
    });
    body.addEventListener('input', maj);
    body.addEventListener('keydown', function (e) {
      var el = e.target; if (!el.closest('tr')) { return; }
      if (e.key === 'Enter' && el.tagName !== 'TEXTAREA') {
        e.preventDefault();
        var tr = el.closest('tr'), col = saisies(tr).indexOf(el), nxt = tr.nextElementSibling;
        if (!nxt) { nxt = ajouter('article'); }
        focus(nxt, nxt.dataset.type === 'note' ? 0 : col);
      }
    });
    body.addEventListener('paste', function (e) {
      var txt = (e.clipboardData || window.clipboardData).getData('text');
      if (!txt || (txt.indexOf('\t') < 0 && txt.indexOf('\n') < 0)) { return; }
      var el = e.target, tr = el.closest('tr'); if (!tr) { return; }
      e.preventDefault();
      var lignes = txt.replace(/\r/g, '').split('\n'); if (lignes.length && lignes[lignes.length - 1] === '') { lignes.pop(); }
      var col0 = saisies(tr).indexOf(el), cur = tr;
      lignes.forEach(function (ligne, li) {
        if (li > 0) { cur = cur.nextElementSibling && cur.nextElementSibling.dataset.type !== 'note' ? cur.nextElementSibling : ajouter('article', cur); }
        ligne.split('\t').forEach(function (val, ci) {
          var s = saisies(cur), c = s[col0 + ci]; if (!c) { return; }
          val = val.trim();
          if (c.tagName === 'SELECT') { Array.prototype.forEach.call(c.options, function (o) { if (o.text.toLowerCase() === val.toLowerCase()) { c.value = o.value; } }); }
          else { c.value = c.type === 'number' ? val.replace(/\s/g, '').replace(',', '.') : val; }
        });
      });
      maj(); notifier();
    });
    if (!body.querySelector('tr')) { ajouter('article'); }
    maj();
  }
  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('[data-grille]').forEach(init); });
})();
