<?php
use App\Core\Auth;
use App\Core\Icon;
use App\Core\View;
use App\Models\ObjectifCollaborateur;
use App\Models\Pilotage;

$d = $objectifsData;
$devise = $filters['devise'];
$moisCourant = date('Y-m');
$prec = date('Y-m', strtotime($mois . '-01 -1 month'));
$suiv = date('Y-m', strtotime($mois . '-01 +1 month'));
$libMois = ['01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril', '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août', '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre'];
$titreMois = ($libMois[substr($mois, 5, 2)] ?? '') . ' ' . substr($mois, 0, 4);
$urlMois = fn($m) => '/index.php?r=pilotage&onglet=objectifs&mois=' . $m . '&devise=' . rawurlencode($devise);

$fmtVal = fn($v, $type) => $type === 'money' ? Pilotage::fmt($v, $devise) : (string) (int) round($v);
$exportUrl = '/index.php?r=pilotage/export.csv&onglet=objectifs&mois=' . $mois . '&devise=' . rawurlencode($devise);
?>
<div class="direction-banner"><?= Icon::svg('lock', 'icon', 14) ?> <strong>Vue direction</strong> · Objectifs, commissions et marges individuelles réservés aux rôles Propriétaire / Admin d'organisation / Finance.</div>

<?php if (!$objectifsDispo): ?>
  <div class="card" style="margin-top:16px"><div class="empty-state">Cette fonction demande une mise à jour de la base : ouvrez <code>/migrate_v40.php?token=…</code> une fois, puis revenez ici.</div></div>
<?php else: ?>

