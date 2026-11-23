<?php
use App\Core\View;
/**
 * Fil de messages (côté équipe).
 * Variables : $messages, $actionUrl (POST), $fichierBase (URL de téléchargement, sans l'id du message), $canal,
 *             $bonId (facultatif), $placeholder, $peutEcrire, $csrfToken.
 */
$csrf = $csrfToken ?? \App\Core\Auth::csrfToken();
?>
<div style="max-height:420px;overflow:auto;margin-bottom:8px">
<?php if (empty($messages)): ?><div class="empty-state">Aucun message pour le moment.</div><?php endif; ?>
<?php foreach ($messages as $m): $moi = $m['emetteur'] === 'interne'; ?>
  <div style="display:flex;justify-content:<?= $moi ? 'flex-end' : 'flex-start' ?>;margin-bottom:6px">
    <div style="max-width:82%;background:<?= $moi ? '#E6F4F6' : '#F3F4F6' ?>;border-radius:12px;padding:7px 11px">
      <div style="font-size:11px;color:#667;margin-bottom:2px"><?= View::e($moi ? ($m['auteur_nom'] ?: 'Équipe') : ($m['auteur_nom'] ?: 'Correspondant')) ?> · <?= date('d/m/Y H:i', strtotime((string) $m['created_at'])) ?><?= (!$moi && empty($m['lu_interne'])) ? ' <span class="badge badge-red">nouveau</span>' : '' ?></div>
      <?php if (trim((string) $m['texte']) !== ''): ?><div style="font-size:13.5px;line-height:1.45;white-space:pre-line"><?= View::e($m['texte']) ?></div><?php endif; ?>
      <?php if (!empty($m['fichier'])): ?><div style="margin-top:4px"><a href="<?= View::e($fichierBase) ?>/<?= (int) $m['id'] ?>/fichier" target="_blank" rel="noopener">📎 <?= View::e($m['nom_fichier'] ?: 'Fichier') ?></a></div><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php if (!empty($peutEcrire)): ?>
<form method="post" action="<?= View::e($actionUrl) ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrf) ?>">
  <input type="hidden" name="canal" value="<?= View::e($canal) ?>">
  <?php if (!empty($bonId)): ?><input type="hidden" name="bon_id" value="<?= (int) $bonId ?>"><?php endif; ?>
  <textarea name="texte" rows="3" maxlength="3000" placeholder="<?= View::e($placeholder ?? 'Votre réponse…') ?>" style="width:100%"></textarea>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-top:6px">
    <span style="font-size:12px;color:#666">Joindre un fichier (PDF, JPG, PNG · 10 Mo) <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"></span>
    <button type="submit" class="btn btn-sm">Répondre</button>
  </div>
</form>
<?php endif; ?>
