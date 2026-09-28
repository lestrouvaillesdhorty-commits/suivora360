<?php
use App\Core\View;
use App\Models\Demande;
?>
<h1>Pilotage</h1>
<div class="subtitle">Analyse par période — statistiques par activité et par responsable, taux de transformation, évolution, retards. Pour l'action rapide au quotidien, voir le <a href="/index.php">tableau de bord</a>.</div>

<form method="get" action="/index.php" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
  <input type="hidden" name="r" value="pilotage">
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">Activité</label>
    <select name="activite">
      <option value="">Toutes les activités</option>
      <?php foreach (Demande::ACTIVITES as $a): ?>
        <option value="<?= View::e($a) ?>" <?= ($filters['activite'] ?? '') === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
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
    <label style="font-size:12px">Reçues du</label>
    <input type="date" name="date_debut" value="<?= View::e($filters['date_debut'] ?? '') ?>">
  </div>
  <div class="form-group" style="margin:0">
    <label style="font-size:12px">au</label>
    <input type="date" name="date_fin" value="<?= View::e($filters['date_fin'] ?? '') ?>">
  </div>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
  <a href="/index.php?r=pilotage" class="btn btn-secondary">Réinitialiser</a>
</form>

<div class="grid-3">
  <div class="stat-tile">
    <div class="value"><?= $kpis['demandes_recues'] ?></div>
    <div class="label">Demandes reçues</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= $kpis['dossiers_crees'] ?></div>
    <div class="label">Dossiers créés</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= $kpis['taux_transformation'] ?>%</div>
    <div class="label">Taux de transformation</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= number_format($kpis['valeur_active'], 0, ',', ' ') ?></div>
    <div class="label">Valeur active (cotations acceptées)</div>
    <div class="kpi-note">Toutes devises confondues</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= number_format($kpis['marge_previsionnelle'], 0, ',', ' ') ?></div>
    <div class="label">Marge prévisionnelle</div>
    <div class="kpi-note">Toutes devises confondues</div>
  </div>
  <a href="/index.php?r=dossiers&statut=en_retard" class="stat-tile stat-tile-link">
    <div class="value" style="<?= $kpis['dossiers_en_retard'] > 0 ? 'color:#991b1b' : '' ?>"><?= $kpis['dossiers_en_retard'] ?></div>
    <div class="label">Dossiers en retard</div>
  </a>
</div>

<div class="grid-2">
  <div class="card">
    <h2>Répartition par activité</h2>
    <?php if (empty($parActivite)): ?>
      <div class="empty-state">Aucune donnée sur cette période.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Activité</th><th>Demandes</th><th>Dossiers</th><th>Transfo.</th><th>Valeur active</th></tr></thead>
        <tbody>
          <?php foreach ($parActivite as $row): ?>
          <tr>
            <td><?= View::e($row['activite']) ?></td>
            <td><?= $row['demandes'] ?></td>
            <td><?= $row['dossiers'] ?></td>
            <td><?= $row['taux_transformation'] ?>%</td>
            <td><?= number_format($row['valeur_active'], 0, ',', ' ') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Répartition par responsable</h2>
    <?php if (empty($parResponsable)): ?>
      <div class="empty-state">Aucune donnée sur cette période.</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Responsable</th><th>Demandes</th><th>Dossiers</th><th>Transfo.</th></tr></thead>
        <tbody>
          <?php foreach ($parResponsable as $row): ?>
          <tr>
            <td><?= View::e($row['responsable']) ?></td>
            <td><?= $row['demandes'] ?></td>
            <td><?= $row['dossiers'] ?></td>
            <td><?= $row['taux_transformation'] ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Évolution des opérations <span style="font-weight:400;font-size:12px;color:#888">(demandes reçues par mois, 6 derniers mois)</span></h2>
  <?php $max = max(1, max(array_column($evolution, 'n'))); ?>
  <div class="bar-chart">
    <?php foreach ($evolution as $m): ?>
      <div class="bar-col">
        <div class="bar-n"><?= $m['n'] ?></div>
        <div class="bar" style="height:<?= $m['n'] > 0 ? max(4, (int) round($m['n'] / $max * 100)) : 0 ?>%"></div>
        <div class="bar-label"><?= $m['label'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <h2>Répartition par collaborateur <span style="font-weight:400;font-size:12px;color:#888">(dossiers suivis en tant que collaborateur additionnel + offres saisies)</span></h2>
  <?php if (empty($parCollaborateur)): ?>
    <div class="empty-state">Aucun collaborateur additionnel assigné, ni offre saisie, sur cette période.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Collaborateur</th><th>Dossiers suivis</th><th>Offres saisies</th></tr></thead>
      <tbody>
        <?php foreach ($parCollaborateur as $row): ?>
        <tr>
          <td><?= View::e($row['collaborateur']) ?></td>
          <td><?= $row['dossiers_suivis'] ?></td>
          <td><?= $row['offres_saisies'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Retards <span style="font-weight:400;font-size:12px;color:#888">(dossiers actifs, échéance dépassée)</span></h2>
  <?php if (empty($retards)): ?>
    <div class="empty-state">Aucun dossier en retard.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Référence</th><th>Objet</th><th>Activité</th><th>Échéance</th></tr></thead>
      <tbody>
        <?php foreach ($retards as $d): ?>
        <tr onclick="window.location='/index.php?r=dossiers/<?= $d['id'] ?>'" style="cursor:pointer">
          <td><?= View::e($d['reference']) ?></td>
          <td><?= View::e($d['objet']) ?></td>
          <td><?= View::e($d['activite'] ?: '—') ?></td>
          <td><?= date('d/m/Y', strtotime($d['echeance'])) ?> <span class="badge badge-red">En retard</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
