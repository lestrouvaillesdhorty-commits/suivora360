/* Photos du téléphone : réduit automatiquement les grosses images (1600 px, JPEG) avant l'envoi,
   pour éviter les refus du serveur (limite de taille) et gagner du temps en 4G. */
(function () {
  var SEUIL = 1200 * 1024, MAX = 1600;
  function reduire(file) {
    return new Promise(function (ok) {
      if (!/^image\/(jpeg|png|webp)$/i.test(file.type) || file.size < SEUIL) { return ok(file); }
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var r = Math.min(1, MAX / Math.max(img.width, img.height));
        var c = document.createElement('canvas'); c.width = Math.round(img.width * r); c.height = Math.round(img.height * r);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) {
          if (!b || b.size >= file.size) { return ok(file); }
          ok(new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
        }, 'image/jpeg', 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); ok(file); };
      img.src = url;
    });
  }
  document.addEventListener('change', function (e) {
    var inp = e.target;
    if (!inp || inp.type !== 'file' || inp.dataset.reduit === '1' || !inp.files || !inp.files.length || !window.DataTransfer) { return; }
    var gros = false; for (var i = 0; i < inp.files.length; i++) { if (inp.files[i].size >= SEUIL && /^image\//i.test(inp.files[i].type)) { gros = true; } }
    if (!gros) { return; }
    e.stopImmediatePropagation();
    Promise.all(Array.prototype.map.call(inp.files, reduire)).then(function (fs) {
      try {
        var dt = new DataTransfer(); fs.forEach(function (f) { dt.items.add(f); });
        inp.files = dt.files;
      } catch (x) {}
      inp.dataset.reduit = '1';
      inp.dispatchEvent(new Event('change', { bubbles: true }));
      inp.dataset.reduit = '';
    });
  }, true);
})();
