<?php
use App\Core\View;
use App\Models\Organisation;

$org = $currentUser ? Organisation::find((int) $currentUser['organisation_id']) : null;
$routeParam = $_GET['r'] ?? '/';
$path = $routeParam === '' ? '/' : '/' . ltrim($routeParam, '/');
function navActive(string $path, string $prefix): string {
    return str_starts_with($path, $prefix) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Suivora360</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">Suivora360</div>
    <?php if ($org): ?><div class="org-name"><?= View::e($org['nom']) ?></div><?php endif; ?>
    <nav>
      <a href="/index.php" class="<?= $path === '/' ? 'active' : '' ?>">Tableau de bord</a>
      <div class="section-label">Opérations</div>
      <a href="/index.php?r=demandes" class="<?= navActive($path, '/demandes') ?>">Demandes</a>
      <a href="/index.php?r=dossiers" class="<?= navActive($path, '/dossiers') ?>">Dossiers</a>
      <?php if ($currentUser && $currentUser['role'] === 'dirigeant'): ?>
      <div class="section-label">Administration</div>
      <a href="/index.php?r=filiales" class="<?= navActive($path, '/filiales') ?>">Filiales</a>
      <a href="/index.php?r=utilisateurs" class="<?= navActive($path, '/utilisateurs') ?>">Utilisateurs &amp; accès</a>
      <?php endif; ?>
    </nav>
    <?php if ($currentUser): ?>
    <div class="user-box">
      <div><strong><?= View::e($currentUser['nom']) ?></strong></div>
      <div class="role"><?= $currentUser['role'] === 'dirigeant' ? 'Dirigeant(e)' : 'Employé(e)' ?></div>
      <form method="post" action="/index.php?r=logout">
        <button type="submit" class="logout">Déconnexion</button>
      </form>
    </div>
    <?php endif; ?>
  </aside>
  <div class="main">
    <div class="content">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'erreur' ? 'erreur' : 'succes' ?>"><?= View::e($flash['message']) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
</body>
</html>
