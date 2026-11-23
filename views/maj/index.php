<?php use App\Core\View; /** Mise à jour par zip. Variables : $csrfToken, $sauvegardes, $resultat. */ ?>
<div class="page-header"><h1>Mise à jour de l'application</h1></div>
<div class="card" style="max-width:720px">
  <p>Déposez le fichier <strong>.zip</strong> reçu : les fichiers sont copiés, puis les migrations en attente sont lancées à la suite. Vos données, le fichier <code>.env</code> et vos documents ne sont jamais touchés.</p>
  <?php if ($resultat): ?>
    <div class="alert alert-<?= !empty($resultat['ok']) ? 'succes' : 'erreur' ?>" style="margin:12px 0">
      <strong><?= !empty($resultat['ok']) ? 'Mise à jour terminée.' : 'Mise à jour non terminée.' ?></strong>
      <?php if (!empty($resultat['erreur'])): ?><div><?= View::e($resultat['erreur']) ?></div><?php endif; ?>
      <?php foreach ($resultat['lignes'] as $l): ?><div style="font-size:13px"><?= View::e($l) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="/index.php?r=maj" enctype="multipart/form-data" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Mise à jour en cours…'">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group"><label>Fichier zip</label><input type="file" name="zip" accept=".zip" required></div>
    <button type="submit" class="btn">Mettre à jour</button>
  </form>
  <?php if ($sauvegardes): ?>
    <p style="font-size:12.5px;color:#667;margin-top:16px">Anciennes versions des fichiers modifiés gardées (5 dernières mises à jour) dans <code>storage/maj/</code> : <?= View::e(implode(', ', array_slice($sauvegardes, 0, 5))) ?>.</p>
  <?php endif; ?>
</div>
