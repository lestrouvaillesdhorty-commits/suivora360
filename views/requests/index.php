<?php use App\Core\View; use App\Models\Utilisateur; use App\Models\Demande; ?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <div>
    <h1>Demandes</h1>
    <div class="subtitle"><?= count($demandes) ?> résultat<?= count($demandes) > 1 ? 's' : '' ?></div>
  </div>
  <a href="/index.php?r=demandes/nouvelle" class="btn">+ Nouvelle demande</a>
</div>

<?php
$statutsFiltre = [
    'a_qualifier' => 'À qualifier',
    'en_attente_info' => "En attente d'infos",
    'en_retard' => 'En retard',
    'qualifiee' => 'Qualifiée',
    'rattachee' => 'Rattachée',
    'transformee' => 'Transformée en dossier',
    'rejetee' => 'Rejetée',
    'archivee' => 'Archivée',
];
$statutBadges = [
    'a_qualifier' => ['À qualifier', 'badge-yellow'],
    'en_attente_info' => ["En attente d'infos", 'badge-yellow'],
    'qualifiee' => ['Qualifiée', 'badge-blue'],
    'rattachee' => ['Rattachée', 'badge-blue'],
    'transformee' => ['Transformée en dossier', 'badge-green'],
    'rejetee' => ['Rejetée', 'badge-red'],
    'archivee' => ['Archivée', 'badge-gray'],
];
$nonQualifiee = ['a_qualifier', 'en_attente_info'];
?>
<form method="get" action="/index.php" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="r" value="demandes">
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Recherche</label>
    <input type="text" name="q" placeholder="Référence, objet, contact..." value="<?= View::e($filters['recherche'] ?? '') ?>" style="max-width:240px">
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Statut</label>
    <select name="statut">
      <option value="">Tous les statuts</option>
      <?php foreach ($statutsFiltre as $code => $label): ?>
        <option value="<?= $code ?>" <?= ($filters['statut'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Responsable</label>
    <select name="responsable_id">
      <option value="">Tous</option>
      <?php foreach ($utilisateurs as $u): ?>
        <option value="<?= $u['id'] ?>" <?= (string) ($filters['responsable_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Reçue du</label>
    <input type="date" name="date_debut" value="<?= View::e($filters['date_debut'] ?? '') ?>">
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">au</label>
    <input type="date" name="date_fin" value="<?= View::e($filters['date_fin'] ?? '') ?>">
  </div>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
  <a href="/index.php?r=demandes" class="btn btn-secondary">Réinitialiser</a>
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
      <td><span class="badge <?= Demande::PRIORITE_BADGES[$d['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$d['priorite']] ?? ucfirst($d['priorite']) ?></span></td>
      <td>
        <?php $si = $statutBadges[$d['statut']] ?? [ucfirst($d['statut']), 'badge-gray']; ?>
        <span class="badge <?= $si[1] ?>"><?= $si[0] ?></span>
      </td>
      <td>
        <?php $enRetard = $d['echeance'] && strtotime($d['echeance']) < strtotime('today') && in_array($d['statut'], $nonQualifiee, true); ?>
        <?= $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '—' ?>
        <?php if ($enRetard): ?> <span class="badge badge-red">En retard</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
