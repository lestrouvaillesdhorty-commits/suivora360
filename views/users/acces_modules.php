<?php
use App\Core\AccesModules;
use App\Core\Permissions;
use App\Core\View;

$roles = $cles;
?>
<h1>Accès aux modules</h1>
<div class="subtitle">Choisissez, pour chaque rôle, ce qui est visible dans le menu. <a href="/index.php?r=utilisateurs">← Utilisateurs &amp; accès</a></div>

<?php if (!$dispo): ?>
  <div class="card"><div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v52.php</strong>.</div></div>
<?php else: ?>
<div class="card">
  <h2>Préréglages</h2>
  <div style="font-size:13px;color:#666;margin-bottom:10px">Un point de départ à ajuster ensuite. <strong>Petite équipe</strong> : tout le monde fait tout, seuls Pilotage et Paramètres restent réservés à la Finance et aux administrateurs. <strong>Équipe structurée</strong> : chaque métier voit surtout ses modules. Appliquer un préréglage remplace vos réglages actuels de la grille et des actions sensibles (les cas particuliers par personne sont conservés).</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach (['petite' => 'Petite équipe : tout le monde fait tout', 'structuree' => 'Équipe structurée'] as $code => $lib): ?>
    <form method="post" action="/index.php?r=utilisateurs/acces-modules" style="margin:0" onsubmit="return confirm('Appliquer ce préréglage ? Vos réglages actuels seront remplacés.')">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="action" value="prereglage">
      <input type="hidden" name="prereglage" value="<?= $code ?>">
      <button class="btn btn-secondary btn-sm" type="submit"><?= View::e($lib) ?></button>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h2>Rôles de l'entreprise</h2>
  <div style="font-size:13px;color:#666;margin-bottom:10px">Créez vos propres rôles (par exemple « Responsable logistique »). Un rôle <strong>fonctionne comme</strong> un rôle de base, qui décide des règles internes (marge visible, accès aux filiales…). Ses accès aux modules se règlent ensuite dans la grille, qui reçoit une colonne par rôle.</div>
  <?php if (!$rolePersoDispo): ?>
    <div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v55.php</strong>.</div>
  <?php else: ?>
  <table class="responsive-cards">
    <thead><tr><th>Rôle</th><th>Fonctionne comme</th><th class="num">Personnes</th><th></th></tr></thead>
    <tbody>
    <?php foreach (['achats', 'commercial', 'finance', 'lecture_seule'] as $rf): ?>
      <tr><td data-label="Rôle"><?= View::e(Permissions::label($rf)) ?> <span style="color:#888;font-size:12px">(fourni)</span></td><td data-label="Fonctionne comme" style="color:#888">—</td><td data-label="Personnes" class="num"></td><td style="color:#888;font-size:12px">non modifiable</td></tr>
    <?php endforeach; ?>
    <?php foreach ($rolesPerso as $rp): ?>
      <tr>
        <td data-label="Rôle"><strong><?= View::e($rp['nom']) ?></strong> <span class="badge badge-orange">perso</span></td>
        <td data-label="Fonctionne comme"><?= View::e(Permissions::label($rp['base_role'])) ?></td>
        <td data-label="Personnes" class="num"><?= (int) $rp['nb'] ?></td>
        <td>
          <form method="post" action="/index.php?r=utilisateurs/acces-modules" style="margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $rp['id'] ?>">
            <input type="text" name="nom" value="<?= View::e($rp['nom']) ?>" maxlength="60" style="width:170px" aria-label="Nom du rôle">
            <button type="submit" name="action" value="role_renommer" class="btn btn-sm btn-secondary">Renommer</button>
            <button type="submit" name="action" value="role_supprimer" class="btn btn-sm btn-secondary" <?= (int) $rp['nb'] > 0 ? 'disabled title="Encore attribué à ' . (int) $rp['nb'] . ' personne(s)"' : 'onclick="return confirm(\'Supprimer ce rôle ?\')"' ?>>Supprimer</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="font-size:12px;color:#888;margin:6px 0 14px">Un rôle fourni ne se supprime pas. Un rôle perso ne se supprime que s'il n'a plus aucune personne.</div>
  <form method="post" action="/index.php?r=utilisateurs/acces-modules" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="action" value="role_creer">
    <div class="form-group" style="margin:0"><label>Nom du rôle *</label><input type="text" name="nom" required maxlength="60" placeholder="Responsable logistique"></div>
    <div class="form-group" style="margin:0"><label>Fonctionne comme *</label>
      <select name="base" required><?php foreach (\App\Models\RolePerso::BASES as $b): ?><option value="<?= $b ?>"><?= View::e(Permissions::label($b)) ?></option><?php endforeach; ?></select></div>
    <div class="form-group" style="margin:0"><label>Partir des accès de</label>
      <select name="copie"><option value="">— Réglages de départ —</option><?php foreach ($cles as $c): ?><option value="<?= View::e($c) ?>"><?= View::e($libelles[$c] ?? $c) ?></option><?php endforeach; ?></select></div>
    <button class="btn" type="submit">Créer le rôle</button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:13px;margin-bottom:10px;align-items:center">
    <span><span class="niv-pastille niv-complet">Complet</span> voir et modifier</span><span><span class="niv-pastille niv-lecture">Lecture</span> voir seulement, sans pouvoir modifier</span><span><span class="niv-pastille niv-cache">Caché</span> absent du menu, adresse refusée</span>
  </div>
  <div style="font-size:13px;color:#666;margin-bottom:10px">Propriétaire et Admin d'organisation ont toujours tous les accès. Filiales, Utilisateurs et Sécurité restent réservés aux administrateurs.</div>
  <form method="post" action="/index.php?r=utilisateurs/acces-modules">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="action" value="grille">
    <div class="table-scroll"><table class="pilotage-table">
      <thead><tr><th>Module</th><?php foreach ($roles as $r): ?><th><?= View::e(($libelles[$r] ?? $r)) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (AccesModules::GROUPES as $groupe => $mods): ?>
        <tr><td colspan="<?= count($roles) + 1 ?>" style="background:#F3F6F9;font-weight:700;color:#0B7C8A;font-size:12.5px"><?= View::e($groupe) ?></td></tr>
        <?php foreach ($mods as $mod => $lib): ?>
        <tr>
          <td><?= View::e($lib) ?></td>
          <?php foreach ($roles as $r): ?>
          <td><select class="niv niv-<?= View::e($niveaux[$mod][$r] ?? 'complet') ?>" name="n[<?= View::e($mod) ?>][<?= View::e($r) ?>]" aria-label="<?= View::e($lib . ' — ' . ($libelles[$r] ?? $r)) ?>">
            <?php foreach (AccesModules::NIVEAUX as $code => $nom): ?><option value="<?= $code ?>" <?= ($niveaux[$mod][$r] ?? '') === $code ? 'selected' : '' ?>><?= View::e($nom) ?></option><?php endforeach; ?>
          </select></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <h2 style="margin-top:22px">Actions sensibles</h2>
    <div style="font-size:13px;color:#666;margin-bottom:8px">Décisions qui engagent l'entreprise. Cochez les rôles autorisés (Propriétaire et Admin d'organisation le sont toujours).</div>
    <div class="table-scroll"><table class="pilotage-table">
      <thead><tr><th>Action</th><?php foreach ($roles as $r): if (\App\Models\RolePerso::baseDeCle($r) === 'lecture_seule') { continue; } ?><th><?= View::e(($libelles[$r] ?? $r)) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (AccesModules::PERMISSIONS as $code => $def): ?>
        <tr><td><?= View::e($def[0]) ?></td>
        <?php foreach ($roles as $r): if (\App\Models\RolePerso::baseDeCle($r) === 'lecture_seule') { continue; } ?>
          <td style="text-align:center"><input type="checkbox" name="p[<?= $code ?>][<?= $r ?>]" value="1" <?= !empty($perms[$code][$r]) ? 'checked' : '' ?> aria-label="<?= View::e($def[0] . ' — ' . ($libelles[$r] ?? $r)) ?>"></td>
        <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
      <button class="btn" type="submit">Enregistrer</button>
    </div>
  </form>
  <form method="post" action="/index.php?r=utilisateurs/acces-modules" style="margin-top:10px" onsubmit="return confirm('Rétablir les réglages de départ pour tous les rôles ?')">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="action" value="reinit">
    <button class="btn btn-secondary btn-sm" type="submit">Revenir aux réglages de départ</button>
  </form>
