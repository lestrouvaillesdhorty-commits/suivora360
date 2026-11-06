<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\FonctionEquipe;
use App\Models\Pilotage;

$fonctionsUtilisateurs = FonctionEquipe::parUtilisateur((int) (\App\Core\Auth::user()['organisation_id'] ?? 0));

$cd = $collaborateursData;
$lignes = $cd['lignes'];
$totaux = $cd['totaux'];
function pcDetailUrl($responsableId, array $filters, string $qs): string {
    $params = array_merge($filters, ['axe' => 'responsable', 'valeur' => (string) $responsableId, 'retour' => $qs]);
    unset($params['periode_label']);
    return '/index.php?r=pilotage/detail&' . http_build_query($params);
}
?>
<?php
$objectifsCollab = $objectifsCollab ?? [];
$objectifsTotaux = $objectifsTotaux ?? null;
$teamMarge = (float) ($totaux['marge_attribuee'] ?? 0);
$teamVentes = (float) ($totaux['ventes_ht'] ?? 0);
$palAv = ['#2F6FED', '#7C5CE0', '#0B9AAE', '#F57C2B', '#1FA463', '#D6409F'];
?>
<?php if ($filters['role'] === 'commercial'): ?>

<div class="kpi-row" style="margin-top:4px">
  <div class="kpi-card">
    <div class="kpi-icon blue"><?= Icon::svg('bar-chart-2') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Ventes HT attribuées <span class="info-tip" data-tip="Attribuées au responsable désigné du dossier, jamais réparties entre plusieurs collaborateurs d'un même dossier.">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($totaux['ventes_ht'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($cd['tendances']['ventes_ht'] ?? null) ?></div>
      <?= Pilotage::avertissementNonConvertis($totaux['ventes_non_convertis']) ?>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon green"><?= Icon::svg('dollar-sign') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Marge attribuée</div>
      <div class="kpi-value"><?= Pilotage::fmt($totaux['marge_attribuee'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($cd['tendances']['marge_attribuee'] ?? null) ?></div>
      <?= Pilotage::avertissementNonConvertis($totaux['marge_non_convertis']) ?>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon violet"><?= Icon::svg('percent') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Taux de marge de l'équipe</div>
      <div class="kpi-value"><?= $teamVentes > 0 ? Pilotage::fmtPct(round($teamMarge / $teamVentes * 100, 1)) : '—' ?></div>
    </div>
  </div>
  <?php if ($objectifsTotaux && $objectifsTotaux['pct'] !== null): ?>
  <div class="kpi-card">
    <div class="kpi-icon orange"><?= Icon::svg('target') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Objectif du mois atteint</div>
      <div class="kpi-value"><?= (int) $objectifsTotaux['pct'] ?> %</div>
      <div class="hbar-track" style="margin-top:8px"><div class="hbar-fill" style="width:<?= min(100, (int) $objectifsTotaux['pct']) ?>%;background:<?= $objectifsTotaux['pct'] >= 100 ? '#1FA463' : '#F57C2B' ?>"></div></div>
    </div>
  </div>
  <?php elseif ($totaux['couts_affectes'] !== null): ?>
  <div class="kpi-card">
    <div class="kpi-icon orange"><?= Icon::svg('dollar-sign') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Contribution nette <span class="info-tip" data-tip="Marge attribuée − frais professionnels saisis sur les dossiers.">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($totaux['contribution_nette'], $filters['devise']) ?></div>
      <div class="kpi-sub">Frais : <?= Pilotage::fmt($totaux['couts_affectes'], $filters['devise']) ?></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if (empty($lignes)): ?>
  <div class="card"><div class="empty-state">Aucun dossier avec commande confirmée sur la période (population : responsable désigné du dossier).</div></div>
<?php else: ?>
<?php
$nbCollab = count($lignes);
$vueCollab = $_GET['vue_collab'] ?? ($nbCollab > 4 ? 'tableau' : 'cartes');
if (!in_array($vueCollab, ['cartes', 'tableau'], true)) { $vueCollab = 'cartes'; }
$baseVue = '/index.php?' . http_build_query(array_merge($_GET, ['r' => 'pilotage', 'onglet' => 'collaborateurs']));
$palAv2 = $palAv;
?>
<div class="cc-barre">
  <div><h2 style="margin:0">Classement des <?= $nbCollab ?> collaborateur<?= $nbCollab > 1 ? 's' : '' ?></h2><div class="cc-sub" style="margin:2px 0 0">Trié par marge attribuée.</div></div>
  <?php if ($nbCollab > 1): ?>
  <div class="cc-toggle"><a href="<?= View::e($baseVue . '&vue_collab=cartes') ?>" class="<?= $vueCollab === 'cartes' ? 'on' : '' ?>">Cartes</a><a href="<?= View::e($baseVue . '&vue_collab=tableau') ?>" class="<?= $vueCollab === 'tableau' ? 'on' : '' ?>">Tableau</a></div>
  <?php endif; ?>
</div>

<?php if ($vueCollab === 'tableau'): ?>
<div class="card cc-tab-card">
<table class="cc-table">
  <thead><tr><th>Rang</th><th>Collaborateur</th><th class="n">Ventes HT</th><th class="n">Marge</th><th class="n">Part de l'équipe</th><th class="n">Transformation</th><th class="n">Objectif du mois</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $rang => $r):
      $uid = (int) $r['utilisateur_id'];
      $o = $objectifsCollab[$uid] ?? null;
      $part = $teamMarge > 0 ? round($r['marge_attribuee'] / $teamMarge * 100, 1) : 0;
  ?>
  <tr>
    <td data-l="Rang" class="rk"><?= $rang + 1 ?></td>
    <td data-l="Collaborateur" class="nm"><?= Icon::avatar($r['nom'], 30) ?><b><?= View::e($r['nom']) ?></b><?= FonctionEquipe::badge($fonctionsUtilisateurs[$uid] ?? null) ?></td>
    <td data-l="Ventes HT" class="n"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td>
    <td data-l="Marge" class="n"><?= Pilotage::fmt($r['marge_attribuee'], $filters['devise']) ?></td>
    <td data-l="Part de l'équipe" class="n"><span class="hbar-track cc-mini"><span class="hbar-fill" style="width:<?= min(100, $part) ?>%;background:#1FA463"></span></span><span><?= Pilotage::fmtPct($part) ?></span></td>
    <td data-l="Transformation" class="n"><?= Pilotage::fmtPct($r['transformation']['taux'] ?? null) ?></td>
    <td data-l="Objectif du mois" class="n"><?php if ($o && $o['objectif'] !== null): ?><span class="hbar-track cc-mini"><span class="hbar-fill" style="width:<?= min(100, (int) ($o['pct'] ?? 0)) ?>%;background:<?= View::e($o['couleur']) ?>"></span></span><span style="color:<?= View::e($o['couleur']) ?>;font-weight:700"><?= (int) ($o['pct'] ?? 0) ?> %</span><?php else: ?><span class="cell-na">—</span><?php endif; ?></td>
    <td class="lk"><a href="<?= pcDetailUrl($uid, $filters, $qs) ?>">Voir</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="collab-grid">
  <?php foreach ($lignes as $rang => $r):
      $uid = (int) $r['utilisateur_id'];
      $o = $objectifsCollab[$uid] ?? null;
      $part = $teamMarge > 0 ? round($r['marge_attribuee'] / $teamMarge * 100, 1) : 0;
      $coul = $palAv[$rang % count($palAv)];
  ?>
  <div class="card collab-card">
    <div class="cc-tete">
      <?= Icon::avatar($r['nom'], 40) ?>
      <div class="cc-nom"><strong><?= View::e($r['nom']) ?></strong><?= FonctionEquipe::badge($fonctionsUtilisateurs[$uid] ?? null) ?></div>
      <div class="cc-rang"><small>Rang</small><b><?= $rang + 1 ?></b></div>
    </div>
    <div class="cc-metriques">
      <div><small>Dossiers</small><b><?= (int) $r['dossiers'] ?></b></div>
      <div><small>Ventes HT</small><b><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></b></div>
      <div><small>Marge</small><b><?= Pilotage::fmt($r['marge_attribuee'], $filters['devise']) ?></b></div>
      <div><small>Transformation</small><b><?= Pilotage::fmtPct($r['transformation']['taux'] ?? null) ?></b></div>
    </div>
    <div>
      <div class="cc-ligne"><span>Part de la marge de l'équipe</span><b><?= Pilotage::fmtPct($part) ?></b></div>
      <div class="hbar-track"><div class="hbar-fill" style="width:<?= min(100, $part) ?>%;background:#1FA463"></div></div>
    </div>
    <?php if ($o && $o['objectif'] !== null): ?>
    <div class="cc-obj">
      <div class="cc-ligne"><span>Objectif du mois : <?= Pilotage::fmt($o['realise'], $filters['devise']) ?> sur <?= Pilotage::fmt($o['objectif'], $filters['devise']) ?></span><b style="color:<?= View::e($o['couleur']) ?>"><?= $o['pct'] !== null ? (int) $o['pct'] . ' %' : '' ?></b></div>
      <div class="hbar-track"><div class="hbar-fill" style="width:<?= min(100, (int) ($o['pct'] ?? 0)) ?>%;background:<?= View::e($o['couleur']) ?>"></div></div>
      <?php if (!empty($o['commission'])): ?><div class="cc-sub">Commission estimée : <strong><?= Pilotage::fmt($o['commission'], $filters['devise']) ?></strong></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($r['couts_affectes'] !== null): ?>
    <div class="cc-obj">
      <div class="cc-ligne"><span>Frais professionnels : <?= Pilotage::fmt($r['couts_affectes'], $filters['devise']) ?></span><b style="color:<?= $r['contribution_nette'] >= 0 ? '#17804B' : '#C2323A' ?>"><?= Pilotage::fmt($r['contribution_nette'], $filters['devise']) ?> net</b></div>
    </div>
    <?php endif; ?>
    <a href="<?= pcDetailUrl($uid, $filters, $qs) ?>" class="cc-lien">Voir ses dossiers</a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="chart-row">
  <div class="card">
    <h2>Ventes et marge<?= $nbCollab > 5 ? ' — 5 premiers' : ' par collaborateur' ?></h2>
    <div class="hbar-list">
      <?php $top = array_slice($lignes, 0, 5); $maxV = max(array_column($top, 'ventes_ht')) ?: 1; ?>
      <?php foreach ($top as $r): ?>
      <div class="hbar-item">
        <div class="hbar-label"><span><?= View::e($r['nom']) ?></span></div>
        <div class="hbar-row"><div class="hbar-track"><div class="hbar-fill" style="width:<?= round($r['ventes_ht'] / $maxV * 100) ?>%;background:#2F6FED"></div></div><div class="hbar-value"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></div></div>
        <div class="hbar-row"><div class="hbar-track"><div class="hbar-fill" style="width:<?= round($r['marge_attribuee'] / $maxV * 100) ?>%;background:#1FA463"></div></div><div class="hbar-value"><?= Pilotage::fmt($r['marge_attribuee'], $filters['devise']) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="linechart-legend" style="margin-top:10px"><span class="leg-item"><span class="dot" style="background:#2F6FED"></span>Ventes HT</span><span class="leg-item"><span class="dot" style="background:#1FA463"></span>Marge</span></div>
    <?php if ($nbCollab > 5): ?><a href="<?= View::e($baseVue . '&vue_collab=tableau') ?>" class="cc-lien">Voir tous (<?= $nbCollab ?>) dans le tableau</a><?php endif; ?>
  </div>

  <div class="card">
    <h2>Suivi fournisseur</h2>
    <div class="kpi-note" style="margin:0 0 10px">Personnes ajoutées sur un dossier pour suivre un fournisseur, sans règle de commission.</div>
    <?php if (empty($cd['collaborateurs_secondaires'])): ?>
      <div class="empty-state">Aucun collaborateur additionnel.</div>
    <?php else: foreach ($cd['collaborateurs_secondaires'] as $cs): ?>
      <div class="cc-sf"><?= Icon::avatar($cs['utilisateur_nom'], 34) ?><div><strong><?= View::e($cs['utilisateur_nom']) ?></strong><div class="cc-sub"><?= (int) $cs['nb_dossiers'] ?> dossier(s) suivi(s)</div></div></div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card">
  <details class="cc-aide">
    <summary>Comment lire ces résultats ?</summary>
    <div class="cc-aide-corps">
      <div><strong>Périmètre</strong><br>Dossiers avec commande confirmée sur la période, attribués au responsable du dossier.</div>
      <div><strong>Coûts</strong><br>Les frais professionnels se saisissent dans l'onglet « Équipe » de chaque dossier. Le temps passé n'existe pas encore.</div>
      <div><strong>Données manquantes</strong><br>Elles sont signalées, jamais remplacées par zéro.</div>
    </div>
  </details>
</div>
<?php endif; ?>

<?php elseif ($filters['role'] === 'achats'): ?>

<div class="card" style="margin-top:16px">
  <h2>Performance Achats <span class="info-tip" data-tip="Délais de réponse fournisseur et conformité des offres, par collaborateur ayant saisi des offres. Les économies ne sont pas calculées : aucune base de prix de référence comparable n'existe dans l'application.">i</span></h2>
  <?php if (empty($achatsData)): ?>
    <div class="empty-state">Aucune offre saisie avec auteur identifié sur la période.</div>
  <?php else: ?>
    <table class="pilotage-table responsive-cards">
      <thead><tr><th>Collaborateur</th><th class="num">Offres saisies</th><th class="num">Délai moyen de réponse fournisseur</th><th class="num">Conformité des offres</th><th class="num">Économies</th></tr></thead>
      <tbody>
        <?php foreach ($achatsData as $r): ?>
        <tr>
          <td data-label="Collaborateur"><?= View::e($r['nom']) ?><?= FonctionEquipe::badge($fonctionsUtilisateurs[$r['utilisateur_id']] ?? null) ?></td>
          <td data-label="Offres" class="num"><?= $r['offres_saisies'] ?></td>
          <td data-label="Délai" class="num"><?= $r['delai_moyen_reponse'] !== null ? $r['delai_moyen_reponse'] . ' j' : 'Non renseigné' ?></td>
          <td data-label="Conformité" class="num"><?= Pilotage::fmtPct($r['conformite_pct']) ?></td>
          <td data-label="Économies" class="num cell-na">Non disponible</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="kpi-note">Délai calculé entre l'envoi de la consultation fournisseur et la première offre reçue, en jours calendaires. "Économies" nécessiterait une base de prix de référence qui n'existe pas encore.</div>
  <?php endif; ?>
</div>

<?php endif; ?>
