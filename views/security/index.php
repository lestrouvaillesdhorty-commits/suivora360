<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\AuditLog;

// [ajouté 06/10] Page Sécurité — journal d'audit (voir SecuriteController).
$avecParams = fn(array $r) => '/index.php?' . http_build_query(array_merge(['r' => 'securite'], $_GET, $r));
$exportParams = http_build_query(array_filter($filters));
$lienEntite = function (array $l): ?string {
    if (empty($l['entite_id'])) return null;
    $routes = ['demande' => 'demandes', 'dossier' => 'dossiers', 'client' => 'clients', 'fournisseur' => 'fournisseurs', 'cotation' => 'cotations'];
    return isset($routes[$l['entite_type']]) ? '/index.php?r=' . $routes[$l['entite_type']] . '/' . (int) $l['entite_id'] : null;
};
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
  <div>
    <h1>Sécurité — journal d'audit</h1>
    <div class="subtitle"><?= (int) $total ?> événement<?= $total > 1 ? 's' : '' ?> · réservé au Propriétaire et à l'Admin d'organisation</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/index.php?r=securite/export<?= $exportParams ? '&' . $exportParams : '' ?>" class="btn btn-secondary">Exporter (Excel / CSV)</a>
    <a href="/index.php?r=securite/imprimable<?= $exportParams ? '&' . $exportParams : '' ?>" target="_blank" class="btn btn-secondary">Imprimer / PDF</a>
  </div>
</div>

<form method="get" class="card filtres-securite" style="margin-top:14px">
  <input type="hidden" name="r" value="securite">
  <select name="utilisateur_id">
    <option value="">Tous les utilisateurs</option>
    <?php foreach ($utilisateurs as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $filters['utilisateur_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option><?php endforeach; ?>
  </select>
  <select name="entite_type">
    <option value="">Tous les types</option>
    <?php foreach (AuditLog::ENTITES as $code => $label): ?><option value="<?= $code ?>" <?= $filters['entite_type'] === $code ? 'selected' : '' ?>><?= View::e($label) ?></option><?php endforeach; ?>
  </select>
  <select name="action">
    <option value="">Toutes les actions</option>
    <?php foreach (AuditLog::LIBELLES as $code => $label): ?><option value="<?= $code ?>" <?= $filters['action'] === $code ? 'selected' : '' ?>><?= View::e($label) ?></option><?php endforeach; ?>
  </select>
  <?php if (count($filiales) > 1): ?>
  <select name="filiale_id">
    <option value="">Toutes les filiales</option>
    <?php foreach ($filiales as $fi): ?><option value="<?= (int) $fi['id'] ?>" <?= (int) $filters['filiale_id'] === (int) $fi['id'] ? 'selected' : '' ?>><?= View::e($fi['nom']) ?></option><?php endforeach; ?>
  </select>
  <?php endif; ?>
  <input type="date" name="date_debut" value="<?= View::e($filters['date_debut']) ?>" title="Du">
  <input type="date" name="date_fin" value="<?= View::e($filters['date_fin']) ?>" title="Au">
  <input type="text" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Rechercher dans les détails">
  <button type="submit" class="btn btn-sm">Filtrer</button>
  <a href="/index.php?r=securite" class="btn btn-sm btn-secondary">Réinitialiser</a>
</form>

<?php if (empty($evenements)): ?>
  <div class="card"><div class="empty-state">Aucun événement ne correspond à ces filtres.</div></div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-scroll">
  <table class="dtable">
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Élément</th><th>Détails</th><?php if (count($filiales) > 1): ?><th>Filiale</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($evenements as $l): $lien = $lienEntite($l); ?>
      <tr>
        <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></td>
        <td><?= View::e($l['utilisateur_nom'] ?? '—') ?></td>
        <td><?= View::e(AuditLog::libelle($l['action'])) ?></td>
        <td><?= View::e(AuditLog::ENTITES[$l['entite_type']] ?? $l['entite_type']) ?><?php if (!empty($l['entite_id'])): ?> <?= $lien ? '<a href="' . $lien . '">#' . (int) $l['entite_id'] . '</a>' : '#' . (int) $l['entite_id'] ?><?php endif; ?></td>
        <td><?= View::e($l['details'] ?? '') ?></td>
        <?php if (count($filiales) > 1): ?><td><?= View::e($l['filiale_nom'] ?? '—') ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:14px;margin-top:16px">
  <?php if ($page > 1): ?><a href="<?= $avecParams(['page' => $page - 1]) ?>" class="btn btn-sm btn-secondary">&larr; Précédent</a><?php endif; ?>
  <span style="font-size:13px;color:#666">Page <?= $page ?> / <?= $totalPages ?></span>
  <?php if ($page < $totalPages): ?><a href="<?= $avecParams(['page' => $page + 1]) ?>" class="btn btn-sm btn-secondary">Suivant &rarr;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
