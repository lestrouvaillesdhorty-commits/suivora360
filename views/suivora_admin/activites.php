<?php use App\Core\View; ?>
<a href="/index.php?r=admin-suivora" style="font-size:13px;color:#666">&larr; Administration Suivora</a>
<h1>Activités — <?= View::e($org['nom']) ?></h1>
<div class="subtitle">Réservé à l'administrateur Suivora : le Propriétaire de l'entreprise ne peut ni ajouter ni retirer d'activité.</div>

<?php if (!$dispo): ?>
  <div class="card"><div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v56.php</strong>.</div></div>
<?php else: ?>
<div class="card">
  <table class="responsive-cards">
    <thead><tr><th>Activité</th><th>Fonctionne comme</th><th class="num">Demandes</th><th>Active</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($activites as $a): ?>
      <tr>
        <td data-label="Activité"><strong><?= View::e($a['nom']) ?></strong> <?= $a['fournie'] ? '<span style="color:#888;font-size:12px">(fournie)</span>' : '<span class="badge badge-orange">ajoutée</span>' ?></td>
        <td data-label="Fonctionne comme"><?= $a['fournie'] ? '—' : View::e($a['comme']) ?></td>
        <td data-label="Demandes" class="num"><?= (int) $a['nb'] ?></td>
        <td data-label="Active"><span class="badge <?= $a['actif'] ? 'badge-green' : 'badge-gray' ?>"><?= $a['actif'] ? 'Active' : 'Désactivée' ?></span></td>
        <td>
          <form method="post" action="/index.php?r=admin-suivora/<?= (int) $org['id'] ?>/activites" style="margin:0;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="nom" value="<?= View::e($a['nom']) ?>">
            <button type="submit" name="action" value="basculer" class="btn btn-sm btn-secondary"><?= $a['actif'] ? 'Désactiver' : 'Activer' ?></button>
            <?php if (!$a['fournie']): ?>
              <button type="submit" name="action" value="supprimer" class="btn btn-sm btn-secondary" <?= (int) $a['nb'] > 0 ? 'disabled title="Elle a des demandes : désactivez-la"' : 'onclick="return confirm(\'Supprimer cette activité ?\')"' ?>>Supprimer</button>
            <?php endif; ?>
          </form>
          <?php if (!$a['fournie']): ?>
          <form method="post" action="/index.php?r=admin-suivora/<?= (int) $org['id'] ?>/activites" style="margin:6px 0 0;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
            <input type="hidden" name="action" value="renommer">
            <input type="hidden" name="ancien" value="<?= View::e($a['nom']) ?>">
            <input type="text" name="nom" maxlength="60" value="<?= View::e($a['nom']) ?>" style="width:190px" aria-label="Nouveau nom">
            <button type="submit" class="btn btn-sm btn-secondary">Renommer</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p style="font-size:12px;color:#888;margin-bottom:0">Une activité désactivée disparaît des formulaires, filtres, tableau de bord et Pilotage. Ses demandes déjà saisies ne sont pas perdues : elles restent consultables et réapparaissent si vous la réactivez.</p>
</div>

<div class="card">
  <h2>Ajouter une activité à cette entreprise</h2>
  <form method="post" action="/index.php?r=admin-suivora/<?= (int) $org['id'] ?>/activites" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="action" value="ajouter">
    <div class="form-group" style="margin:0"><label>Nom de l'activité *</label><input type="text" name="nom" required maxlength="60" placeholder="Vente de produits"></div>
    <div class="form-group" style="margin:0"><label>Fonctionne comme *</label>
      <select name="comme" required><?php foreach ($bases as $b): ?><option><?= View::e($b) ?></option><?php endforeach; ?></select></div>
    <button class="btn" type="submit">Ajouter l'activité</button>
  </form>
  <div class="alert" style="margin-top:12px">« Fonctionne comme » décide du déroulé du dossier (étapes, mot Fournisseur, Transitaire ou Prestataire). Une activité qui a besoin d'un déroulé vraiment différent demande un développement : prévoyez-le avant de l'ajouter.</div>
</div>
<?php endif; ?>
