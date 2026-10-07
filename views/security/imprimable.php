<?php use App\Core\View; use App\Models\AuditLog; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Journal d'audit — Suivora360</title>
<style>
  body { font-family: -apple-system, Arial, sans-serif; color: #1f2430; padding: 30px; }
  h1 { font-size: 20px; margin: 0 0 4px; color: #2D18FA; }
  .meta { font-size: 12px; color: #666; margin-bottom: 18px; line-height: 1.6; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 5px 8px; text-align: left; font-size: 11px; border-bottom: 1px solid #ddd; vertical-align: top; }
  th { background: #f7f8fa; text-transform: uppercase; font-size: 10px; }
  .print-btn { margin-bottom: 20px; }
  @media print { .print-btn { display: none; } thead { display: table-header-group; } tr { page-break-inside: avoid; } }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
<h1>Journal d'audit — Suivora360</h1>
<div class="meta">
  Exporté le <?= date('d/m/Y H:i') ?> par <?= View::e($par) ?><br>
  Filtres : <?= View::e($resume) ?><br>
  <?= count($lignes) ?> événement<?= count($lignes) > 1 ? 's' : '' ?><?= $total > count($lignes) ? ' sur ' . (int) $total . ' (limité aux ' . (int) $max . ' plus récents)' : '' ?>
</div>
<table>
  <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Élément</th><th>Détails</th><th>Filiale</th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $l): ?>
    <tr>
      <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
      <td><?= View::e($l['utilisateur_nom'] ?? '—') ?></td>
      <td><?= View::e(AuditLog::libelle($l['action'])) ?></td>
      <td><?= View::e(AuditLog::ENTITES[$l['entite_type']] ?? $l['entite_type']) ?><?= !empty($l['entite_id']) ? ' #' . (int) $l['entite_id'] : '' ?></td>
      <td><?= View::e($l['details'] ?? '') ?></td>
      <td><?= View::e($l['filiale_nom'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