</div>

<div class="card">
  <h2>Cas particuliers par personne</h2>
  <div style="font-size:13px;color:#666;margin-bottom:8px">Modifie le niveau d'un module pour une seule personne, sans changer son rôle.</div>
  <?php if (!$exceptions): ?><div style="color:#888;font-size:14px;padding:6px 0">Aucune exception.</div><?php endif; ?>
  <?php foreach ($exceptions as $x):
    $lib = '';
    foreach (AccesModules::GROUPES as $mods) { if (isset($mods[$x['module']])) { $lib = $mods[$x['module']]; } } ?>
  <div style="display:flex;gap:10px;align-items:center;padding:8px 0;border-top:1px solid #eee;flex-wrap:wrap">
    <span style="flex:1;min-width:200px"><strong><?= View::e($x['nom']) ?></strong> (<?= View::e(\App\Models\RolePerso::libelle($x)) ?>) : <?= View::e($lib ?: $x['module']) ?> → <span class="niv-pastille niv-<?= View::e($x['niveau']) ?>"><?= View::e(AccesModules::NIVEAUX[$x['niveau']] ?? $x['niveau']) ?></span></span>
    <form method="post" action="/index.php?r=utilisateurs/acces-modules" style="margin:0">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="action" value="exception_supprimer">
      <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
      <button class="btn btn-sm btn-secondary" type="submit">Supprimer</button>
    </form>
  </div>
  <?php endforeach; ?>
  <form method="post" action="/index.php?r=utilisateurs/acces-modules" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="action" value="exception_ajouter">
    <select name="utilisateur_id" required aria-label="Personne"><option value="">— Personne —</option>
      <?php foreach ($utilisateurs as $u): ?><option value="<?= (int) $u['id'] ?>"><?= View::e($u['nom']) ?> (<?= View::e(\App\Models\RolePerso::libelle($u)) ?>)</option><?php endforeach; ?>
    </select>
    <select name="module" required aria-label="Module"><option value="">— Module —</option>
      <?php foreach (AccesModules::GROUPES as $groupe => $mods): ?><optgroup label="<?= View::e($groupe) ?>"><?php foreach ($mods as $m => $lib): ?><option value="<?= $m ?>"><?= View::e($lib) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
    </select>
    <select name="niveau" required aria-label="Niveau" class="niv niv-complet"><?php foreach (AccesModules::NIVEAUX as $code => $nom): ?><option value="<?= $code ?>"><?= View::e($nom) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm" type="submit">Ajouter</button>
  </form>
</div>
<?php endif; ?>

<script>
(function () {
  // La couleur de la liste suit le niveau choisi (Complet = turquoise, Lecture = orange, Caché = gris).
  document.querySelectorAll('select.niv').forEach(function (sel) {
    function maj() { sel.className = 'niv niv-' + sel.value; }
    sel.addEventListener('change', maj); maj();
  });
})();
</script>
