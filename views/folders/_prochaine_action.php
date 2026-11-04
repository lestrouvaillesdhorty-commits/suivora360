<?php
// Bandeau « Prochaine action » : l'étape en cours, ce qu'il reste à faire et le bouton pour le faire.
use App\Core\Auth;
use App\Core\View;

if (\App\Models\Dossier::estAnnule($dossier)) { return; }

$pa = \App\Services\ProchaineAction::calculer($dossier);
$couleurs = ['info' => '#0B7C8A', 'attente' => '#4F7A96', 'alerte' => '#C8402A', 'ok' => '#2E9A5F'];
$fond = $couleurs[$pa['ton']] ?? $couleurs['info'];
$peutAgir = Auth::canWrite();
$csrfPa = Auth::csrfToken();
?>
<div class="pa-card" style="background:<?= $fond ?>">
  <div style="flex:1;min-width:240px">
    <div class="pa-etiquette">Prochaine action</div>
    <div class="pa-titre"><?= View::e($pa['titre']) ?></div>
    <div class="pa-texte"><?= View::e($pa['texte']) ?></div>
    <?php if (!empty($pa['compteurs'])): ?><div class="pa-compteurs"><?= View::e(implode(' — ', $pa['compteurs'])) ?></div><?php endif; ?>
  </div>
  <?php if ($peutAgir && ($pa['bouton'] || $pa['secondaire'])): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if ($pa['bouton'] && $pa['url']): ?>
      <a href="<?= View::e($pa['url']) ?>" class="btn pa-btn"><?= View::e($pa['bouton']) ?></a>
    <?php elseif ($pa['bouton'] && $pa['post']): ?>
      <form method="post" action="<?= View::e($pa['post']['url']) ?>" style="margin:0"<?= !empty($pa['post']['confirm']) ? ' onsubmit="return confirm(\'' . View::e(addslashes($pa['post']['confirm'])) . '\');"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfPa) ?>">
        <?php foreach ($pa['post']['champs'] as $k => $v): ?><input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e((string) $v) ?>"><?php endforeach; ?>
        <button type="submit" class="btn pa-btn"><?= View::e($pa['bouton']) ?></button>
      </form>
    <?php endif; ?>
    <?php if ($pa['secondaire']): $sec = $pa['secondaire']; ?>
      <?php if (!empty($sec['url'])): ?>
        <a href="<?= View::e($sec['url']) ?>" class="btn pa-btn pa-btn-2"><?= View::e($sec['bouton']) ?></a>
      <?php elseif (!empty($sec['post'])): ?>
        <form method="post" action="<?= View::e($sec['post']['url']) ?>" style="margin:0"<?= !empty($sec['post']['confirm']) ? ' onsubmit="return confirm(\'' . View::e(addslashes($sec['post']['confirm'])) . '\');"' : '' ?>>
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfPa) ?>">
          <?php foreach ($sec['post']['champs'] as $k => $v): ?><input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e((string) $v) ?>"><?php endforeach; ?>
          <button type="submit" class="btn pa-btn pa-btn-2"><?= View::e($sec['bouton']) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php elseif (!$peutAgir && $pa['bouton']): ?>
    <div style="font-size:12.5px;color:#fff;opacity:.85">Lecture seule : cette action est réservée à un rôle avec droit d'écriture.</div>
  <?php endif; ?>
</div>
