<?php use App\Core\View; use App\Models\Utilisateur; ?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <div>
    <h1>Demandes</h1>
    <div class="subtitle"><?= count($demandes) ?> résultat<?= count($demandes) > 1 ? 's' : '' ?></div>
  </div>
  <a href="/index.php?r=demandes/nouvelle" class="btn">+ Nouvelle demande</a>
</div>

<form method="get" style="margin-bottom:16px;display:flex;gap:10px">
  <input type="text" name="q" placeholder="Référence, objet, contact..." value="<?= View::e($filters['recherche'] ?? '') ?>" style="max-width:320px">
  <select name="statut" onchange="this.form.submit()">
    <option value="">Tous les statuts</option>
    <option value="a_qualifier" <?= ($filters['statut'] ?? '') === 'a_qualifier' ? 'selected' : '' ?>>À qualifier</option>
    <option value="qualifiee" <?= ($filters['statut'] ?? '') === 'qualifiee' ? 'selected' : '' ?>>Qualifiée</option>
  </select>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
</form>

<?php if (empty($demandes)): ?>
  <div class="card"><div class="empty-state">Aucune demande pour le moment.</div></div>
<?php else: ?>
<table>
  <thead>
    <tr><th>Référence</th><th>Objet / Expéditeur</th><th>Filiale</th><th>Responsable</th><th>Priorité</th><th>Statut</th><th>Échéance</th></tr>
  </thead>
  <tbody>
    <?php foreach ($demandes as $d): ?>
    <tr onclick="window.location='/index.php?r=demandes/<?= $d['id'] ?>'" style="cursor:pointer">
      <td><?= View::e($d['reference']) ?></td>
      <td><strong><?= View::e($d['objet']) ?></strong><br><span style="color:#888"><?= View::e($d['expediteur_nom']) ?></span></td>
      <td><?= View::e($d['filiale_nom']) ?></td>
      <td><?= View::e(Utilisateur::nameOf($d['responsable_id'])) ?></td>
      <td><?= $d['priorite'] === 'haute' ? '<span class="badge badge-red">Haute</span>' : '<span class="badge badge-gray">Normale</span>' ?></td>
      <td>
        <?php if ($d['statut'] === 'a_qualifier'): ?>
          <span class="badge badge-yellow">À qualifier</span>
        <?php else: ?>
          <span class="badge badge-blue">Qualifiée</span>
        <?php endif; ?>
      </td>
      <td><?= $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
