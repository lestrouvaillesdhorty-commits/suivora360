<?php use App\Core\Icon; use App\Core\View; use App\Models\Demande; use App\Models\Dossier; use App\Models\Utilisateur; ?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
  <div>
    <h1>Dossiers</h1>
    <div class="subtitle"><?= count($dossiers) ?> dossier<?= count($dossiers) > 1 ? 's' : '' ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/index.php?r=dossiers/export.csv&<?= http_build_query($filters) ?>" class="btn btn-secondary"><?= Icon::svg('download', 'icon', 15) ?> Exporter (CSV)</a>
    <a href="/index.php?r=dossiers/importer" class="btn btn-secondary"><?= Icon::svg('upload', 'icon', 15) ?> Importer</a>
  </div>
</div>

<form method="get" action="/index.php" style="margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="r" value="dossiers">
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Recherche</label>
    <input type="text" name="q" placeholder="Référence, objet..." value="<?= View::e($filters['recherche'] ?? '') ?>" style="max-width:220px">
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Statut</label>
    <select name="statut">
      <option value="">Tous</option>
      <option value="actif" <?= ($filters['statut'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
      <option value="en_retard" <?= ($filters['statut'] ?? '') === 'en_retard' ? 'selected' : '' ?>>En retard</option>
      <option value="cloture" <?= ($filters['statut'] ?? '') === 'cloture' ? 'selected' : '' ?>>Clôturé</option>
    </select>
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Étape</label>
    <select name="etape">
      <option value="">Toutes</option>
      <?php foreach (Dossier::ETAPES as $etape): ?>
        <option value="<?= $etape ?>" <?= ($filters['etape'] ?? '') === $etape ? 'selected' : '' ?>><?= Dossier::ETAPES_LABELS[$etape] ?></option>
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
    <label style="font-size:12px">Créé du</label>
    <input type="date" name="date_debut" value="<?= View::e($filters['date_debut'] ?? '') ?>">
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">au</label>
    <input type="date" name="date_fin" value="<?= View::e($filters['date_fin'] ?? '') ?>">
  </div>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
  <a href="/index.php?r=dossiers" class="btn btn-secondary">Réinitialiser</a>
</form>

<?php if (empty($dossiers)): ?>
  <div class="card"><div class="empty-state">Aucun dossier pour le moment.</div></div>
<?php else: ?>
<table>
  <thead>
    <tr><th>Référence</th><th>Objet</th><th>Filiale</th><th>Étape</th><th>Avancement</th><th>Responsable</th><th>Priorité</th><th>Échéance</th><th>Statut</th></tr>
  </thead>
  <tbody>
    <?php foreach ($dossiers as $d): ?>
    <?php $enRetard = $d['statut'] === 'actif' && $d['echeance'] && strtotime($d['echeance']) < strtotime('today'); ?>
    <tr onclick="window.location='/index.php?r=dossiers/<?= $d['id'] ?>'" style="cursor:pointer">
      <td><a href="/index.php?r=dossiers/<?= $d['id'] ?>"><?= View::e($d['reference']) ?></a></td>
      <td><?= View::e($d['objet']) ?></td>
      <td><?= View::e($d['filiale_nom']) ?></td>
      <td><span class="badge badge-blue"><?= Dossier::ETAPES_LABELS[$d['etape']] ?? $d['etape'] ?></span></td>
      <td style="min-width:110px">
        <?php $prog = Dossier::progression($d['etape']); ?>
        <div style="background:#f1f2f5;border-radius:6px;height:6px;overflow:hidden;margin-bottom:3px" title="<?= View::e(implode(' → ', Dossier::ETAPES_LABELS)) ?>">
          <div style="background:<?= $prog === 100 ? '#16a34a' : '#4f46e5' ?>;height:100%;width:<?= $prog ?>%"></div>
        </div>
        <span style="font-size:12px;color:#666"><?= $prog ?>%</span>
      </td>
      <td><?= View::e(Utilisateur::nameOf($d['responsable_id'])) ?></td>
      <td><span class="badge <?= Demande::PRIORITE_BADGES[$d['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$d['priorite']] ?? ucfirst($d['priorite']) ?></span></td>
      <td>
        <?= $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '—' ?>
        <?php if ($enRetard): ?> <span class="badge badge-red">En retard</span><?php endif; ?>
      </td>
      <td>
        <?php $sc = ['actif' => 'green', 'cloture' => 'gray'][$d['statut']] ?? 'gray'; ?>
        <span class="badge badge-<?= $sc ?>"><?= ucfirst($d['statut']) ?></span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
