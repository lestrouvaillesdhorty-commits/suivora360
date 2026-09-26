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

  <?php if ($lienWhatsapp): ?>
    <a href="<?= View::e($lienWhatsapp) ?>" target="_blank" class="btn" style="background:#2E7D5B">Ouvrir WhatsApp avec le message prérempli</a>
  <?php else: ?>
    <div class="alert alert-erreur">Aucun numéro de téléphone renseigné pour ce fournisseur — ajoutez-en un sur sa fiche pour utiliser WhatsApp.</div>
  <?php endif; ?>

  <div class="form-group" style="margin-top:16px">
    <label>Message prérempli</label>
    <textarea readonly rows="3" style="font-size:13px"><?= View::e($messagePrepare) ?></textarea>
  </div>

  <?php if (empty($partage['marque_envoye_le'])): ?>
    <div class="alert" style="background:#fffbeb;color:#92400e;padding:10px 14px;border-radius:8px;font-size:13px;margin-top:12px">
      Suivora ne peut pas savoir si le message a réellement été envoyé dans WhatsApp — confirmez-le vous-même une fois envoyé.
    </div>
    <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $partage['id'] ?>/envoye" style="margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <button type="submit" class="btn btn-secondary">Marquer comme envoyé (WhatsApp ouvert)</button>
    </form>
  <?php else: ?>
    <div style="margin-top:12px;font-size:13px;color:#065f46;font-weight:600">✓ Marqué comme envoyé le <?= date('d/m/Y H:i', strtotime($partage['marque_envoye_le'])) ?></div>
  <?php endif; ?>

  <?php if (empty($partage['revoque_le'])): ?>
    <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $partage['id'] ?>/revoquer" style="margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <button type="submit" class="btn btn-secondary">Révoquer ce lien</button>
    </form>
  <?php endif; ?>
</div>
