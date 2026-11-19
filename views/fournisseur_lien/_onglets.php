<?php use App\Core\View; /** Onglets du lien fournisseur. Variables : $base, $onglet, $nbCommandes (facultatif). */
$nbc = (int) ($nbCommandes ?? 0);
$_on = ['demandes' => ['Demandes de prix', $base], 'commandes' => ['Commandes', $base . '/commandes'], 'documents' => ['Documents', $base . '/documents']]; ?>
<div class="tabs"><?php foreach ($_on as $k => [$lib, $href]): ?><a href="<?= View::e($href) ?>" class="<?= ($onglet ?? 'demandes') === $k ? 'on' : '' ?>"><?= View::e($lib) ?><?= ($k === 'commandes' && $nbc > 0) ? ' <span class="badge rd" style="padding:1px 7px">' . $nbc . '</span>' : '' ?></a><?php endforeach; ?></div>
