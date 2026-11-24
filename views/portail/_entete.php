<?php use App\Core\View; ?>
<header class="pt"><div class="w"><div class="top">
  <img src="/assets/img/logo-full.png" alt="Suivora360">
  <?php $__lg = null; try { if (!empty($client['filiale_id'])) { $__lg = \App\Models\EntrepriseInfo::logoDataUri(\App\Models\EntrepriseInfo::forFiliale((int) $client['filiale_id'])); } } catch (\Throwable $e) { $__lg = null; } ?>
  <div style="display:flex;align-items:center;gap:10px"><div class="who"><b>Espace client — <?= View::e($client['nom']) ?></b><?= $emetteur !== '' ? 'Proposé par ' . View::e($emetteur) . ' · ' : '' ?>lien valable jusqu'au <?= date('d/m/Y', strtotime($lien['expire_le'])) ?></div></div><?php if ($__lg): ?><img src="<?= $__lg ?>" alt="<?= View::e($emetteur) ?>" style="height:40px;max-width:220px;object-fit:contain"><?php endif; ?></div>
</div></div></header>
