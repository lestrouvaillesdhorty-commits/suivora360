<?php use App\Core\View; ?>
<a href="/index.php?r=admin-suivora" style="font-size:13px;color:#666">&larr; Administration Suivora</a>
<h1>Utilisateurs — <?= View::e($org['nom']) ?></h1>
<div class="subtitle">Support Suivora : vous voyez seulement les personnes (nom, e-mail, rôle, état), jamais les demandes, dossiers, prix ou documents. Chaque action est inscrite dans le journal de l'entreprise.</div>

<?php if (!$autorise): ?>
  <div class="alert alert-erreur">Cette entreprise a retiré l'accès du support Suivora. Demandez à son Propriétaire de le réactiver (Sécurité).</div>
<?php else: ?>
<div class="card">
  <table class="responsive-cards">
    <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($utilisateurs as $u): if (!empty($u['is_super_admin'])) { continue; } $actif = !empty($u['actif']); ?>
      <tr>
        <td data-label="Nom"><strong><?= View::e($u['nom']) ?></strong></td>
        <td data-label="E-mail"><?= View::e($u['email']) ?></td>
        <td data-label="Rôle"><?= View::e($u['role']) ?></td>
        <td data-label="État"><span class="badge <?= $actif ? 'badge-green' : 'badge-gray' ?>"><?= $actif ? 'Actif' : 'Désactivé' ?></span></td>
        <td>
          <form method="post" action="/index.php?r=admin-suivora/<?= (int) $org['id'] ?>/utilisateurs/<?= (int) $u['id'] ?>" style="margin:0;display:flex;gap:6px;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <button type="submit" name="action" value="reinit" class="btn btn-sm" style="color:#fff" onclick="return confirm('Réinitialiser le mot de passe de <?= View::e(addslashes($u['nom'])) ?> ? Le propriétaire sera prévenu par e-mail.');">Réinitialiser le mot de passe</button>
            <?php if ($actif): ?>
            <button type="submit" name="action" value="desactiver" class="btn btn-sm btn-secondary" onclick="return confirm('Désactiver ce compte ?');">Désactiver</button>
            <?php else: ?>
            <button type="submit" name="action" value="activer" class="btn btn-sm btn-secondary">Réactiver</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p style="font-size:12px;color:#888;margin-bottom:0">Réinitialiser : un mot de passe provisoire s'affiche une seule fois ; la personne doit le changer à sa première connexion. Vous ne pouvez ni changer un rôle, ni supprimer un compte, ni modifier une adresse e-mail.</p>
</div>
<?php endif; ?>
