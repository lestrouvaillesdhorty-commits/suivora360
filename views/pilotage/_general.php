<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Pilotage;

$vg = $vueGenerale;
function pdDetailUrl(string $axe, $valeur, array $filters, string $qs): string {
    $params = array_merge($filters, ['axe' => $axe, 'valeur' => (string) $valeur, 'retour' => $qs]);
    unset($params['periode_label']);
    return '/index.php?r=pilotage/detail&' . http_build_query($params);
}
?>
<div class="kpi-row">
  <div class="kpi-card clickable" onclick="window.location='<?= pdDetailUrl('tout', '', $filters, $qs) ?>'">
    <div class="kpi-icon"><?= Icon::svg('bar-chart-2') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Montant HT des commandes confirmées <span class="info-tip" data-tip="Somme du montant HT de la cotation liée à chaque commande créée sur la période (date de création de la commande). Une commande confirmée ne signifie pas qu'elle est payée.">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($vg['montant_ht_commandes']['total'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= $vg['nb_commandes'] ?> commande(s) <?= Pilotage::badgeTendance($vg['montant_ht_commandes_tendance']) ?></div>
      <?php if (!empty($vg['montant_ht_commandes']['non_convertis'])): ?>
        <div class="kpi-warn"><?= Icon::svg('alert-triangle', 'icon', 12) ?> <?= array_sum(array_column($vg['montant_ht_commandes']['non_convertis'], 'n')) ?> montant(s) non converti(s)</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon green"><?= Icon::svg('pie-chart') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Marge <?= $filters['base'] === 'previsionnel' ? 'prévisionnelle' : 'réalisée' ?> <span class="info-tip" data-tip="<?= $filters['base'] === 'previsionnel' ? 'Somme de la marge des cotations acceptées sur la période (date d\'acceptation non trackée séparément : date de création de la cotation utilisée).' : 'Somme de la marge des cotations acceptées et facturées (facture émise, non annulée) sur la période. Chaque cotation comptée une seule fois même si plusieurs factures y sont rattachées.' ?>">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($vg['marge']['montant']['total'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($vg['marge_tendance']) ?></div>
      <?php if (!empty($vg['marge']['non_renseignees'])): ?>
        <div class="kpi-warn"><?= Icon::svg('alert-triangle', 'icon', 12) ?> <?= $vg['marge']['non_renseignees'] ?> cotation(s) sans montant d'achat renseigné — marge non calculable, exclue(s) du total</div>
      <?php endif; ?>
      <?php if ($vg['marge']['encaissements']): ?>
        <div class="kpi-sub">Dont encaissé (factures payées) : <?= Pilotage::fmt($vg['marge']['encaissements']['total'], $filters['devise']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon blue"><?= Icon::svg('percent') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Taux de transformation des cotations <span class="info-tip" data-tip="Cotations acceptées ÷ cotations soumises (envoyées + acceptées + refusées) sur la période, chaque cotation comptée une seule fois.">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmtPct($vg['transformation']['taux']) ?></div>
      <div class="kpi-sub"><?= $vg['transformation']['numerateur'] ?> acceptée(s) / <?= $vg['transformation']['denominateur'] ?> soumise(s) <?= Pilotage::badgeTendance($vg['transformation_tendance']) ?></div>
    </div>
  </div>

  <div class="kpi-card">
    <div class="kpi-icon orange"><?= Icon::svg('folder') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Valeur des dossiers actifs <span class="info-tip" data-tip="Photographie à date : somme de la dernière cotation acceptée de chaque dossier actuellement actif. Ne dépend pas de la période sélectionnée (seuls activité/responsable s'appliquent).">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($vg['valeur_dossiers_actifs']['total'], $filters['devise']) ?></div>
      <div class="kpi-sub">Indépendant de la période</div>
    </div>
  </div>
</div>

<?php if (!empty($vg['recommandations'])): ?>
<div class="card alert-card">
  <h2 style="display:flex;align-items:center;gap:8px;color:#b91c1c"><?= Icon::svg('target', 'icon', 17) ?> Points d'attention — rentabilité</h2>
  <div class="reco-list">
    <?php foreach ($vg['recommandations'] as $r): ?>
      <div class="reco-item niveau-<?= $r['niveau'] ?>">
        <div class="reco-icon"><?= Icon::svg($r['icone'], 'icon', 16) ?></div>
        <div class="reco-body">
          <div class="reco-titre"><?= View::e($r['titre']) ?></div>
          <div class="reco-message"><?= View::e($r['message']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="kpi-note">Détection automatique basée sur vos données réelles de la période (aucune donnée inventée) — seuils : écart de marge, baisse vs période précédente, concentration des ventes, taux de transformation.</div>
</div>
<?php endif; ?>

<div class="chart-row">
  <div class="card">
    <h2>Évolution mensuelle des commandes HT et de la marge</h2>
    <?php if (empty($vg['evolution_mensuelle'])): ?>
      <div class="empty-state">Pas encore de données sur cette fenêtre.</div>
    <?php else: ?>
      <?= Pilotage::svgLineChart(
        ['Montant HT' => array_column($vg['evolution_mensuelle'], 'ventes'), 'Marge' => array_column($vg['evolution_mensuelle'], 'marge')],
        array_column($vg['evolution_mensuelle'], 'label')
      ) ?>
      <div class="linechart-legend">
        <span class="leg-item"><span class="dot" style="background:<?= Pilotage::couleurPour(0) ?>"></span>Montant HT</span>
        <span class="leg-item"><span class="dot" style="background:<?= Pilotage::couleurPour(1) ?>"></span>Marge</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Répartition des montants par activité</h2>
    <?php if (empty($vg['repartition_activite']['lignes'])): ?>
      <div class="empty-state">Aucune commande confirmée sur la période.</div>
    <?php else: ?>
      <div class="donut-wrap">
        <div class="donut" style="background:<?= Pilotage::cssDonut($vg['repartition_activite']['lignes']) ?>">
          <div class="donut-center"><div class="val"><?= Pilotage::fmt($vg['repartition_activite']['total'], $filters['devise']) ?></div><div class="lbl">Montant HT</div></div>
        </div>
        <div class="donut-legend">
          <?php foreach ($vg['repartition_activite']['lignes'] as $i => $l): ?>
            <div class="leg-item" onclick="window.location='<?= pdDetailUrl('activite', $l['activite'], $filters, $qs) ?>'">
              <span class="dot" style="background:<?= Pilotage::couleurPour($i) ?>"></span>
              <?= View::e($l['activite']) ?>
              <span class="leg-val"><?= $l['part'] ?>%</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="chart-row">
  <div class="card">
    <h2>Cotations émises</h2>
    <?php $cs = $vg['cotations_statuts']; ?>
    <?php if ($cs['total_emis'] === 0): ?>
      <div class="empty-state">Aucune cotation soumise sur la période.</div>
    <?php else: ?>
      <div class="hbar-list">
        <div class="hbar-item">
          <div class="hbar-label"><span>Acceptées</span><span class="hbar-value"><?= $cs['acceptee'] ?></span></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= $cs['total_emis'] > 0 ? round($cs['acceptee'] / $cs['total_emis'] * 100) : 0 ?>%;background:#10b981"></div></div>
        </div>
        <div class="hbar-item">
          <div class="hbar-label"><span>Refusées</span><span class="hbar-value"><?= $cs['refusee'] ?></span></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= $cs['total_emis'] > 0 ? round($cs['refusee'] / $cs['total_emis'] * 100) : 0 ?>%;background:#ef4444"></div></div>
        </div>
        <div class="hbar-item">
          <div class="hbar-label"><span>En attente</span><span class="hbar-value"><?= $cs['envoyee'] ?></span></div>
          <div class="hbar-track"><div class="hbar-fill" style="width:<?= $cs['total_emis'] > 0 ? round($cs['envoyee'] / $cs['total_emis'] * 100) : 0 ?>%;background:#f59e0b"></div></div>
        </div>
      </div>
      <?php if (!empty($vg['cotations_urgentes'])): ?>
        <div class="kpi-warn" style="margin-top:8px"><?= Icon::svg('alert-triangle', 'icon', 12) ?> Dont <?= $vg['cotations_urgentes'] ?> en attente depuis plus de 7 jours — <a href="/index.php?r=dossiers">à relancer</a></div>
      <?php endif; ?>
      <div class="kpi-note">Total émis (hors brouillons) : <?= $cs['total_emis'] ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Délais moyens <span class="info-tip" data-tip="Calculés uniquement sur les étapes déjà terminées dans la période, en jours calendaires (pas des jours ouvrés).">i</span></h2>
    <?php $dl = $vg['delais']; ?>
    <div class="info-row"><div class="label">Qualification (demande → dossier)</div><div><?= $dl['qualification']['moyenne_jours'] !== null ? $dl['qualification']['moyenne_jours'] . ' j' : 'Non renseigné' ?> <span style="color:#aaa;font-size:12px">(<?= $dl['qualification']['n'] ?>)</span></div></div>
    <div class="info-row"><div class="label">Réponse fournisseur</div><div><?= $dl['reponse_fournisseur']['moyenne_jours'] !== null ? $dl['reponse_fournisseur']['moyenne_jours'] . ' j' : 'Non renseigné' ?> <span style="color:#aaa;font-size:12px">(<?= $dl['reponse_fournisseur']['n'] ?>)</span></div></div>
    <div class="info-row"><div class="label">Préparation de cotation</div><div><?= $dl['preparation_cotation']['moyenne_jours'] !== null ? $dl['preparation_cotation']['moyenne_jours'] . ' j' : 'Non renseigné' ?> <span style="color:#aaa;font-size:12px">(<?= $dl['preparation_cotation']['n'] ?>)</span></div></div>
    <div class="kpi-note">Unité : <?= $dl['unite'] ?></div>
  </div>
</div>

<div class="card">
  <h2>Résultats par activité</h2>
  <?php if (empty($vg['resultats_activite']['lignes'])): ?>
    <div class="empty-state">Aucune commande confirmée sur la période sélectionnée.</div>
  <?php else: ?>
    <table class="pilotage-table responsive-cards">
      <thead><tr><th>Activité</th><th class="num">Commandes</th><th class="num">Montant HT</th><th class="num">Marge</th><th class="num">Marge / ventes HT</th><th class="col-arrow"></th></tr></thead>
      <tbody>
        <?php foreach ($vg['resultats_activite']['lignes'] as $r): ?>
        <tr class="row-clickable" onclick="window.location='<?= pdDetailUrl('activite', $r['activite'], $filters, $qs) ?>'">
          <td data-label="Activité"><?= View::e($r['activite']) ?></td>
          <td data-label="Commandes" class="num"><?= $r['commandes'] ?></td>
          <td data-label="Montant HT" class="num"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td>
          <td data-label="Marge" class="num"><?= Pilotage::fmt($r['marge'], $filters['devise']) ?><?php if ($r['marge_manquante'] > 0): ?><br><span class="cell-na"><?= $r['marge_manquante'] ?> non renseignée(s)</span><?php endif; ?></td>
          <td data-label="Marge/Ventes HT" class="num"><?= Pilotage::fmtPct($r['marge_pct']) ?></td>
          <td class="col-arrow"><?= Icon::rowArrow() ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="row-total">
          <td>Total</td>
          <td class="num"><?= $vg['resultats_activite']['total']['commandes'] ?></td>
          <td class="num"><?= Pilotage::fmt($vg['resultats_activite']['total']['ventes_ht'], $filters['devise']) ?></td>
          <td class="num"><?= Pilotage::fmt($vg['resultats_activite']['total']['marge'], $filters['devise']) ?></td>
          <td class="num"><?= Pilotage::fmtPct($vg['resultats_activite']['total']['marge_pct']) ?></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    <div class="kpi-note">Pourcentage global calculé à partir des totaux (pas de la moyenne des lignes). Cliquez une ligne pour voir le détail des opérations.</div>
  <?php endif; ?>
</div>
