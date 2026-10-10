<?php
use App\Core\Telephone;
use App\Core\View;

/** Boutons E-mail / WhatsApp, affichés seulement si la coordonnée existe. Variables : $bcEmail, $bcTel. */
$bcWa = Telephone::pourWhatsApp($bcTel ?? '');
if (!empty($bcEmail) || $bcWa !== ''): ?>
  <span style="display:inline-flex;gap:6px;flex-wrap:wrap">
    <?php if (!empty($bcEmail)): ?><a href="mailto:<?= View::e($bcEmail) ?>" class="btn btn-sm btn-secondary" title="Ouvre votre messagerie — rien n'est enregistré comme envoyé">E-mail</a><?php endif; ?>
    <?php if ($bcWa !== ''): ?><a href="https://wa.me/<?= View::e($bcWa) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary" title="Ouvre WhatsApp — rien n'est enregistré comme envoyé">WhatsApp</a><?php endif; ?>
  </span>
<?php endif; ?>
