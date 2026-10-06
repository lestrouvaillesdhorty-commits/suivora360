<?php use App\Core\View; ?>
<h1>Filiales</h1>
<div class="subtitle"><?= count($filiales) ?> / 5 filiales</div>

<div class="card">
  <h2>Ajouter une filiale</h2>
  <?php if ($maxAtteint): ?>
    <div class="alert alert-erreur">Nombre maximum de filiales atteint (5).</div>
  <?php else: ?>
  <form method="post" action="/index.php?r=filiales" style="display:flex;gap:10px;align-items:flex-end">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group" style="flex:1;margin-bottom:0">
      <label>Nom de la filiale</label>
      <input type="text" name="nom" required placeholder="Ex : Millenium">
    </div>
    <button type="submit" class="btn">Ajouter</button>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($filiales)): ?>
  <div class="card"><div class="empty-state">Aucune filiale pour le moment.</div></div>
<?php else: ?>
<table>
  <thead><tr><th>Nom</th><th>Créée le</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($filiales as $f): ?>
    <tr>
      <td>
        <form method="post" action="/index.php?r=filiales/<?= $f['id'] ?>/renommer" style="display:flex;gap:6px;align-items:center;margin:0">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <input type="text" name="nom" value="<?= View::e($f['nom']) ?>" style="max-width:220px" required>
          <button type="submit" class="btn btn-sm btn-secondary">Renommer</button>
        </form>
      </td>
      <td><?= date('d/m/Y', strtotime($f['created_at'])) ?></td>
      <td>
        <form method="post" action="/index.php?r=filiales/<?= $f['id'] ?>/supprimer" style="margin:0" onsubmit="return confirm('Supprimer la filiale « <?= View::e($f['nom']) ?> » ? Refusé si elle contient déjà des demandes, clients ou fournisseurs.');">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <button type="submit" class="btn btn-sm btn-secondary">Supprimer</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
