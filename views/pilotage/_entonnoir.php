<?php
use App\Core\View;
$etapes = $etapes ?? [];
?>
<div class="card">
    <h2>Du premier contact à la commande</h2>
    <div class="kpi-note" style="margin:0 0 12px">Où perd-on des affaires ? Le pourcentage compare chaque étape à la précédente.</div>
    <div class="entonnoir">
      <?php foreach ($etapes as $e): ?>
        <div class="e-ligne">
          <div class="e-tete"><span><?= View::e($e['label']) ?><?php if ($e['pct'] !== null): ?> <small>· <?= (int) $e['pct'] ?> %</small><?php endif; ?></span><strong><?= (int) $e['n'] ?></strong></div>
          <div class="e-piste"><span style="width:<?= $e['largeur'] ?>%;background:<?= $e['couleur'] ?>"></span></div>
        </div>
      <?php endforeach; ?>
    </div>
</div>
