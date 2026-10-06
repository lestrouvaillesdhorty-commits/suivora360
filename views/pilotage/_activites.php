<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Pilotage;

$ad = $activitesData;
$lignes = $ad['tableau']['lignes'];
$total = $ad['tableau']['total'];
function paDetailUrl(string $axe, $valeur, array $filters, string $qs): string {
    $params = array_merge($filters, ['axe' => $axe, 'valeur' => (string) $valeur, 'retour' => $qs]);
    unset($params['periode_label']);
    return '/index.php?r=pilotage/detail&' . http_build_query($params);
}
?>
<div class="kpi-row">
  <div class="kpi-card">
    <div class="kpi-icon"><?= Icon::svg('bar-chart-2') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Ventes HT <span class="info-tip" data-tip="Montant HT des cotations liées aux commandes confirmées sur la période, même population que la Vue générale.">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($total['ventes_ht'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($ad['tendances']['ventes_ht'] ?? null) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon green"><?= Icon::svg('pie-chart') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Marge</div>
      <div class="kpi-value"><?= Pilotage::fmt($total['marge'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($ad['tendances']['marge'] ?? null) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon blue"><?= Icon::svg('percent') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Marge / ventes HT</div>
      <div class="kpi-value"><?= Pilotage::fmtPct($total['marge_pct']) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon orange"><?= Icon::svg('shopping-cart') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Nombre de commandes</div>
      <div class="kpi-value"><?= $total['commandes'] ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($ad['tendances']['commandes'] ?? null) ?></div>
    </div>
  </div>
</div>

<div class="chart-row">
  <div class="card">
    <h2>Ventes et marge par activité</h2>
    <?php if (empty($lignes)): ?>
      <div class="empty-state">Aucune commande confirmée sur la période.</div>
    <?php else: ?>
      <?= Pilotage::svgBarChart(
        array_column($lignes, 'activite'),
        ['Ventes HT' => array_column($lignes, 'ventes_ht'), 'Marge' => array_column($lignes, 'marge')],
        ['Ventes HT' => '#5036F5', 'Marge' => '#14b8a6']
      ) ?>
      <div class="linechart-legend" style="margin-top:14px">
        <span class="leg-item"><span class="dot" style="background:#5036F5"></span>Ventes HT</span>
        <span class="leg-item"><span class="dot" style="background:#14b8a6"></span>Marge</span>
      </div>
      <div class="kpi-note">Détail et accès par activité dans le tableau "Résultats par activité" ci-dessous.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Répartition des ventes HT</h2>
    <?php if (empty($lignes)): ?>
      <div class="empty-state">Aucune donnée.</div>
    <?php else: ?>
      <?php $lignesDonut = array_map(fn($r) => ['activite' => $r['activite'], 'part' => $r['part_ventes']], $lignes); ?>
      <div class="donut-wrap">
        <div class="donut" style="background:<?= Pilotage::cssDonut($lignesDonut) ?>">
          <div class="donut-center"><div class="val"><?= Pilotage::fmt($total['ventes_ht'], $filters['devise']) ?></div><div class="lbl">Ventes HT</div></div>
        </div>
        <div class="donut-legend">
          <?php foreach ($lignes as $i => $r): ?>
            <div class="leg-item" onclick="window.location='<?= paDetailUrl('activite', $r['activite'], $filters, $qs) ?>'">
              <span class="dot" style="background:<?= Pilotage::couleurPour($i) ?>"></span>
              <?= View::e($r['activite']) ?>
              <span class="leg-val"><?= $r['part_ventes'] ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Résultats par activité</h2>
  <?php if (empty($lignes)): ?>
    <div class="empty-state">Aucune commande confirmée sur la période.</div>
  <?php else: ?>
    <table class="pilotage-table responsive-cards">
      <thead><tr><th>Activité</th><th class="num">Commandes</th><th class="num">Ventes HT</th><th class="num">Marge</th><th class="num">Marge / ventes HT</th><th class="num">Part des ventes</th><th class="col-arrow"></th></tr></thead>
      <tbody>
        <?php foreach ($lignes as $r): ?>
        <tr class="row-clickable" onclick="window.location='<?= paDetailUrl('activite', $r['activite'], $filters, $qs) ?>'">
          <td data-label="Activité"><?= View::e($r['activite']) ?></td>
          <td data-label="Commandes" class="num"><?= $r['commandes'] ?></td>
          <td data-label="Ventes HT" class="num"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td>
          <td data-label="Marge" class="num"><?= Pilotage::fmt($r['marge'], $filters['devise']) ?></td>
          <td data-label="Marge/Ventes HT" class="num"><?= Pilotage::fmtPct($r['marge_pct']) ?></td>
          <td data-label="Part des ventes" class="num"><?= $r['part_ventes'] ?> %</td>
          <td class="col-arrow"><?= Icon::rowArrow() ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="row-total">
          <td>Total</td>
          <td class="num"><?= $total['commandes'] ?></td>
          <td class="num"><?= Pilotage::fmt($total['ventes_ht'], $filters['devise']) ?></td>
          <td class="num"><?= Pilotage::fmt($total['marge'], $filters['devise']) ?></td>
          <td class="num"><?= Pilotage::fmtPct($total['marge_pct']) ?></td>
          <td class="num">100 %</td>
          <td></td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php
$activiteDetail = $filters['activite'] ?: ($lignes[0]['activite'] ?? null);
$ligneDetail = null;
foreach ($lignes as $r) {
    if ($r['activite'] === $activiteDetail) { $ligneDetail = $r; break; }
}
?>
<div class="card">
  <h2>Détail de l'activité</h2>
  <?php if (!$ligneDetail): ?>
    <?php /* [corrigé 03/10] $lignes ne contient que les activités ayant au
       moins une commande sur la période : une activité explicitement
       sélectionnée mais sans commande sur la période n'y figure jamais,
       et affichait à tort le même message "Sélectionnez..." que l'absence
       de sélection — distingué ici des deux cas réels. */ ?>
    <div class="empty-state"><?= $filters['activite'] ? "Aucune commande sur cette activité pour la période sélectionnée." : "Sélectionnez une activité dans le filtre pour voir son détail." ?></div>
  <?php else: ?>
    <div class="detail-panel-head">
      <div class="kpi-icon" style="width:52px;height:52px"><?= Icon::svg('package', 'icon', 24) ?></div>
      <div style="flex:1;min-width:150px">
        <div style="font-weight:700;font-size:16px"><?= View::e($ligneDetail['activite']) ?></div>
        <div style="color:#888;font-size:13px"><?= $ligneDetail['commandes'] ?> commande(s)</div>
      </div>
      <div class="detail-panel-stat">
        <div class="v"><?= Pilotage::fmt($ligneDetail['ventes_ht'], $filters['devise']) ?></div>
        <div class="l">de ventes HT</div>
      </div>
      <div class="detail-panel-stat">
        <div class="v" style="color:#059669"><?= Pilotage::fmt($ligneDetail['marge'], $filters['devise']) ?></div>
        <div class="l">de marge</div>
      </div>
    </div>
    <a href="<?= paDetailUrl('activite', $ligneDetail['activite'], $filters, $qs) ?>" class="btn btn-pilotage" style="margin-top:16px;display:flex;justify-content:center">Ouvrir les opérations <?= Icon::svg('arrow-right', 'icon', 15) ?></a>
  <?php endif; ?>
</div>
