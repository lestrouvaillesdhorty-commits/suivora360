<?php
use App\Core\Auth;
use App\Core\Icon;
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
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-mark">S</div>
      <div class="brand-text">
        <div class="name">Suivora</div>
        <div class="tagline">Pilotage opérationnel</div>
      </div>
      <button type="button" id="sidebarClose" class="brand-close" aria-label="Fermer le menu">✕</button>
    </div>
    <?php if ($org): ?><div class="org-name"><?= View::e($org['nom']) ?></div><?php endif; ?>
    <nav>
      <a href="/index.php" class="<?= $path === '/' ? 'active' : '' ?>"><?= Icon::svg('home') ?><span>Tableau de bord</span></a>
      <?php if ($currentUser): ?>
      <a href="/index.php?r=notifications" class="<?= navActive($path, '/notifications') ?>" style="justify-content:space-between">
        <span style="display:flex;align-items:center;gap:11px"><?= Icon::svg('bell') ?>Notifications</span>
        <?php if (!empty($notifNonLues)): ?><span class="badge badge-red"><?= $notifNonLues ?></span><?php endif; ?>
      </a>
      <?php endif; ?>
      <div class="section-label">Opérations</div>
      <a href="/index.php?r=demandes" class="<?= navActive($path, '/demandes') ?>"><?= Icon::svg('inbox') ?><span>Demandes</span></a>
      <a href="/index.php?r=dossiers" class="<?= navActive($path, '/dossiers') ?>"><?= Icon::svg('folder') ?><span>Dossiers</span></a>
      <a href="/index.php?r=clients" class="<?= navActive($path, '/clients') ?>"><?= Icon::svg('users') ?><span>Clients</span></a>
      <a href="/index.php?r=fournisseurs" class="<?= navActive($path, '/fournisseurs') ?>"><?= Icon::svg('truck') ?><span>Fournisseurs</span></a>
      <a href="/index.php?r=simulateur" class="<?= navActive($path, '/simulateur') ?>"><?= Icon::svg('sliders') ?><span>Simulateur de prix</span></a>
      <?php if ($currentUser && (Permissions::isAdmin($currentUser['role']) || Permissions::canManageParametres($currentUser['role']))): ?>
      <div class="section-label">Administration</div>
      <?php if (Permissions::isAdmin($currentUser['role'])): ?>
      <a href="/index.php?r=filiales" class="<?= navActive($path, '/filiales') ?>"><?= Icon::svg('building') ?><span>Filiales</span></a>
      <a href="/index.php?r=utilisateurs" class="<?= navActive($path, '/utilisateurs') ?>"><?= Icon::svg('user') ?><span>Utilisateurs &amp; accès</span></a>
      <?php endif; ?>
      <?php if (Permissions::canVoirPilotage($currentUser['role'])): ?>
      <a href="/index.php?r=pilotage" class="<?= navActive($path, '/pilotage') ?>"><?= Icon::svg('bar-chart-2') ?><span>Pilotage</span></a>
      <?php endif; ?>
      <?php if (Permissions::canManageParametres($currentUser['role'])): ?>
      <a href="/index.php?r=parametres" class="<?= navActive($path, '/parametres') ?>"><?= Icon::svg('settings') ?><span>Paramètres</span></a>
      <?php endif; ?>
      <?php endif; ?>
    </nav>
    <?php if ($currentUser): ?>
    <div class="user-box">
      <div class="ub-avatar"><?= View::e(strtoupper(mb_substr($currentUser['nom'], 0, 1))) ?></div>
      <div>
        <div class="ub-name"><?= View::e($currentUser['nom']) ?></div>
        <div class="role"><?= View::e(Permissions::label($currentUser['role'])) ?></div>
      </div>
    </div>
    <form method="post" action="/index.php?r=logout">
      <button type="submit" class="logout"><?= Icon::svg('log-out', 'icon', 15) ?> Déconnexion</button>
    </form>
    <?php endif; ?>
  </aside>
  <div class="main">
    <?php if ($currentUser): ?>
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button type="button" id="sidebarOpen" aria-label="Ouvrir le menu" style="display:none;background:none;border:none;font-size:20px;cursor:pointer;color:#333"><?= Icon::svg('menu') ?></button>
        <?php if ($org): ?><div class="topbar-org"><?= Icon::svg('building', 'icon', 16) ?> <?= View::e($org['nom']) ?></div><?php endif; ?>
      </div>
      <div class="topbar-right">
        <a href="/index.php?r=notifications" class="bell-link" title="Notifications"><?= Icon::svg('bell', 'icon', 18) ?><?php if (!empty($notifNonLues)): ?><span class="bell-dot"><?= $notifNonLues > 9 ? '9+' : $notifNonLues ?></span><?php endif; ?></a>
        <a href="/index.php?r=utilisateurs" class="avatar" title="<?= View::e($currentUser['nom']) ?>"><?= View::e(strtoupper(mb_substr($currentUser['nom'], 0, 1))) ?></a>
      </div>
    </div>
    <?php endif; ?>
    <div class="content">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'erreur' ? 'erreur' : 'succes' ?>"><?= View::e($flash['message']) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script>
(function () {
  var sidebar = document.getElementById('sidebar');
  var openBtn = document.getElementById('sidebarOpen');
  var closeBtn = document.getElementById('sidebarClose');
  if (!sidebar || !openBtn || !closeBtn) { return; }
  function isMobile() { return window.innerWidth <= 768; }
  function applyMode() {
    if (isMobile()) {
      openBtn.style.display = 'inline-block';
      closeBtn.style.display = 'inline-block';
      sidebar.classList.add('sidebar-collapsible');
      if (!sidebar.classList.contains('sidebar-open')) { sidebar.classList.add('sidebar-hidden'); }
    } else {
      openBtn.style.display = 'none';
      closeBtn.style.display = 'none';
      sidebar.classList.remove('sidebar-collapsible', 'sidebar-hidden', 'sidebar-open');
    }
  }
  openBtn.addEventListener('click', function () { sidebar.classList.remove('sidebar-hidden'); sidebar.classList.add('sidebar-open'); });
  closeBtn.addEventListener('click', function () { sidebar.classList.add('sidebar-hidden'); sidebar.classList.remove('sidebar-open'); });
  window.addEventListener('resize', applyMode);
  applyMode();
})();
</script>
</body>
</html>
