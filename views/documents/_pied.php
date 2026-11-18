<?php
use App\Core\View;
/** Pied de page commun des documents (même contenu que le devis). Variables : $info, $nomEmetteur, $juridique, $piedDroite (texte). */
?>
  <div class="pied">
    <div class="pl"><strong><?= View::e($nomEmetteur) ?></strong><?= !empty($info['adresse']) ? ' — ' . View::e(trim(preg_replace('/\s*\R\s*/u', ', ', (string) $info['adresse']))) : '' ?></div>
    <?php if (!empty($info['site_web'])): ?><div class="pl"><?= View::e($info['site_web']) ?></div><?php endif; ?>
    <?php if (!empty($juridique)): ?><div class="pl"><?= View::e($juridique) ?></div><?php endif; ?>
    <?php if (!empty($info['identifiants'])): ?><div class="pl"><?= View::e(trim(preg_replace('/\s*\R\s*/u', '  ·  ', (string) $info['identifiants']))) ?></div><?php endif; ?>
    <?php if (!empty($info['mentions_pied'])): ?><div class="pl"><?= nl2br(View::e((string) $info['mentions_pied'])) ?></div><?php endif; ?>
    <?php if (!empty($piedDroite)): ?><div class="pl pr"><?= View::e($piedDroite) ?></div><?php endif; ?>
  </div>
