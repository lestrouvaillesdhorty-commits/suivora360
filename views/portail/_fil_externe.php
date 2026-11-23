<?php
use App\Core\View;
/**
 * Fil de messages côté client / fournisseur (page publique).
 * Variables : $messages, $actionUrl, $fichierBase, $nomMoi (libellé de l'équipe), $csrfToken, $erreurMsg (facultatif).
 * « Vous » = l'extérieur ; l'équipe = l'entreprise.
 */
?>
<div style="max-height:460px;overflow:auto;margin-bottom:10px">
<?php if (empty($messages)): ?><div class="mut">Aucun message pour le moment. Posez votre question ci-dessous.</div><?php endif; ?>
<?php foreach ($messages as $m): $moi = $m['emetteur'] === 'externe'; ?>
  <div style="display:flex;justify-content:<?= $moi ? 'flex-end' : 'flex-start' ?>;margin-bottom:8px">
    <div style="max-width:86%;background:<?= $moi ? '#E6F4F6' : '#F3F4F6' ?>;border-radius:14px;padding:8px 12px">
      <div style="font-size:11px;color:#667;margin-bottom:2px"><?= $moi ? 'Vous' : View::e($m['auteur_nom'] !== '' ? $m['auteur_nom'] : ($nomEquipe ?? 'Équipe')) ?> · <?= date('d/m/Y H:i', strtotime((string) $m['created_at'])) ?></div>
      <?php if (trim((string) $m['texte']) !== ''): ?><div style="font-size:14px;line-height:1.5;white-space:pre-line"><?= View::e($m['texte']) ?></div><?php endif; ?>
      <?php if (!empty($m['fichier'])): ?><div style="margin-top:4px"><a href="<?= View::e($fichierBase) ?>/<?= (int) $m['id'] ?>/fichier" target="_blank" rel="noopener">📎 <?= View::e($m['nom_fichier'] ?: 'Fichier') ?></a></div><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<form method="post" action="<?= View::e($actionUrl) ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <div class="hp" aria-hidden="true"><label>Site web</label><input type="text" name="site_web" tabindex="-1" autocomplete="off"></div>
  <textarea name="texte" rows="3" maxlength="3000" placeholder="Écrire votre message…" style="width:100%;box-sizing:border-box"></textarea>
  <div class="act" style="justify-content:space-between">
    <span class="mut">Joindre un fichier (PDF, JPG, PNG · 10 Mo) <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"></span>
    <button type="submit" class="btn bp">Envoyer</button>
  </div>
</form>
