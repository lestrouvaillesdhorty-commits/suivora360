<?php use App\Core\View; use App\Models\ConsultationPartage; ?>
<a href="/index.php?r=consultations/<?= $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Lien fournisseur généré</h1>
<div class="subtitle">Fournisseur : <?= View::e($consultation['fournisseur_nom']) ?></div>

<div class="card" style="max-width:760px">
  <?php if (!empty($partage['revoque_le'])): ?>
    <div class="alert alert-erreur">Ce lien a été révoqué, il n'est plus accessible.</div>
  <?php elseif (!ConsultationPartage::estValide($partage)): ?>
    <div class="alert alert-erreur">Ce lien a expiré (valide jusqu'au <?= date('d/m/Y H:i', strtotime($partage['expire_le'])) ?>).</div>
  <?php else: ?>
    <div class="alert alert-succes">Lien actif jusqu'au <?= date('d/m/Y H:i', strtotime($partage['expire_le'])) ?>.</div>
  <?php endif; ?>

  <div class="form-group">
    <label>Lien sécurisé (récapitulatif + pièces sélectionnées)</label>
    <input type="text" readonly value="<?= View::e($lienPublic) ?>" onclick="this.select()" style="font-size:13px">
  </div>

  <div style="margin-bottom:14px">
    <a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/partages/<?= (int) $partage['id'] ?>/apercu" target="_blank" class="btn btn-secondary">Voir ce que le fournisseur reçoit (récapitulatif)</a>
    <div style="font-size:12px;color:#888;margin-top:4px">Ouvre le même récapitulatif que le lien, sans compter comme une consultation du fournisseur.</div>
  </div>

  <?php $lienActif = empty($partage['revoque_le']) && ConsultationPartage::estValide($partage); ?>
  <?php if ($lienActif): ?>
  <div class="card" style="box-shadow:none;border:1px solid #e3e6ec;margin:0 0 14px">
    <h2 style="font-size:15px;margin-bottom:8px">Envoyer par e-mail</h2>
    <?php if ($emailFournisseur === ''): ?>
      <div style="font-size:13px;color:#92400e;margin-bottom:8px">Aucune adresse e-mail sur la fiche du fournisseur : saisissez-la ci-dessous (elle ne sera pas ajoutée à la fiche).</div>
    <?php endif; ?>
    <form method="post" action="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/partages/<?= (int) $partage['id'] ?>/email" id="formEmailPartage">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <div class="form-group"><label for="emDest">Destinataire</label><input type="email" id="emDest" name="destinataire" value="<?= View::e($emailFournisseur) ?>" placeholder="contact@fournisseur.com"></div>
      <div class="form-group"><label for="emSujet">Objet</label><input type="text" id="emSujet" name="sujet" value="<?= View::e($sujetEmail) ?>"></div>
      <div class="form-group"><label for="emCorps">Message (le lien doit rester dans le texte)</label><textarea id="emCorps" name="corps" rows="9" style="font-size:13px"><?= View::e($corpsEmail) ?></textarea></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="#" class="btn" id="emMailto">Ouvrir dans ma messagerie</a>
        <?php if ($mailServeurPret): ?><button type="submit" class="btn btn-secondary">Envoyer depuis Suivora</button><?php endif; ?>
      </div>
      <div style="font-size:12px;color:#888;margin-top:6px">
        « Ouvrir dans ma messagerie » prépare le message dans votre application e-mail (Gmail, Outlook…) : vous l'envoyez vous-même, de votre adresse, puis vous cliquez sur « Marquer comme envoyé ».
        <?php if ($mailServeurPret): ?>« Envoyer depuis Suivora » l'expédie directement ; les réponses du fournisseur arrivent sur votre adresse e-mail.<?php else: ?>L'envoi direct depuis Suivora n'est pas activé sur cette installation.<?php endif; ?>
      </div>
    </form>
  </div>
  <script>
  document.getElementById('emMailto').addEventListener('click', function (e) {
    e.preventDefault();
    var f = document.getElementById('formEmailPartage');
    window.location.href = 'mailto:' + encodeURIComponent(f.destinataire.value.trim()) +
      '?subject=' + encodeURIComponent(f.sujet.value) + '&body=' + encodeURIComponent(f.corps.value);
  });
  </script>
  <?php endif; ?>

  <?php if ($lienWhatsapp): ?>
    <a href="<?= View::e($lienWhatsapp) ?>" target="_blank" class="btn" style="background:#2E7D5B">Ouvrir WhatsApp avec le message prérempli</a>
    <span style="font-size:12px;color:#888;margin-left:6px">Ouvre seulement WhatsApp : la consultation n'est pas marquée envoyée.</span>
  <?php else: ?>
    <div class="alert alert-erreur">Aucun numéro de téléphone renseigné pour ce fournisseur — ajoutez-en un sur sa fiche pour utiliser WhatsApp.</div>
  <?php endif; ?>

  <div class="form-group" style="margin-top:16px">
    <label>Message prérempli</label>
    <textarea readonly rows="3" style="font-size:13px"><?= View::e($messagePrepare) ?></textarea>
  </div>

  <?php if (empty($partage['marque_envoye_le'])): ?>
    <div class="alert" style="background:#fffbeb;color:#92400e;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:12px">
      Suivora ne peut pas savoir si le message a réellement été envoyé (WhatsApp ou messagerie) — confirmez-le vous-même une fois envoyé, en indiquant le canal utilisé.
    </div>
    <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $partage['id'] ?>/envoye" style="margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <div class="form-group" style="max-width:360px">
        <label for="canalEnvoi">Canal utilisé pour l'envoi</label>
        <select name="canal_envoi" id="canalEnvoi" required>
          <option value="">Choisir le canal…</option>
          <?php foreach (\App\Models\ConsultationFournisseur::CANAUX as $code => $label): ?>
            <option value="<?= $code ?>"><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary">Confirmer : la consultation a été envoyée</button>
    </form>
  <?php else: ?>
    <div style="margin-top:12px;font-size:13px;color:#065f46;font-weight:600">✓ Marqué comme envoyé le <?= date('d/m/Y H:i', strtotime($partage['marque_envoye_le'])) ?><?= !empty($consultation['canal_envoi']) ? ' — canal : ' . View::e(\App\Models\ConsultationFournisseur::CANAUX[$consultation['canal_envoi']] ?? $consultation['canal_envoi']) : '' ?></div>
  <?php endif; ?>

  <?php if (empty($partage['revoque_le'])): ?>
    <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $partage['id'] ?>/revoquer" style="margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <button type="submit" class="btn btn-secondary">Révoquer ce lien</button>
    </form>
  <?php endif; ?>
</div>
