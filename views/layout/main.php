<?php
use App\Core\Auth;
use App\Core\Permissions;
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
      <?php if ($currentUser): ?>
      <a href="/index.php?r=notifications" class="<?= navActive($path, '/notifications') ?>" style="display:flex;align-items:center;justify-content:space-between">
        <span>🔔 Notifications</span>
        <?php if (!empty($notifNonLues)): ?><span class="badge badge-red"><?= $notifNonLues ?></span><?php endif; ?>
      </a>
      <?php endif; ?>
      <div class="section-label">Opérations</div>
      <a href="/index.php?r=demandes" class="<?= navActive($path, '/demandes') ?>">Demandes</a>
      <a href="/index.php?r=dossiers" class="<?= navActive($path, '/dossiers') ?>">Dossiers</a>
      <a href="/index.php?r=clients" class="<?= navActive($path, '/clients') ?>">Clients</a>
      <a href="/index.php?r=fournisseurs" class="<?= navActive($path, '/fournisseurs') ?>">Fournisseurs</a>
      <a href="/index.php?r=simulateur" class="<?= navActive($path, '/simulateur') ?>">Simulateur de prix</a>
      <?php if ($currentUser && (Permissions::isAdmin($currentUser['role']) || Permissions::canManageParametres($currentUser['role']))): ?>
      <div class="section-label">Administration</div>
      <?php if (Permissions::isAdmin($currentUser['role'])): ?>
      <a href="/index.php?r=filiales" class="<?= navActive($path, '/filiales') ?>">Filiales</a>
      <a href="/index.php?r=utilisateurs" class="<?= navActive($path, '/utilisateurs') ?>">Utilisateurs &amp; accès</a>
      <?php endif; ?>
      <?php if (Permissions::canVoirPilotage($currentUser['role'])): ?>
      <a href="/index.php?r=pilotage" class="<?= navActive($path, '/pilotage') ?>">Pilotage</a>
      <?php endif; ?>
      <?php if (Permissions::canManageParametres($currentUser['role'])): ?>
      <a href="/index.php?r=parametres" class="<?= navActive($path, '/parametres') ?>">Paramètres</a>
      <?php endif; ?>
      <?php endif; ?>
    </nav>
    <?php if ($currentUser): ?>
    <div class="user-box">
      <div><strong><?= View::e($currentUser['nom']) ?></strong></div>
      <div class="role"><?= View::e(Permissions::label($currentUser['role'])) ?></div>
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
