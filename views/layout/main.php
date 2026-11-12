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
<html lang="fr" class="no-js">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Suivora360</title>
<link rel="icon" href="/favicon.ico?v=59">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=59">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#0E3A4A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Suivora360">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="stylesheet" href="/assets/css/app.css?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/css/app.css') ?>">
</head>
<?php
// Module en lecture seule pour cette personne : bandeau + boutons de modification masqués.
$moduleCourant = $currentUser ? \App\Core\AccesModules::moduleDeRoute('GET', $path) : null;
$modeLecture = $moduleCourant !== null && \App\Core\AccesModules::niveau($moduleCourant, $currentUser) === \App\Core\AccesModules::LECTURE;
?>
<body<?= $currentUser ? ' data-uid="' . (int) $currentUser['id'] . '"' : '' ?><?= $modeLecture ? ' class="mode-lecture"' : '' ?>>
<script>document.documentElement.classList.remove('no-js');</script>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img src="/assets/img/logo-sidebar.png?v=59" alt="Suivora360" class="brand-logo">
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
      <?php
      // Menu en groupes dépliables : le groupe de la page ouverte est déplié, les autres repliés ; l'état choisi est mémorisé (par appareil).
      $voit = fn(string $m) => !$currentUser || \App\Core\AccesModules::visible($m, $currentUser);
      $admin = $currentUser && Permissions::isAdmin($currentUser['role']);
      $gestParam = $currentUser && Permissions::canManageParametres($currentUser['role']);
      $items = [
        'Suivi' => [
          ['demandes', '/demandes', 'inbox', 'Demandes', true],
          ['demandes', '/a-valider', 'check-circle', 'À valider', $currentUser && Permissions::canWrite($currentUser['role'])],
          ['dossiers', '/dossiers', 'folder', 'Dossiers', true],
        ],
        'Achats' => [
          ['fournisseurs', '/fournisseurs', 'truck', 'Fournisseurs', true],
          ['offres', '/offres', 'tag', 'Offres', true],
          ['comparateur', '/comparateur', 'layers', 'Comparateur', true],
          ['simulateur', '/simulateur', 'sliders', 'Simulateur de prix', true],
        ],
        'Ventes' => [
          ['clients', '/clients', 'users', 'Clients', true],
          ['cotations', '/cotations', 'file-text', 'Cotations', true],
          ['commandes', '/commandes', 'shopping-cart', 'Commandes', true],
          ['factures', '/factures', 'dollar-sign', 'Factures', true],
        ],
        'Administration' => [
          [null, '/filiales', 'building', 'Filiales', $admin],
          [null, '/utilisateurs', 'user', 'Utilisateurs & accès', $admin],
          [null, '/securite', 'lock', 'Sécurité', $admin],
          [null, '/admin-suivora', 'building', 'Administration Suivora', $currentUser && !empty($currentUser['is_super_admin'])],
          ['pilotage', '/pilotage', 'bar-chart-2', 'Pilotage', $currentUser && Permissions::canVoirPilotage($currentUser['role'])],
          ['parametres', '/parametres', 'settings', 'Paramètres', $gestParam],
        ],
      ];
      $pathActif = $path;
      foreach ($items as $groupe => $liens):
          $liens = array_values(array_filter($liens, fn($l) => $l[4] && ($l[0] === null || $voit($l[0]))));
          if (!$liens) { continue; }
          $actifGroupe = false;
          foreach ($liens as $l) { if (str_starts_with($pathActif, $l[1])) { $actifGroupe = true; } }
          $gid = 'g-' . strtolower(preg_replace('/[^a-z]/i', '', $groupe));
      ?>
      <div class="nav-group" data-group="<?= $gid ?>" data-actif="<?= $actifGroupe ? '1' : '0' ?>">
        <button type="button" class="nav-group-head" aria-expanded="<?= $actifGroupe ? 'true' : 'false' ?>">
          <span><?= View::e($groupe) ?></span><span class="nav-group-meta"><span class="nav-count"><?= count($liens) ?></span><span class="nav-chev">▾</span></span>
        </button>
        <div class="nav-group-body">
          <?php foreach ($liens as $l): ?>
          <a href="/index.php?r=<?= ltrim($l[1], '/') ?>" class="<?= navActive($path, $l[1]) ?>"><?= Icon::svg($l[2]) ?><span><?= View::e($l[3]) ?></span><?php if ($l[1] === '/a-valider' && $currentUser && class_exists(\App\Models\EntrantDemande::class)): try { $nbAv = \App\Models\EntrantDemande::compter(\App\Models\Filiale::visibleIdsFor($currentUser)); } catch (\Throwable $eAv) { $nbAv = 0; } if ($nbAv > 0): ?><span class="badge badge-red" style="margin-left:auto"><?= $nbAv ?></span><?php endif; endif; ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </nav>
    <?php if ($currentUser): ?>
    <div class="user-box">
      <div class="ub-avatar"><?= View::e(strtoupper(mb_substr($currentUser['nom'], 0, 1))) ?></div>
      <div>
        <div class="ub-name"><?= View::e($currentUser['nom']) ?></div>
        <div class="role"><?= View::e(\App\Models\RolePerso::libelle($currentUser)) ?></div>
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
      <?php if ($modeLecture): ?><div class="bandeau-lecture">Ce module est en <strong>lecture seule</strong> pour vous : vous pouvez le consulter, sans le modifier.</div><?php endif; ?>
      <?php if (\App\Models\ActiviteAcces::restreint()):
        $nbTot = count(array_filter(\App\Models\Activite::toutes((int) Auth::user()['organisation_id']), fn ($a) => $a['actif']));
        $nbVu = $nbTot - count(\App\Models\ActiviteAcces::cachees()); ?>
        <div class="alert" style="background:#EAF5F7;color:#085560;font-size:13px;padding:8px 14px">Vous voyez <?= $nbVu ?> activité<?= $nbVu > 1 ? 's' : '' ?> sur <?= $nbTot ?>.<?php $lec = array_keys(array_filter(\App\Models\ActiviteAcces::niveaux(), fn ($n) => $n === 'lecture')); if ($lec): ?> En lecture seule : <?= View::e(implode(', ', $lec)) ?>.<?php endif; ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<?php if ($currentUser): ?>
