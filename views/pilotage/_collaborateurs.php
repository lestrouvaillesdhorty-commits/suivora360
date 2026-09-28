<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Pilotage;

$cd = $collaborateursData;
$lignes = $cd['lignes'];
$totaux = $cd['totaux'];
function pcDetailUrl($responsableId, array $filters, string $qs): string {
    $params = array_merge($filters, ['axe' => 'responsable', 'valeur' => (string) $responsableId, 'retour' => $qs]);
    unset($params['periode_label']);
    return '/index.php?r=pilotage/detail&' . http_build_query($params);
}
?>
<div class="direction-banner"><?= Icon::svg('lock', 'icon', 14) ?> <strong>Vue direction</strong> · Coûts et contributions individuelles confidentiels — réservé aux rôles Propriétaire / Admin d'organisation / Finance, y compris dans les exports.</div>

<?php if ($filters['role'] === 'commercial'): ?>

<div class="kpi-row" style="margin-top:16px">
  <div class="kpi-card">
    <div class="kpi-icon"><?= Icon::svg('bar-chart-2') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Ventes HT attribuées <span class="info-tip" data-tip="Attribuées au responsable désigné du dossier (dossiers.responsable_id), jamais réparties entre plusieurs collaborateurs d'un même dossier.">i</span></div>
      <div class="kpi-value"><?= Pilotage::fmt($totaux['ventes_ht'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($cd['tendances']['ventes_ht'] ?? null) ?></div>
    </div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon green"><?= Icon::svg('dollar-sign') ?></div>
    <div class="kpi-body">
      <div class="kpi-label">Marge attribuée</div>
      <div class="kpi-value"><?= Pilotage::fmt($totaux['marge_attribuee'], $filters['devise']) ?></div>
      <div class="kpi-sub"><?= Pilotage::badgeTendance($cd['tendances']['marge_attribuee'] ?? null) ?></div>
    </div>
  </div>
</div>
<div class="kpi-note" style="margin-top:-6px;margin-bottom:16px"><?= Icon::svg('info', 'icon', 12) ?> Coûts affectés et contribution nette non renseignés — aucune source de données n'existe aujourd'hui (pas de suivi du temps ni de commissions). <span class="info-tip" data-tip="Contribution nette = marge attribuée − coûts affectés. Jamais remplacé par zéro ni estimé.">i</span></div>

<div class="card">
  <h2>Résultats par collaborateur</h2>
  <?php if (empty($lignes)): ?>
    <div class="empty-state">Aucun dossier avec commande confirmée sur la période (population : responsable désigné du dossier).</div>
  <?php else: ?>
    <table class="pilotage-table responsive-cards">
      <thead><tr><th>Collaborateur</th><th class="num">Dossiers</th><th class="num">Ventes HT</th><th class="num">Marge attribuée</th><th class="num">Coûts &amp; contribution</th><th class="num">Transformation</th><th class="col-arrow"></th></tr></thead>
      <tbody>
        <?php foreach ($lignes as $r): ?>
        <tr class="row-clickable" onclick="window.location='<?= pcDetailUrl($r['utilisateur_id'], $filters, $qs) ?>'">
          <td data-label="Collaborateur"><span class="avatar-with-name"><?= Icon::avatar($r['nom'], 32) ?><?= View::e($r['nom']) ?></span></td>
          <td data-label="Dossiers" class="num"><?= $r['dossiers'] ?></td>
          <td data-label="Ventes HT" class="num"><?= Pilotage::fmt($r['ventes_ht'], $filters['devise']) ?></td>
          <td data-label="Marge attribuée" class="num"><?= Pilotage::fmt($r['marge_attribuee'], $filters['devise']) ?></td>
          <td data-label="Coûts & contribution" class="num cell-na">Non renseigné</td>
          <td data-label="Transformation" class="num"><?= Pilotage::fmtPct($r['transformation']['taux'] ?? null) ?></td>
          <td class="col-arrow"><?= Icon::rowArrow() ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="kpi-note">Attribution explicite par responsable de dossier, sans double comptage. Cliquez une ligne pour voir le détail des dossiers et éléments utilisés dans les calculs.</div>
  <?php endif; ?>

  <?php if (!empty($cd['collaborateurs_secondaires'])): ?>
    <h2 style="margin-top:22px">Collaborateurs additionnels (suivi fournisseur)</h2>
    <div class="kpi-note" style="margin-bottom:8px">Personnes assignées au suivi d'un fournisseur précis sur un dossier (fonctionnalité "Collaborateurs de dossier"), sans règle d'attribution financière : ventes/marge/coûts non applicables — affichés "Non renseigné".</div>
    <table class="pilotage-table">
      <thead><tr><th>Collaborateur</th><th class="num">Dossiers suivis</th></tr></thead>
      <tbody>
        <?php foreach ($cd['collaborateurs_secondaires'] as $cs): ?>
        <tr><td><?= View::e($cs['utilisateur_nom']) ?></td><td class="num"><?= $cs['nb_dossiers'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="chart-row">
  <div class="card">
    <h2>Marge attribuée par collaborateur</h2>
    <?php if (empty($lignes)): ?>
      <div class="empty-state">Aucune donnée.</div>
    <?php else: ?>
      <div class="hbar-list">
        <?php $maxM = max(array_column($lignes, 'marge_attribuee')) ?: 1; ?>
        <?php foreach ($lignes as $r): ?>
        <div class="hbar-item">
          <div class="hbar-label"><span><?= View::e($r['nom']) ?></span></div>
          <div class="hbar-row">
            <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($r['marge_attribuee'] / $maxM * 100) ?>%"></div></div>
            <div class="hbar-value"><?= Pilotage::fmt($r['marge_attribuee'], $filters['devise']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="kpi-note" style="margin-top:10px"><?= Icon::svg('info', 'icon', 12) ?> Coûts affectés non représentés — non renseignés (voir note ci-dessus).</div>
    <?php endif; ?>
  </div>

  <div class="card explication-block">
    <h2>Comprendre les résultats</h2>
    <div class="exp-item">
      <div class="exp-icon"><?= Icon::svg('target', 'icon', 16) ?></div>
      <div><div class="exp-title">Périmètre</div><div class="exp-text">Dossiers avec commande confirmée sur la période sélectionnée, attribués au responsable désigné du dossier.</div></div>
    </div>
    <div class="exp-item">
      <div class="exp-icon"><?= Icon::svg('file-text', 'icon', 16) ?></div>
      <div><div class="exp-title">Coûts</div><div class="exp-text">Temps affecté, commissions et frais professionnels — <strong>aucune de ces sources n'existe encore</strong> dans l'application aujourd'hui.</div></div>
    </div>
    <div class="exp-item">
      <div class="exp-icon"><?= Icon::svg('alert-triangle', 'icon', 16) ?></div>
      <div><div class="exp-title">Données manquantes</div><div class="exp-text">Signalées explicitement ("Non renseigné"), jamais remplacées par zéro ni estimées.</div></div>
    </div>
  </div>
</div>

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
          <td data-label="Collaborateur"><?= View::e($r['nom']) ?></td>
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
