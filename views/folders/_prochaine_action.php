<?php
// Bandeau « Prochaine action » : l'étape en cours, ce qu'il reste à faire et le bouton pour le faire.
use App\Core\Auth;
use App\Core\View;

if (\App\Models\Dossier::estAnnule($dossier)) { return; }

$pa = \App\Services\ProchaineAction::calculer($dossier);
$couleurs = ['info' => ['#eef2ff', '#4f46e5'], 'attente' => ['#fffbeb', '#d97706'], 'alerte' => ['#fef2f2', '#dc2626'], 'ok' => ['#ecfdf5', '#16a34a']];
[$fond, $trait] = $couleurs[$pa['ton']] ?? $couleurs['info'];
$peutAgir = Auth::canWrite();
$csrfPa = Auth::csrfToken();
?>
<div style="background:<?= $fond ?>;border:1px solid <?= $trait ?>33;border-left:4px solid <?= $trait ?>;border-radius:10px;padding:14px 18px;margin:4px 0 16px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
  <div style="flex:1;min-width:240px">
    <div style="font-size:12px;color:<?= $trait ?>;font-weight:700">Prochaine action</div>
    <div style="font-size:17px;font-weight:700;margin:2px 0 4px"><?= View::e($pa['titre']) ?></div>
    <div style="font-size:13.5px;color:#4b5563;max-width:70ch"><?= View::e($pa['texte']) ?></div>
    <?php if (!empty($pa['compteurs'])): ?><div style="font-size:12.5px;color:#6b7280;margin-top:6px"><?= View::e(implode(' — ', $pa['compteurs'])) ?></div><?php endif; ?>
  </div>
  <?php if ($peutAgir && ($pa['bouton'] || $pa['secondaire'])): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if ($pa['bouton'] && $pa['url']): ?>
      <a href="<?= View::e($pa['url']) ?>" class="btn"><?= View::e($pa['bouton']) ?></a>
    <?php elseif ($pa['bouton'] && $pa['post']): ?>
      <form method="post" action="<?= View::e($pa['post']['url']) ?>" style="margin:0"<?= !empty($pa['post']['confirm']) ? ' onsubmit="return confirm(\'' . View::e(addslashes($pa['post']['confirm'])) . '\');"' : '' ?>>
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfPa) ?>">
        <?php foreach ($pa['post']['champs'] as $k => $v): ?><input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e((string) $v) ?>"><?php endforeach; ?>
        <button type="submit" class="btn"><?= View::e($pa['bouton']) ?></button>
      </form>
    <?php endif; ?>
    <?php if ($pa['secondaire']): $sec = $pa['secondaire']; ?>
      <?php if (!empty($sec['url'])): ?>
        <a href="<?= View::e($sec['url']) ?>" class="btn btn-secondary"><?= View::e($sec['bouton']) ?></a>
      <?php elseif (!empty($sec['post'])): ?>
        <form method="post" action="<?= View::e($sec['post']['url']) ?>" style="margin:0"<?= !empty($sec['post']['confirm']) ? ' onsubmit="return confirm(\'' . View::e(addslashes($sec['post']['confirm'])) . '\');"' : '' ?>>
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfPa) ?>">
          <?php foreach ($sec['post']['champs'] as $k => $v): ?><input type="hidden" name="<?= View::e($k) ?>" value="<?= View::e((string) $v) ?>"><?php endforeach; ?>
          <button type="submit" class="btn btn-secondary"><?= View::e($sec['bouton']) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php elseif (!$peutAgir && $pa['bouton']): ?>
    <div style="font-size:12.5px;color:#6b7280">Lecture seule : cette action est réservée à un rôle avec droit d'écriture.</div>
  <?php endif; ?>
</div>
