<?php
use App\Core\Icon;
use App\Core\View;
?>
<h1>Résultat de l'import</h1>
<div class="subtitle">Demandes</div>

<div class="card">
  <p>
    <span class="badge badge-green"><?= Icon::svg('check-circle', 'icon', 14) ?> <?= (int) $resultats['crees'] ?> demande<?= $resultats['crees'] > 1 ? 's' : '' ?> créée<?= $resultats['crees'] > 1 ? 's' : '' ?></span>
    <?php if (!empty($resultats['erreurs'])): ?>
      <span class="badge badge-red" style="margin-left:8px"><?= count($resultats['erreurs']) ?> ligne<?= count($resultats['erreurs']) > 1 ? 's' : '' ?> ignorée<?= count($resultats['erreurs']) > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </p>

  <?php if (!empty($resultats['erreurs'])): ?>
    <div class="alert alert-erreur">
      <ul style="margin:0;padding-left:18px">
        <?php foreach ($resultats['erreurs'] as $erreur): ?>
          <li><?= View::e($erreur) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div style="margin-top:14px">
    <a href="/index.php?r=demandes" class="btn">Voir les demandes</a>
    <a href="/index.php?r=demandes/importer" class="btn btn-secondary">Importer un autre fichier</a>
  </div>
</div>
