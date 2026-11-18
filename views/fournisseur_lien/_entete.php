<?php use App\Core\View; ?>
<header class="pt"><div class="w"><div class="top">
  <img src="/assets/img/logo-full.png" alt="Suivora360">
  <div class="who"><b>Demandes de prix — <?= View::e($fournisseur['nom']) ?></b><?= $emetteur !== '' ? 'De la part de ' . View::e($emetteur) . ' · ' : '' ?>lien valable jusqu'au <?= date('d/m/Y', strtotime($lien['expire_le'])) ?></div>
</div></div></header>