<nav class="tabbar" aria-label="Navigation rapide">
  <a href="/index.php" class="<?= $path === '/' ? 'on' : '' ?>"><?= Icon::svg('home', 'icon', 22) ?><span>Accueil</span></a>
  <a href="/index.php?r=dossiers" class="<?= navActive($path, '/dossiers') ? 'on' : '' ?>"><?= Icon::svg('folder', 'icon', 22) ?><span>Dossiers</span></a>
  <a href="/index.php?r=demandes/nouvelle" class="tb-plus" aria-label="Nouvelle demande"><span class="tb-plus-btn"><?= Icon::svg('plus', 'icon', 24) ?></span></a>
  <a href="/index.php?r=notifications" class="<?= navActive($path, '/notifications') ? 'on' : '' ?>"><?= Icon::svg('bell', 'icon', 22) ?><?php if (!empty($notifNonLues)): ?><i class="tb-dot"></i><?php endif; ?><span>Alertes</span></a>
  <button type="button" id="tabbarMenu"><?= Icon::svg('menu', 'icon', 22) ?><span>Menu</span></button>
</nav>
<?php endif; ?>
<script>
(function () {
  var b = document.getElementById('tabbarMenu'), o = document.getElementById('sidebarOpen');
  if (b && o) { b.addEventListener('click', function (e) { e.stopPropagation(); o.click(); }); }
})();
</script>
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
<script src="/assets/js/menu.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/menu.js') ?>"></script>
<script src="/assets/js/galerie.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/galerie.js') ?>"></script>
<script src="/assets/js/mobile.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/mobile.js') ?>"></script>
<?php if ($currentUser): ?><script src="/assets/js/alertes.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/alertes.js') ?>"></script><?php endif; ?>
<?php if ($currentUser): ?><script src="/assets/js/etapes.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/etapes.js') ?>"></script><?php endif; ?>
<?php if ($currentUser): ?><script src="/assets/js/brouillon.js?v=<?= (int) @filemtime(__DIR__ . '/../../public/assets/js/brouillon.js') ?>"></script><?php endif; ?>
<script>if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); }); }</script>
</body>
</html>
