<?php use App\Core\Auth; use App\Core\Permissions; use App\Core\View; ?>
<h1>Utilisateurs &amp; accès</h1>
<div class="subtitle">Propriétaire et Admin d'organisation voient toutes les filiales. Les autres rôles ne voient que les filiales qui leur sont assignées. Le rôle détermine les actions possibles (voir la feuille de route pour le détail).</div>

<div class="card">
  <h2>Ajouter un utilisateur</h2>
  <form method="post" action="/index.php?r=utilisateurs">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-row">
      <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Mot de passe</label><input type="password" name="mot_de_passe" required minlength="8"></div>
      <div class="form-group">
        <label>Rôle</label>
        <select name="role" onchange="document.getElementById('filiales-choice').style.display = ['proprietaire','admin_organisation'].includes(this.value) ? 'none' : 'block'">
          <?php foreach (Permissions::ROLES as $code => $label): ?>
            <?php if ($code === 'proprietaire' && !Auth::isProprietaire()) continue; ?>
            <option value="<?= $code ?>" <?= $code === 'lecture_seule' ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group" id="filiales-choice">
      <label>Filiales accessibles</label>
      <div class="access-grid">
        <?php foreach ($filiales as $f): ?>
          <label><input type="checkbox" name="filiale_ids[]" value="<?= $f['id'] ?>"> <?= View::e($f['nom']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <button type="submit" class="btn">Créer l'utilisateur</button>
  </form>
</div>

<?php if (empty($utilisateurs)): ?>
  <div class="card"><div class="empty-state">Aucun utilisateur pour le moment.</div></div>
<?php else: ?>
<table class="responsive-cards">
  <thead><tr><th>Nom / Email</th><th>Rôle</th><th>Filiales accessibles</th><th>Statut</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($utilisateurs as $u): ?>
    <tr>
      <td data-label="Nom / E-mail">
        <div><?= View::e($u['nom']) ?></div>
        <div style="font-size:12px;color:#666"><?= View::e($u['email']) ?></div>
        <details>
          <summary style="cursor:pointer;font-size:12px;color:#4f46e5">Modifier</summary>
          <form method="post" action="/index.php?r=utilisateurs/<?= $u['id'] ?>/modifier" style="margin-top:8px">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= View::e($u['nom']) ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= View::e($u['email']) ?>" required></div>
            <button type="submit" class="btn btn-sm">Enregistrer</button>
          </form>
        </details>
        <details>
          <summary style="cursor:pointer;font-size:12px;color:#4f46e5">Réinitialiser le mot de passe</summary>
          <form method="post" action="/index.php?r=utilisateurs/<?= $u['id'] ?>/mot-de-passe" style="margin-top:8px">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="mot_de_passe" required minlength="8"></div>
            <button type="submit" class="btn btn-sm btn-secondary">Réinitialiser</button>
          </form>
        </details>
      </td>
      <td data-label="Rôle">
        <form method="post" action="/index.php?r=utilisateurs/<?= $u['id'] ?>/role">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <select name="role" onchange="this.form.submit()" style="font-size:12px">
            <?php foreach (Permissions::ROLES as $code => $label): ?>
              <?php if ($code === 'proprietaire' && !Auth::isProprietaire() && $u['role'] !== 'proprietaire') continue; ?>
              <option value="<?= $code ?>" <?= $u['role'] === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
      <td data-label="Filiales accessibles">
        <?php if (Permissions::seesAllFiliales($u['role'])): ?>
          Toutes (automatique)
        <?php else: ?>
          <?php if (empty($accesParUtilisateur[$u['id']])): ?>
            <!--
              [ajouté 03/10] Avertissement — demande explicite de Marie Laure
              suite à la question soulevée en revoyant ce module : quand un
              utilisateur repasse d'un rôle "toutes filiales" (Propriétaire/
              Admin) à un rôle à accès limité, ses anciennes filiales ne sont
              pas restaurées automatiquement (voir Utilisateur::setFiliales
              vidée au moment de la promotion, dans UtilisateurController::
              updateRole()) — décision retenue : pas de restauration
              automatique (plus sûr, pas d'accès périmé redonné sans
              validation), mais ce badge rend le "zéro filiale" visible tout
              de suite au lieu de le laisser passer silencieusement.
            -->
            <div class="badge badge-red" style="margin-bottom:6px;display:inline-block">Aucune filiale assignée</div>
          <?php endif; ?>
          <form method="post" action="/index.php?r=utilisateurs/<?= $u['id'] ?>/acces">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <div class="access-grid">
              <?php foreach ($filiales as $f): ?>
                <label>
                  <input type="checkbox" name="filiale_ids[]" value="<?= $f['id'] ?>"
                    <?= in_array((int) $f['id'], $accesParUtilisateur[$u['id']] ?? [], true) ? 'checked' : '' ?>
                    onchange="this.form.submit()">
                  <?= View::e($f['nom']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </form>
        <?php endif; ?>
      </td>
      <td data-label="Statut">
        <span class="badge <?= ((int) $u['actif'] === 1) ? 'badge-green' : 'badge-gray' ?>">
          <?= ((int) $u['actif'] === 1) ? 'Actif' : 'Inactif' ?>
        </span>
      </td>
      <td data-label="Actions">
        <?php if ((int) $u['actif'] === 1): ?>
          <?php if ((int) $u['id'] === $currentUserId): ?>
            <span style="font-size:12px;color:#999">(vous)</span>
          <?php else: ?>
            <form method="post" action="/index.php?r=utilisateurs/<?= $u['id'] ?>/desactiver" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <button type="submit" class="btn btn-sm btn-secondary">Désactiver</button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <form method="post" action="/index.php?r=utilisateurs/<?= $u['id'] ?>/activer" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm">Réactiver</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
