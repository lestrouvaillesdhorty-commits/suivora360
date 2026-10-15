<?php use App\Core\View; use App\Models\ConsultationPartage; use App\Models\ConsultationFournisseur; ?>
<a href="/index.php?r=consultations/<?= $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Envoyer la consultation — vérifier et envoyer</h1>
<div class="subtitle">Fournisseur : <?= View::e($consultation['fournisseur_nom']) ?></div>

<?php
$lienActif = empty($partage['revoque_le']) && ConsultationPartage::estValide($partage);
$destNom = trim((string) ($dest['nom'] ?? ''));
$destEmail = trim((string) ($dest['email'] ?? ''));
$destTel = trim((string) ($dest['telephone'] ?? ''));
?>
<div class="card" style="max-width:760px">
  <?php if (!empty($partage['revoque_le'])): ?>
    <div class="alert alert-erreur">Ce lien a été révoqué, il n'est plus accessible.</div>
  <?php elseif (!$lienActif): ?>
    <div class="alert alert-erreur">Ce lien a expiré (valide jusqu'au <?= date('d/m/Y H:i', strtotime($partage['expire_le'])) ?>).</div>
  <?php else: ?>
    <div class="alert alert-succes">Lien actif jusqu'au <?= date('d/m/Y H:i', strtotime($partage['expire_le'])) ?>.</div>
  <?php endif; ?>

  <div style="background:#f8f9fb;border:1px solid #e3e6ec;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:14px">
    <strong>Destinataire :</strong> <?= View::e($destNom !== '' ? $destNom : $consultation['fournisseur_nom']) ?>
    <?= $destEmail !== '' ? ' — ✉ ' . View::e($destEmail) : '' ?><?= $destTel !== '' ? ' — ☎ ' . View::e($destTel) : '' ?><br>
    <strong>Canal choisi :</strong> <?= View::e(ConsultationFournisseur::CANAUX[$canalChoisi] ?? $canalChoisi) ?>
    <a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/partages/nouvelle" style="margin-left:8px;font-size:13px">Changer</a>
  </div>

  <div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap">
    <a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/partages/<?= (int) $partage['id'] ?>/apercu" target="_blank" class="btn btn-secondary">Voir ce que le fournisseur reçoit (récapitulatif)</a>
    <a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/partages/<?= (int) $partage['id'] ?>/pdf" class="btn btn-secondary">Télécharger le PDF</a>
  </div>

<?php
// Un panneau par canal ; celui choisi s'affiche en premier, les autres sont repliés.
$panneaux = [];

ob_start(); ?>
    <h2 style="font-size:15px;margin-bottom:8px">Envoyer par e-mail</h2>
    <?php if ($destEmail === ''): ?>
      <div style="font-size:13px;color:#92400e;margin-bottom:8px">Aucune adresse e-mail pour ce destinataire : saisissez-la ci-dessous (elle ne sera pas ajoutée à la fiche).</div>
    <?php endif; ?>
    <?php if (!$lienActif): ?>
      <div style="font-size:13px;color:#92400e">Le lien n'est plus actif : générez-en un nouveau.</div>
    <?php else: ?>
    <form method="post" action="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/partages/<?= (int) $partage['id'] ?>/email" id="formEmailPartage">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <div class="form-group"><label for="emDest">Destinataire</label><input type="email" id="emDest" name="destinataire" value="<?= View::e($destEmail) ?>" placeholder="contact@fournisseur.com"></div>
      <div class="form-group"><label for="emSujet">Objet</label><input type="text" id="emSujet" name="sujet" value="<?= View::e($sujetEmail) ?>"></div>
      <div class="form-group"><label for="emCorps">Message (le lien doit rester dans le texte)</label><textarea id="emCorps" name="corps" rows="9" style="font-size:13px"><?= View::e($corpsEmail) ?></textarea></div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin:4px 0 10px"><input type="checkbox" name="joindre_pdf" value="1" checked style="width:auto"> Joindre le récapitulatif en PDF (envoi depuis Suivora uniquement)</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($mailServeurPret): ?><button type="submit" class="btn">Envoyer l'e-mail depuis Suivora</button><?php endif; ?>
        <a href="#" class="btn <?= $mailServeurPret ? 'btn-secondary' : '' ?>" id="emMailto">Ouvrir dans ma messagerie</a>
      </div>
      <div style="font-size:12px;color:#888;margin-top:6px">
        <?php if ($mailServeurPret): ?>« Envoyer l'e-mail depuis Suivora » l'expédie directement avec le PDF ; les réponses arrivent sur votre adresse e-mail et la consultation passe en « Envoyée ».<?php else: ?>L'envoi direct depuis Suivora n'est pas activé sur cette installation (il faut renseigner <code>MAIL_FROM_ADDRESS</code> dans le fichier .env).<?php endif; ?>
        « Ouvrir dans ma messagerie » prépare le message dans Gmail, Outlook… : vous l'envoyez vous-même (téléchargez le PDF ci-dessus pour le joindre), puis vous confirmez l'envoi plus bas.
      </div>
    </form>
    <script>
    document.getElementById('emMailto').addEventListener('click', function (e) {
      e.preventDefault();
      var f = document.getElementById('formEmailPartage');
      window.location.href = 'mailto:' + encodeURIComponent(f.destinataire.value.trim()) +
        '?subject=' + encodeURIComponent(f.sujet.value) + '&body=' + encodeURIComponent(f.corps.value);
    });
    </script>
    <?php endif; ?>
<?php $panneaux['email'] = ob_get_clean();

ob_start(); ?>
    <h2 style="font-size:15px;margin-bottom:8px">Envoyer par WhatsApp</h2>
    <?php if ($lienWhatsapp): ?>
      <a href="<?= View::e($lienWhatsapp) ?>" target="_blank" class="btn" style="background:#2E7D5B">Ouvrir WhatsApp avec le message prérempli</a>
      <div style="font-size:12px;color:#888;margin-top:6px">Ouvre seulement WhatsApp : la consultation n'est pas marquée envoyée tant que vous ne confirmez pas plus bas.</div>
    <?php else: ?>
      <div class="alert alert-erreur">Aucun numéro de téléphone pour ce destinataire — ajoutez-en un sur la fiche du fournisseur ou changez de destinataire.</div>
    <?php endif; ?>
    <div class="form-group" style="margin-top:10px">
      <label>Message prérempli</label>
      <textarea readonly rows="3" style="font-size:13px"><?= View::e($messagePrepare) ?></textarea>
    </div>
<?php $panneaux['whatsapp'] = ob_get_clean();

ob_start(); ?>
    <h2 style="font-size:15px;margin-bottom:8px">Autre canal (téléphone, Alibaba, plateforme…)</h2>
    <div class="form-group">
      <label>Lien sécurisé (récapitulatif + pièces sélectionnées)</label>
      <input type="text" readonly value="<?= View::e($lienPublic) ?>" onclick="this.select()" style="font-size:13px">
    </div>
    <div class="form-group">
      <label>Message à copier</label>
      <textarea readonly rows="3" style="font-size:13px" onclick="this.select()"><?= View::e($messagePrepare) ?></textarea>
    </div>
<?php $panneaux['autre'] = ob_get_clean();

$ordre = array_unique(array_merge([$canalChoisi], ['email', 'whatsapp', 'autre']));
foreach ($ordre as $i => $code):
  if ($i === 0): ?>
  <div class="card" style="box-shadow:none;border:2px solid #c7d2fe;margin:0 0 14px"><?= $panneaux[$code] ?></div>
<?php else: ?>
  <details style="margin:0 0 10px"><summary style="cursor:pointer;font-size:14px;color:#4b5563">Utiliser plutôt : <?= View::e($code === 'autre' ? 'un autre canal (copier le lien)' : ConsultationFournisseur::CANAUX[$code]) ?></summary>
    <div class="card" style="box-shadow:none;border:1px solid #e3e6ec;margin:8px 0 0"><?= $panneaux[$code] ?></div>
  </details>
<?php endif; endforeach; ?>

  <div class="form-group" style="margin-top:6px">
    <label>Lien sécurisé</label>
    <input type="text" readonly value="<?= View::e($lienPublic) ?>" onclick="this.select()" style="font-size:13px">
  </div>

  <?php if (empty($partage['marque_envoye_le'])): ?>
    <div class="alert" style="background:#fffbeb;color:#92400e;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:12px">
      Suivora ne peut pas savoir si le message a réellement été envoyé (WhatsApp ou messagerie) — confirmez-le vous-même une fois envoyé, en indiquant le canal utilisé. Pour l'envoi d'e-mail depuis Suivora, c'est automatique.
    </div>
    <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $partage['id'] ?>/envoye" style="margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <div class="form-group" style="max-width:360px">
        <label for="canalEnvoi">Canal utilisé pour l'envoi</label>
        <select name="canal_envoi" id="canalEnvoi" required>
          <option value="">Choisir le canal…</option>
          <?php foreach (ConsultationFournisseur::CANAUX as $code => $label): ?>
            <option value="<?= $code ?>" <?= $code === $canalChoisi ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-secondary">Confirmer : la consultation a été envoyée</button>
    </form>
  <?php else: ?>
    <div style="margin-top:12px;font-size:13px;color:#065f46;font-weight:600">✓ Marqué comme envoyé le <?= date('d/m/Y H:i', strtotime($partage['marque_envoye_le'])) ?><?= !empty($consultation['canal_envoi']) ? ' — canal : ' . View::e(ConsultationFournisseur::CANAUX[$consultation['canal_envoi']] ?? $consultation['canal_envoi']) : '' ?></div>
  <?php endif; ?>

  <?php if (empty($partage['revoque_le'])): ?>
    <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $partage['id'] ?>/revoquer" style="margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <button type="submit" class="btn btn-secondary">Révoquer ce lien</button>
    </form>
  <?php endif; ?>
</div>
