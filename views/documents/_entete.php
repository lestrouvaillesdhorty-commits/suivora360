<?php
use App\Core\View;
/** En-tête commun des documents (même rendu que le devis). Variables : $info, $logoUri, $nomEmetteur, $titre, $droiteLignes (HTML sûr). */
$mode = \App\Models\EntrepriseInfo::enteteMode($info, !empty($logoUri));
?>
<?php if (($mep ?? '') === 'moderne'): ?>
  <div class="band"><b><?= View::e($titre) ?></b><div class="meta"><?= preg_replace('/^<br>/', '', $droiteLignes) ?></div></div>
<?php endif; ?>
  <div class="ent">
    <div class="emet">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <?php if ($mode !== 'nom' && !empty($logoUri)): ?><img class="logo" style="margin:0" src="<?= $logoUri ?>" alt="<?= View::e($nomEmetteur) ?>"><?php endif; ?>
      <?php if ($mode !== 'logo'): ?><div class="nom"><?= View::e($nomEmetteur) ?></div><?php endif; ?>
      </div><div style="height:8px"></div>
      <div class="coord"><?= !empty($info['adresse']) ? nl2br(View::e((string) $info['adresse'])) . '<br>' : '' ?><?= !empty($info['telephone']) ? 'Tél. ' . View::e($info['telephone']) : '' ?><?= !empty($info['telephone']) && !empty($info['email']) ? ' · ' : '' ?><?= View::e((string) ($info['email'] ?? '')) ?></div>
    </div>
    <?php if (($mep ?? '') !== 'moderne'): ?><div class="doc"><b><?= View::e($titre) ?></b><?= $droiteLignes ?></div><?php endif; ?>
  </div>
