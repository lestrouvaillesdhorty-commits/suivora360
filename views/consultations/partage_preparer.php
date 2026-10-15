<?php use App\Core\View; use App\Models\ConsultationPartage; ?>
<a href="/index.php?r=consultations/<?= $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Envoyer la consultation</h1>
<div class="subtitle">Fournisseur : <?= View::e($consultation['fournisseur_nom']) ?> — Dossier <?= View::e($consultation['dossier_reference']) ?></div>

<div class="card" style="max-width:760px">
  <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <h2 style="font-size:15px;margin:0 0 8px">1. À qui l'envoyer chez <?= View::e($consultation['fournisseur_nom']) ?> ?</h2>
    <?php foreach ($contacts as $i => $c): ?>
      <label style="display:flex;align-items:flex-start;gap:10px;padding:8px 10px;border:1px solid #e3e6ec;border-radius:8px;margin-bottom:6px;cursor:pointer">
        <input type="radio" name="contact" value="<?= View::e($c['cle']) ?>" <?= $i === 0 ? 'checked' : '' ?> style="width:auto;margin-top:3px" data-email="<?= View::e($c['email']) ?>" data-tel="<?= View::e($c['telephone']) ?>">
        <span>
          <strong><?= View::e($c['nom']) ?></strong><?= $c['fonction'] !== '' ? ' <span style="color:#6b7280">— ' . View::e($c['fonction']) . '</span>' : '' ?><br>
          <span style="font-size:13px;color:#4b5563">
            <?= $c['email'] !== '' ? '✉ ' . View::e($c['email']) : '<span style="color:#b45309">✉ pas d\'e-mail</span>' ?>
            &nbsp;·&nbsp;
            <?= $c['telephone'] !== '' ? '☎ ' . View::e($c['telephone']) : '<span style="color:#b45309">☎ pas de téléphone</span>' ?>
          </span>
        </span>
      </label>
    <?php endforeach; ?>
    <label style="display:flex;align-items:flex-start;gap:10px;padding:8px 10px;border:1px solid #e3e6ec;border-radius:8px;margin-bottom:6px;cursor:pointer">
      <input type="radio" name="contact" value="autre" <?= empty($contacts) ? 'checked' : '' ?> style="width:auto;margin-top:3px" data-email="" data-tel="">
      <span><strong>Autre destinataire</strong> <span style="color:#6b7280;font-size:13px">(saisir ci-dessous)</span></span>
    </label>
    <div id="blocAutre" style="display:none;margin:8px 0 4px;padding:10px 12px;background:#f8f9fb;border-radius:8px">
      <div class="form-row">
        <div class="form-group"><label>Nom</label><input type="text" name="dest_nom" placeholder="Ex. Mme Li"></div>
        <div class="form-group"><label>E-mail</label><input type="email" name="dest_email" placeholder="contact@fournisseur.com"></div>
        <div class="form-group"><label>Téléphone (WhatsApp)</label><input type="text" name="dest_telephone" placeholder="+86 …"></div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px"><input type="checkbox" name="enregistrer_contact" value="1" style="width:auto"> Enregistrer ce contact sur la fiche du fournisseur</label>
    </div>
    <?php if (empty($contacts)): ?>
      <div style="font-size:13px;color:#92400e;margin-top:6px">Aucun contact sur la fiche de ce fournisseur : saisissez le destinataire ci-dessus (vous pourrez l'enregistrer).</div>
    <?php endif; ?>

    <h2 style="font-size:15px;margin:18px 0 8px">2. Par quel canal ?</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:4px">
      <?php foreach (\App\Models\ConsultationFournisseur::CANAUX as $code => $label): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px solid #e3e6ec;border-radius:8px;cursor:pointer">
          <input type="radio" name="canal" value="<?= $code ?>" <?= $code === 'email' ? 'checked' : '' ?> style="width:auto"> <?= View::e($code === 'autre' ? 'Autre (copier le lien)' : $label) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <div id="alerteCanal" style="font-size:13px;color:#b45309;min-height:18px"></div>

    <h2 style="font-size:15px;margin:18px 0 8px">3. Ce que le fournisseur reçoit</h2>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="masquer_client" value="1" checked style="width:auto">
        Masquer les informations du client final (nom, coordonnées) sur le récapitulatif
      </label>
      <div style="font-size:12px;color:#888;margin-top:4px">Recommandé : le fournisseur voit l'objet et les articles demandés, pas l'identité de votre client.</div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Échéance de réponse souhaitée</label><input type="date" name="echeance_reponse"></div>
      <div class="form-group">
        <label>Durée de validité du lien</label>
        <select name="duree_jours">
          <?php foreach (ConsultationPartage::DUREES_JOURS as $j): ?>
            <option value="<?= $j ?>" <?= $j === 7 ? 'selected' : '' ?>><?= $j ?> jours</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Pièces jointes du dossier à partager (optionnel)</label>
      <?php if (empty($pieces)): ?>
        <div class="empty-state">Aucune pièce jointe sur ce dossier.</div>
      <?php else: ?>
        <?php foreach ($pieces as $p): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:4px 0">
            <input type="checkbox" name="pieces_ids[]" value="<?= $p['id'] ?>" style="width:auto">
            <?= View::e($p['nom_original']) ?> <span style="color:#888;font-size:12px">(<?= number_format($p['taille'] / 1024, 0) ?> Ko)</span>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="submit" class="btn btn-secondary" formaction="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/apercu" formtarget="_blank">Voir le récapitulatif avant envoi</button>
      <button type="submit" class="btn">Continuer : préparer le message</button>
    </div>
    <div style="font-size:12px;color:#888;margin-top:6px">L'aperçu s'ouvre dans un nouvel onglet et montre exactement ce que le fournisseur verra. Rien n'est envoyé à cette étape : vous vérifiez et envoyez à l'écran suivant.</div>
  </form>
</div>
<script>
(function () {
  var radios = document.querySelectorAll('input[name=contact]');
  var canaux = document.querySelectorAll('input[name=canal]');
  var bloc = document.getElementById('blocAutre');
  var alerte = document.getElementById('alerteCanal');
  function maj() {
    var c = document.querySelector('input[name=contact]:checked');
    var canal = document.querySelector('input[name=canal]:checked');
    bloc.style.display = (c && c.value === 'autre') ? 'block' : 'none';
    var msg = '';
    if (c && c.value !== 'autre' && canal) {
      if (canal.value === 'email' && !c.dataset.email) msg = "Ce contact n'a pas d'e-mail : choisissez un autre canal ou « Autre destinataire ».";
      if (canal.value === 'whatsapp' && !c.dataset.tel) msg = "Ce contact n'a pas de téléphone : choisissez un autre canal ou « Autre destinataire ».";
    }
    alerte.textContent = msg;
  }
  radios.forEach(function (r) { r.addEventListener('change', maj); });
  canaux.forEach(function (r) { r.addEventListener('change', maj); });
  // Canal par défaut : e-mail si le contact a une adresse, sinon WhatsApp.
  var c0 = document.querySelector('input[name=contact]:checked');
  if (c0 && !c0.dataset.email && c0.dataset.tel) document.querySelector('input[name=canal][value=whatsapp]').checked = true;
  maj();
})();
</script>

<?php if (!empty($partages)): ?>
<div class="card" style="max-width:760px">
  <h2>Liens déjà générés</h2>
  <table>
    <thead><tr><th>Créé le</th><th>Expire le</th><th>Envoyé</th><th>Consultations</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($partages as $p): ?>
      <tr>
        <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($p['expire_le'])) ?></td>
        <td><?= $p['marque_envoye_le'] ? '✓ ' . date('d/m/Y', strtotime($p['marque_envoye_le'])) : '—' ?></td>
        <td><?= (int) $p['nb_consultations'] ?><?= $p['dernier_acces'] ? ' (dernière le ' . date('d/m/Y', strtotime($p['dernier_acces'])) . ')' : '' ?></td>
        <td>
          <?php if (!empty($p['revoque_le'])): ?>
            <span class="badge badge-red">Révoqué</span>
          <?php elseif (ConsultationPartage::estValide($p)): ?>
            <span class="badge badge-green">Actif</span>
          <?php else: ?>
            <span class="badge badge-gray">Expiré</span>
          <?php endif; ?>
        </td>
        <td><a href="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Voir le lien</a>
          <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $p['id'] ?>/apercu" target="_blank" class="btn btn-sm btn-secondary">Voir le récap</a>
          <a href="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $p['id'] ?>/pdf" class="btn btn-sm btn-secondary">PDF</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
