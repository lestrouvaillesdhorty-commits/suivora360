<?php use App\Core\View; ?>
<h1>Utilisateurs &amp; accès</h1>
<div class="subtitle">Le rôle dirigeant voit toutes les filiales et les données sensibles. Un employé ne voit que les filiales qui lui sont assignées.</div>

<div class="card">
  <h2>Ajouter un utilisateur</h2>
  <form method="post" action="/index.php?r=utilisateurs">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-row">
      <div class="form-group"><label>Nom</label><input type="text" name="nom" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Mot de passe</label><input type="password" name="mot_de_passe" required minlength="6"></div>
      <div class="form-group">
        <label>Rôle</label>
        <select name="role" onchange="document.getElementById('filiales-choice').style.display = this.value === 'dirigeant' ? 'none' : 'block'">
          <option value="employe">Employé</option>
          <option value="dirigeant">Dirigeant</option>
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
<table>
  <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Filiales accessibles</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($utilisateurs as $u): ?>
    <tr>
      <td><?= View::e($u['nom']) ?></td>
      <td><?= View::e($u['email']) ?></td>
      <td><?= $u['role'] === 'dirigeant' ? '<span class="badge badge-blue">Dirigeant</span>' : '<span class="badge badge-gray">Employé</span>' ?></td>
      <td>
        <?php if ($u['role'] === 'dirigeant'): ?>
          Toutes (automatique)
        <?php else: ?>
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
      <td></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
