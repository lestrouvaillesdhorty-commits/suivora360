<?php
use App\Core\Auth;
use App\Core\Icon;
use App\Core\View;
use App\Models\Pilotage;
use App\Models\FonctionEquipe;

$d = $aTraiterData;
$types = Pilotage::TYPES_A_TRAITER;
$fonctions = FonctionEquipe::parUtilisateur((int) (Auth::user()['organisation_id'] ?? 0));
$noms = [];
foreach ($utilisateurs as $u) {
    $noms[(int) $u['id']] = $u['nom'];
}
$url = function (array $over = []) use ($seuil, $typeFiltre, $respFiltre) {
    $p = array_merge(['r' => 'pilotage', 'onglet' => 'atraiter', 'seuil' => $seuil, 'type' => $typeFiltre, 'resp' => $respFiltre], $over);
    return '/index.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 0));
};
$total = count($d['lignes']);
?>
<div class="direction-banner"><?= Icon::svg('info', 'icon', 14) ?> Ce qui attend depuis plus de <strong><?= (int) $seuil ?> jour(s)</strong>. Les demandes clients non traitées sont en tête ; ouvrez la ligne pour agir ou relancer.</div>

<div class="kpi-row" style="margin-top:16px">
  <?php foreach ($types as $k => [$lib, $coul]): ?>
  <a href="<?= View::e($url(['type' => $typeFiltre === $k ? '' : $k])) ?>" class="kpi-card" style="text-decoration:none;color:inherit;border-top:4px solid <?= $coul ?>;<?= $typeFiltre === $k ? 'box-shadow:0 0 0 2px ' . $coul : '' ?>">
    <div class="kpi-body">
      <div class="kpi-label"><?= View::e($lib) ?></div>
      <div class="kpi-value" style="color:<?= $d['compteurs'][$k] > 0 ? $coul : 'inherit' ?>"><?= (int) $d['compteurs'][$k] ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <form method="get" action="/index.php" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
    <input type="hidden" name="r" value="pilotage"><input type="hidden" name="onglet" value="atraiter">
    <div><label style="display:block;font-size:12px">Attente de plus de</label>
      <select name="seuil" onchange="this.form.submit()"><?php foreach ([1, 2, 3, 5, 7, 14, 30] as $j): ?><option value="<?= $j ?>" <?= $seuil === $j ? 'selected' : '' ?>><?= $j ?> jour<?= $j > 1 ? 's' : '' ?></option><?php endforeach; ?></select></div>
    <div><label style="display:block;font-size:12px">Type</label>
      <select name="type" onchange="this.form.submit()"><option value="">Tous</option><?php foreach ($types as $k => [$lib]): ?><option value="<?= $k ?>" <?= $typeFiltre === $k ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
    <div><label style="display:block;font-size:12px">Responsable</label>
      <select name="resp" onchange="this.form.submit()"><option value="0">Tous</option><?php foreach ($noms as $id => $n): ?><option value="<?= $id ?>" <?= $respFiltre === $id ? 'selected' : '' ?>><?= View::e($n) ?></option><?php endforeach; ?></select></div>
  </form>

  <?php if ($total > 0): ?>
  <div style="margin-bottom:12px"><a class="btn btn-pilotage" href="<?= View::e('/index.php?' . http_build_query(['r' => 'pilotage/export.csv', 'onglet' => 'atraiter', 'seuil' => $seuil, 'type' => $typeFiltre, 'resp' => $respFiltre])) ?>"><?= Icon::svg('download', 'icon', 15) ?> Exporter vers Excel (CSV)</a></div>
  <?php endif; ?>
  <?php if ($total === 0): ?>
    <div class="empty-state">Rien en attente depuis plus de <?= (int) $seuil ?> jour(s). Tout est traité.</div>
  <?php else: ?>
  <div class="table-scroll"><table class="pilotage-table responsive-cards">
    <thead><tr><th>Type</th><th>Élément</th><th>Dossier</th><th>Responsable</th><th class="num">En attente</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['lignes'] as $l): [$lib, $coul] = $types[$l['type']];
      $jc = $l['jours'] >= 7 ? '#E5484D' : '#F57C2B'; ?>
      <tr>
        <td data-label="Type"><span class="badge" style="background:<?= $coul ?>;color:#fff"><?= View::e($lib) ?></span></td>
        <td data-label="Élément"><?= View::e($l['titre']) ?><br><small>depuis le <?= View::e(date('d/m/Y', strtotime($l['depuis']))) ?></small></td>
        <td data-label="Dossier"><?= $l['dossier_id'] ? '<a href="/index.php?r=dossiers/' . (int) $l['dossier_id'] . '">' . View::e($l['dossier_reference']) . '</a>' : '—' ?></td>
        <td data-label="Responsable"><?= $l['responsable_id'] ? View::e($noms[$l['responsable_id']] ?? '—') . FonctionEquipe::badge($fonctions[$l['responsable_id']] ?? null) : '<span class="cell-na">Non assigné</span>' ?></td>
        <td data-label="En attente" class="num"><span class="badge" style="background:<?= $jc ?>;color:#fff"><?= (int) $l['jours'] ?> jour<?= $l['jours'] > 1 ? 's' : '' ?></span></td>
        <td><a class="btn btn-sm" style="color:#fff;white-space:nowrap" href="<?= View::e($l['url']) ?>"><?= $l['type'] === 'demande' ? 'Traiter' : 'Relancer / ouvrir' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (count($d['par_responsable']) > 0): ?>
  <div class="kpi-note" style="margin-top:12px"><?= Icon::svg('info', 'icon', 12) ?> Par responsable :
    <?php foreach ($d['par_responsable'] as $rid => $n): ?><strong><?= $rid ? View::e($noms[$rid] ?? '—') : 'Non assigné' ?></strong> <?= (int) $n ?> · <?php endforeach; ?></div>
  <?php endif; ?>
  <?php endif; ?>
</div>
