<?php use App\Core\View; ?>
<a href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la cotation <?= View::e($cotation['reference']) ?></a>
<h1 style="margin-top:8px">Envoyer le devis — vérifier et envoyer</h1>
<div class="subtitle">Client : <?= View::e($cotation['client_nom']) ?></div>

<div class="card" style="max-width:760px">
  <div style="background:#f8f9fb;border:1px solid #e3e6ec;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:14px">
    <strong>Destinataire :</strong> <?= View::e($cotation['client_nom']) ?><?= $emailClient !== '' ? ' — ✉ ' . View::e($emailClient) : '' ?><?= !empty($client['telephone']) ? ' — ☎ ' . View::e($client['telephone']) : '' ?>
  </div>
  <div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap">
    <a href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/apercu" target="_blank" class="btn btn-secondary">Voir le devis (document)</a>
    <a href="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/pdf" class="btn btn-secondary" id="lienPdf">Télécharger le PDF</a>
    <button type="button" class="btn btn-secondary" id="btnPartagerPdf" style="display:none">Partager le PDF (WhatsApp, e-mail…)</button>
  </div>
  <div id="msgPartagePdf" style="display:none;font-size:13px;color:#92400e;margin:-6px 0 12px"></div>

  <div class="form-row">
    <div class="form-group"><label>Langue du message</label>
      <select id="msgLangue"><option value="fr">Français</option><option value="en">English</option></select></div>
    <div class="form-group"><label>Adresse e-mail du client</label><input type="email" id="destEmail" value="<?= View::e($emailClient) ?>" placeholder="client@exemple.com"></div>
  </div>
  <div class="form-group"><label>Objet</label><input type="text" id="sujet"></div>
  <div class="form-group"><label>Message (texte brut, modifiable)</label><textarea id="corps" rows="14" style="font-size:13px;min-height:240px"></textarea></div>

  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button type="button" class="btn" id="btnMail">Ouvrir ma messagerie</button>
    <button type="button" class="btn" id="btnWa" style="background:#2E7D5B">Ouvrir WhatsApp</button>
    <button type="button" class="btn btn-secondary" id="btnCopie">Copier le message</button>
  </div>
  <div style="font-size:13px;color:#4b5563;margin-top:10px">Autre messagerie ?
    <?php foreach (['gmail' => 'Gmail', 'outlook' => 'Outlook.com / Hotmail', 'mailto' => 'Application installée'] as $t => $lib): ?>
      <a href="#" class="lienMess" data-type="<?= $t ?>" style="margin-right:10px"><?= View::e($lib) ?></a>
    <?php endforeach; ?>
  </div>
  <div style="font-size:12px;color:#888;margin-top:8px">Votre messagerie ou WhatsApp s'ouvre avec le message prêt : vous relisez et envoyez vous-même. Pour joindre le devis : « Partager le PDF » (téléphone) ou « Télécharger le PDF » puis ajout manuel. Ensuite, confirmez l'envoi ci-dessous.</div>

  <?php if (in_array($cotation['statut'], ['brouillon', 'envoyee'], true) && \App\Core\Auth::canGererCotations()): ?>
  <form method="post" action="/index.php?r=cotations/<?= (int) $cotation['id'] ?>/envoyee" style="margin-top:18px;padding-top:14px;border-top:1px solid #eef0f4">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="canal" id="canalEnvoi" value="">
    <?php if ($cotation['statut'] === 'brouillon'): ?>
      <button type="submit" class="btn">J'ai envoyé le devis — marquer comme envoyé</button>
    <?php else: ?>
      <div class="alert alert-succes">Ce devis est déjà marqué comme envoyé.</div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>

<script>
(function () {
  var V = <?= json_encode($variantes, JSON_UNESCAPED_UNICODE) ?>, tel = <?= json_encode($telephoneClient) ?>, nom = <?= json_encode($nomFichier) ?>, mess = <?= json_encode($messagerie) ?>;
  var $ = function (i) { return document.getElementById(i); };
  function charge() { $('sujet').value = V[$('msgLangue').value].sujet; $('corps').value = V[$('msgLangue').value].corps; }
  $('msgLangue').addEventListener('change', charge); charge();
  function canal(c) { var e = $('canalEnvoi'); if (e) e.value = c; }
  function url(type) {
    var to = $('destEmail').value.trim(), su = $('sujet').value, bo = $('corps').value, e = encodeURIComponent;
    if (type === 'gmail') return 'https://mail.google.com/mail/?view=cm&fs=1&to=' + e(to) + '&su=' + e(su) + '&body=' + e(bo);
    if (type === 'outlook') return 'https://outlook.live.com/mail/0/deeplink/compose?to=' + e(to) + '&subject=' + e(su) + '&body=' + e(bo);
    return 'mailto:' + e(to) + '?subject=' + e(su) + '&body=' + e(bo);
  }
  function ouvrir(type) { canal('email'); if (type === 'mailto') { window.location.href = url(type); } else { window.open(url(type), '_blank'); } }
  $('btnMail').addEventListener('click', function () { ouvrir(mess); });
  document.querySelectorAll('.lienMess').forEach(function (a) { a.addEventListener('click', function (ev) { ev.preventDefault(); ouvrir(a.dataset.type); }); });
  $('btnWa').addEventListener('click', function () {
    canal('whatsapp');
    window.open('https://wa.me/' + tel + '?text=' + encodeURIComponent($('corps').value), '_blank');
  });
  $('btnCopie').addEventListener('click', function () {
    $('corps').select();
    try { navigator.clipboard.writeText($('corps').value); } catch (e) { document.execCommand('copy'); }
    this.textContent = 'Message copié'; canal('autre');
  });
  var btn = $('btnPartagerPdf'), msg = $('msgPartagePdf');
  if (window.isSecureContext && navigator.share && navigator.canShare && window.File) {
    btn.style.display = '';
    btn.addEventListener('click', function () {
      msg.style.display = 'none';
      fetch($('lienPdf').href, {credentials: 'same-origin'}).then(function (r) { return r.blob(); })
        .then(function (b) {
          var f = new File([b], nom, {type: 'application/pdf'});
          if (!navigator.canShare({files: [f]})) throw new Error('non');
          return navigator.share({files: [f], title: $('sujet').value, text: $('corps').value});
        })
        .catch(function (e) {
          if (e && e.name === 'AbortError') return;
          msg.textContent = "Le partage du PDF n'est pas possible sur cet appareil : utilisez « Télécharger le PDF » puis joignez-le vous-même."; msg.style.display = 'block';
        });
    });
  }
})();
</script>