<div class="card" style="margin-top:16px">
  <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
    <h2 style="margin:0">Objectifs — <?= View::e($titreMois) ?></h2>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <a class="btn btn-secondary btn-sm" href="<?= $urlMois($prec) ?>">← Mois précédent</a>
      <?php if ($mois !== $moisCourant): ?><a class="btn btn-secondary btn-sm" href="<?= $urlMois($moisCourant) ?>">Mois en cours</a><?php endif; ?>
      <a class="btn btn-secondary btn-sm" href="<?= $urlMois($suiv) ?>">Mois suivant →</a>
      <select onchange="window.location='/index.php?r=pilotage&onglet=objectifs&mois=<?= $mois ?>&devise='+this.value" aria-label="Devise">
        <option value="EUR" <?= $devise === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
        <option value="FCFA" <?= $devise === 'FCFA' ? 'selected' : '' ?>>FCFA</option>
      </select>
    </div>
  </div>
  <p class="kpi-note" style="margin:8px 0 14px"><?= Icon::svg('info', 'icon', 12) ?> Chaque personne a son indicateur : marge, chiffre d'affaires HT ou nombre de commandes confirmées dans le mois. Commission = taux × réalisé (indicateurs en montant). L'attribution se choisit par personne : dossiers dont elle est responsable, ou dossiers où son offre est retenue.</p>

  <form method="post" action="/index.php?r=pilotage/objectifs">
    <input type="hidden" name="csrf_token" value="<?= View::e(Auth::csrfToken()) ?>">
    <input type="hidden" name="mois" value="<?= View::e($mois) ?>">
    <input type="hidden" name="devise" value="<?= View::e($devise) ?>">
    <div style="overflow-x:auto"><table class="pilotage-table responsive-cards" style="white-space:nowrap">
      <thead><tr>
        <th>Collaborateur</th><th>Indicateur</th><th>Attribution</th><th class="num">Objectif</th><th class="num">Réalisé</th><th style="min-width:140px">Avancement</th><th class="num">Reste à faire</th><th>Statut</th><th class="num">Taux commission (%)</th><th class="num">Commission</th>
      </tr></thead>
      <tbody>
      <?php foreach ($d['lignes'] as $l): $uid = $l['utilisateur_id']; $lib = $l['statut']; $coul = $l['couleur']; $t0 = $l['type']; ?>
        <tr>
          <td data-label="Collaborateur"><span class="avatar-with-name"><?= Icon::avatar($l['nom'], 32) ?><?= View::e($l['nom']) ?></span></td>
          <td data-label="Indicateur">
            <select name="indicateur[<?= $uid ?>]">
              <?php foreach (ObjectifCollaborateur::INDICATEURS as $k => $ii): ?>
                <option value="<?= $k ?>" <?= $l['indicateur'] === $k ? 'selected' : '' ?>><?= View::e($ii[0]) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="Attribution">
            <select name="base[<?= $uid ?>]" style="min-width:150px;max-width:190px">
              <?php foreach (ObjectifCollaborateur::BASES as $k => $lb): ?>
                <option value="<?= $k ?>" <?= $l['base'] === $k ? 'selected' : '' ?>><?= View::e($lb) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="Objectif" class="num"><input type="text" inputmode="decimal" name="objectif[<?= $uid ?>]" value="<?= $l['objectif'] !== null ? View::e((string) (float) $l['objectif']) : '' ?>" placeholder="—" style="width:110px;text-align:right"></td>
          <td data-label="Réalisé" class="num"><strong><?= $fmtVal($l['realise'], $t0) ?></strong><?= Pilotage::noteNonConvertis($l['non_convertis']) ?><br><small><?= $l['dossiers'] ?> dossier(s)</small></td>
          <td data-label="Avancement">
            <?php if ($l['pct'] !== null): ?>
              <div style="background:#E6EEF0;border-radius:99px;height:12px;overflow:hidden"><div style="width:<?= min(100, $l['pct']) ?>%;background:<?= $coul ?>;height:100%"></div></div>
              <small><?= $l['pct'] ?> %</small>
            <?php else: ?><span class="cell-na">—</span><?php endif; ?>
          </td>
          <td data-label="Reste à faire" class="num"><?= $l['reste'] !== null ? $fmtVal($l['reste'], $t0) : '—' ?></td>
          <td data-label="Statut"><span class="badge" style="background:<?= $coul ?>;color:#fff"><?= View::e($lib) ?></span></td>
          <td data-label="Taux" class="num"><input type="text" inputmode="decimal" name="taux[<?= $uid ?>]" value="<?= $l['taux'] > 0 ? View::e((string) $l['taux']) : '' ?>" placeholder="0" style="width:70px;text-align:right"></td>
          <td data-label="Commission" class="num"><?= $l['commission'] !== null ? '<strong>' . Pilotage::fmt($l['commission'], $devise) . '</strong>' : '<span class="cell-na">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php $t = $d['totaux']; ?>
      <tfoot><tr>
        <td><strong>Total équipe</strong><br><small>indicateurs en montant</small></td><td></td><td></td>
        <td class="num"><strong><?= Pilotage::fmt($t['objectif'], $devise) ?></strong></td>
        <td class="num"><strong><?= Pilotage::fmt($t['realise'], $devise) ?></strong></td>
        <td><?= $t['pct'] !== null ? '<small>' . $t['pct'] . ' %</small>' : '' ?></td>
        <td class="num"><strong><?= Pilotage::fmt($t['reste'], $devise) ?></strong></td><td></td><td></td>
        <td class="num"><strong><?= Pilotage::fmt($t['commission'], $devise) ?></strong></td>
      </tr></tfoot>
    </table></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
      <button type="submit" class="btn btn-primary" name="action" value="enregistrer">Enregistrer les objectifs et taux</button>
      <button type="submit" class="btn btn-secondary" name="action" value="copier" formnovalidate>Reprendre les objectifs du mois précédent</button>
      <a class="btn btn-pilotage" href="<?= $exportUrl ?>"><?= Icon::svg('download', 'icon', 15) ?> Exporter vers Excel (CSV)</a>
    </div>
  </form>
  <div class="kpi-note" style="margin-top:10px"><?= Icon::svg('info', 'icon', 12) ?> L'indicateur, l'attribution et le taux se règlent une fois par personne et valent pour tous les mois. L'export CSV s'ouvre directement dans Excel : tous les calculs (avancement, reste, commission) sont déjà faits. Les économies sur achats ne sont pas proposées : il faudrait d'abord un prix de référence par article.</div>
</div>
<?php endif; ?>
