<?php use App\Core\View; ?>
<header class="pt"><div class="w"><div class="top">
  <?php $__lg = $__m['logo'] ?? null; ?><?php if ($__lg): ?><img src="<?= $__lg ?>" alt="<?= View::e($__m['nom'] ?: $emetteur) ?>"><?php else: ?><img src="/assets/img/logo-full.png" alt="Suivora360"><?php endif; ?>
  <div class="who"><b>Espace client — <?= View::e($client['nom']) ?></b><?= $emetteur !== '' ? 'Proposé par ' . View::e($emetteur) . ' · ' : '' ?>lien valable jusqu'au <?= date('d/m/Y', strtotime($lien['expire_le'])) ?></div>
</div></div></header>
