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
<link rel="icon" href="/favicon.ico">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/css/app.css') ?>">
</head>
<body<?= $currentUser ? ' data-uid="' . (int) $currentUser['id'] . '"' : '' ?>>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="/assets/img/logo-sidebar.png" alt="Suivora360" class="brand-logo">
      <button type="button" id="sidebarClose" class="brand-close" aria-label="Fermer le menu">✕</button>
    </div>
    <div class="brand-tagline">Pilotage opérationnel</div>
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
      <a href="/index.php?r=offres" class="<?= navActive($path, '/offres') ?>"><?= Icon::svg('tag') ?><span>Offres</span></a>
      <a href="/index.php?r=comparateur" class="<?= navActive($path, '/comparateur') ?>"><?= Icon::svg('layers') ?><span>Comparateur</span></a>
      <a href="/index.php?r=cotations" class="<?= navActive($path, '/cotations') ?>"><?= Icon::svg('file-text') ?><span>Cotations</span></a>
      <a href="/index.php?r=commandes" class="<?= navActive($path, '/commandes') ?>"><?= Icon::svg('shopping-cart') ?><span>Commandes</span></a>
      <a href="/index.php?r=factures" class="<?= navActive($path, '/factures') ?>"><?= Icon::svg('dollar-sign') ?><span>Factures</span></a>
      <a href="/index.php?r=clients" class="<?= navActive($path, '/clients') ?>"><?= Icon::svg('users') ?><span>Clients</span></a>
      <a href="/index.php?r=fournisseurs" class="<?= navActive($path, '/fournisseurs') ?>"><?= Icon::svg('truck') ?><span>Fournisseurs</span></a>
      <a href="/index.php?r=simulateur" class="<?= navActive($path, '/simulateur') ?>"><?= Icon::svg('sliders') ?><span>Simulateur de prix</span></a>
      <?php if ($currentUser && (Permissions::isAdmin($currentUser['role']) || Permissions::canManageParametres($currentUser['role']))): ?>
      <div class="section-label">Administration</div>
      <?php if (Permissions::isAdmin($currentUser['role'])): ?>
      <a href="/index.php?r=filiales" class="<?= navActive($path, '/filiales') ?>"><?= Icon::svg('building') ?><span>Filiales</span></a>
      <a href="/index.php?r=utilisateurs" class="<?= navActive($path, '/utilisateurs') ?>"><?= Icon::svg('user') ?><span>Utilisateurs &amp; accès</span></a>
      <a href="/index.php?r=securite" class="<?= navActive($path, '/securite') ?>"><?= Icon::svg('lock') ?><span>Sécurité</span></a>
      <?php endif; ?>
      <?php if (!empty($currentUser['is_super_admin'])): ?>
      <a href="/index.php?r=admin-suivora" class="<?= navActive($path, '/admin-suivora') ?>"><?= Icon::svg('building') ?><span>Administration Suivora</span></a>
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
        <a href="/index.php?r=mon-mot-de-passe" style="font-size:11.5px;color:#aab0c6;text-decoration:underline">Mon mot de passe</a>
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
        <?php
          $filialesSel = \App\Models\Filiale::accessiblesFor($currentUser);
          $filialeActive = \App\Models\Filiale::activeId($currentUser);
          if (count($filialesSel) > 1):
        ?>
        <form method="post" action="/index.php?r=filiale-active" class="topbar-filiale" title="Filiale affichée dans tous les modules">
          <input type="hidden" name="csrf_token" value="<?= View::e(Auth::csrfToken()) ?>">
          <input type="hidden" name="retour" value="<?= View::e(trim($routeParam, '/')) ?>">
          <label for="selFilialeActive">Filiale</label>
          <select id="selFilialeActive" name="filiale_id" onchange="this.form.submit()">
            <option value="0" <?= $filialeActive === null ? 'selected' : '' ?>>Toutes les filiales</option>
            <?php foreach ($filialesSel as $fs): ?>
              <option value="<?= (int) $fs['id'] ?>" <?= $filialeActive === (int) $fs['id'] ? 'selected' : '' ?>><?= View::e($fs['nom']) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="btn btn-sm">OK</button></noscript>
        </form>
        <?php endif; ?>
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
  // [08/10] Fond assombri : un appui n'importe où hors du menu le referme (en plus de la croix).
  var fond = document.createElement('div');
  fond.className = 'sidebar-fond';
  document.body.appendChild(fond);
  function ouvrir() { sidebar.classList.remove('sidebar-hidden'); sidebar.classList.add('sidebar-open'); fond.classList.add('visible'); }
  function fermer() { sidebar.classList.add('sidebar-hidden'); sidebar.classList.remove('sidebar-open'); fond.classList.remove('visible'); }
  openBtn.addEventListener('click', function (e) { e.stopPropagation(); ouvrir(); });
  closeBtn.addEventListener('click', fermer);
  fond.addEventListener('click', fermer);
  document.addEventListener('click', function (e) {
    if (isMobile() && sidebar.classList.contains('sidebar-open') && !sidebar.contains(e.target) && !openBtn.contains(e.target)) { fermer(); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { fermer(); } });
  window.addEventListener('resize', applyMode);
  applyMode();
})();
</script>
<?php if ($currentUser): ?><script src="/assets/js/mdp.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/mdp.js') ?>"></script><?php endif; ?>
<script src="/assets/js/galerie.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/galerie.js') ?>"></script>
<script src="/assets/js/mobile.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/mobile.js') ?>"></script>
<?php if ($currentUser): ?><script src="/assets/js/brouillon.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/brouillon.js') ?>"></script><?php endif; ?>
</body>
</html>
