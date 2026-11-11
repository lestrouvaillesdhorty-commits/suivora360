<?php
use App\Core\Auth;
use App\Core\Icon;
use App\Core\View;
use App\Models\FonctionEquipe;
use App\Models\ObjectifCollaborateur;
use App\Models\Pilotage;

$d = $objectifsData;
$devise = $filters['devise'];
$per = $periodeObj;
$comp = $comp ?? 'prec';
$compData = $compData ?? null;
$historique = $historique ?? [];
$libreDispo = $libreDispo ?? false;
$titreMois = $per['label'];
$base = '/index.php?r=pilotage&onglet=objectifs&devise=' . rawurlencode($devise) . '&comp=' . rawurlencode($comp);
$urlMois = fn($m) => $base . '&mois=' . rawurlencode($m);
$cleCourante = ObjectifCollaborateur::cleCourante($per['type'] === 'libre' ? 'mois' : $per['type']);

$fonctions = $d['fonctions'] ?? [];
$fonctionsDispo = FonctionEquipe::disponible();
$derniereFct = '__init__';
$fmtVal = fn($v, $type) => $type === 'money' ? Pilotage::fmt($v, $devise) : (string) (int) round($v);
$exportUrl = '/index.php?r=pilotage/export.csv&onglet=objectifs&mois=' . rawurlencode($mois) . '&devise=' . rawurlencode($devise);
?>
<div class="direction-banner"><?= Icon::svg('lock', 'icon', 14) ?> <strong>Vue direction</strong> · Objectifs, commissions et marges individuelles réservés aux rôles Propriétaire / Admin d'organisation / Finance.</div>

<?php if (!$objectifsDispo): ?>
  <div class="card" style="margin-top:16px"><div class="empty-state">Cette fonction demande une mise à jour de la base : ouvrez <code>/migrate_v40.php?token=…</code> une fois, puis revenez ici.</div></div>
<?php else: ?>

<?php
$typesPer = ['mois' => 'Mois', 'trimestre' => 'Trimestre', 'annee' => 'Année'] + ($libreDispo ? ['libre' => 'Dates libres'] : []);
$evol = function (?float $r, ?float $p): string {
    if ($p === null || $p <= 0 || $r === null) { return ''; }
    $dv = ($r - $p) / $p * 100;
    return '<span style="color:' . ($dv >= 0 ? '#17804B' : '#C2323A') . ';font-weight:600;white-space:nowrap">' . ($dv >= 0 ? '▲' : '▼') . ' ' . number_format(abs($dv), 0, ',', ' ') . ' %</span>';
};
$compLabel = $compData ? $compData['periode']['label'] : '';
?>
<div class="card" style="margin-top:16px">
  <div class="obj-periode">
    <div class="obj-champ"><label>Type de période</label>
      <div class="cc-toggle">
        <?php foreach ($typesPer as $k => $lb): ?>
          <a href="<?= View::e($k === 'libre' ? $base . '&mois=' . rawurlencode($per['type'] === 'libre' ? $mois : 'L' . $per['debut'] . '_' . $per['fin']) : $urlMois($per['type'] === $k ? $mois : ObjectifCollaborateur::cleCourante($k))) ?>" class="<?= $per['type'] === $k ? 'on' : '' ?>"><?= $lb ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="obj-champ"><label>Période</label>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <a class="btn btn-secondary btn-sm" href="<?= View::e($urlMois($per['prec'])) ?>" aria-label="Période précédente">←</a>
        <strong><?= View::e($titreMois) ?></strong>
        <a class="btn btn-secondary btn-sm" href="<?= View::e($urlMois($per['suiv'])) ?>" aria-label="Période suivante">→</a>
        <?php if ($per['type'] !== 'libre' && $mois !== $cleCourante): ?><a class="btn btn-secondary btn-sm" href="<?= View::e($urlMois($cleCourante)) ?>">Période en cours</a><?php endif; ?>
      </div>
    </div>
    <div class="obj-champ"><label for="objComp">Comparer à</label>
      <select id="objComp" onchange="window.location='<?= View::e($urlMois($mois)) ?>'.replace(/&comp=[^&]*/, '&comp=' + this.value)">
        <option value="prec" <?= $comp === 'prec' ? 'selected' : '' ?>>Période précédente</option>
        <option value="an" <?= $comp === 'an' ? 'selected' : '' ?>>Même période l'an dernier</option>
        <option value="aucune" <?= $comp === 'aucune' ? 'selected' : '' ?>>Aucune comparaison</option>
      </select>
    </div>
    <div class="obj-champ"><label for="objDev">Devise</label>
      <select id="objDev" onchange="window.location='/index.php?r=pilotage&onglet=objectifs&mois=<?= rawurlencode($mois) ?>&comp=<?= rawurlencode($comp) ?>&devise='+this.value">
        <option value="EUR" <?= $devise === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
        <option value="FCFA" <?= $devise === 'FCFA' ? 'selected' : '' ?>>FCFA</option>
        <option value="XAF" <?= $devise === 'XAF' ? 'selected' : '' ?>>XAF</option>
      </select>
    </div>
  </div>
  <?php if ($per['type'] === 'libre'): ?>
  <form method="get" action="/index.php" class="obj-periode" style="margin-top:10px">
    <input type="hidden" name="r" value="pilotage"><input type="hidden" name="onglet" value="objectifs"><input type="hidden" name="type" value="libre">
    <input type="hidden" name="devise" value="<?= View::e($devise) ?>"><input type="hidden" name="comp" value="<?= View::e($comp) ?>">
    <div class="obj-champ"><label for="objDu">Du</label><input type="date" id="objDu" name="du" value="<?= View::e($per['debut']) ?>" required></div>
    <div class="obj-champ"><label for="objAu">Au</label><input type="date" id="objAu" name="au" value="<?= View::e($per['fin']) ?>" required></div>
    <div class="obj-champ"><label>&nbsp;</label><button type="submit" class="btn btn-secondary btn-sm">Appliquer</button></div>
  </form>
  <?php endif; ?>
  <p class="kpi-note" style="margin:10px 0 4px"><?= Icon::svg('info', 'icon', 12) ?> Chaque objectif est comparé au réalisé sur <strong>sa propre période</strong><?= $compData ? ' ; comparaison avec : ' . View::e($compLabel) : '' ?>.</p>
</div>

<?php $tt = $d['totaux']; $tc = $compData['totaux'] ?? null; ?>
<div class="kpi-row" style="margin-top:14px">
  <div class="kpi-card"><div class="kpi-icon blue"><?= Icon::svg('target') ?></div><div class="kpi-body"><div class="kpi-label">Objectif de l'équipe (montants)</div><div class="kpi-value"><?= Pilotage::fmt($tt['objectif'], $devise) ?></div></div></div>
  <div class="kpi-card"><div class="kpi-icon green"><?= Icon::svg('bar-chart-2') ?></div><div class="kpi-body"><div class="kpi-label">Réalisé</div><div class="kpi-value"><?= Pilotage::fmt($tt['realise'], $devise) ?></div><div class="kpi-sub"><?= $tc ? $evol($tt['realise'], $tc['realise']) . ' vs ' . View::e($compData['periode']['court']) : '' ?></div></div></div>
  <div class="kpi-card"><div class="kpi-icon orange"><?= Icon::svg('percent') ?></div><div class="kpi-body"><div class="kpi-label">Avancement</div><div class="kpi-value"><?= $tt['pct'] !== null ? (int) $tt['pct'] . ' %' : '—' ?></div><?php if ($tt['pct'] !== null): ?><div class="hbar-track" style="margin-top:8px"><div class="hbar-fill" style="width:<?= min(100, (int) $tt['pct']) ?>%;background:<?= $tt['pct'] >= 100 ? '#1FA463' : '#F5A524' ?>"></div></div><?php endif; ?></div></div>
  <div class="kpi-card"><div class="kpi-icon violet"><?= Icon::svg('dollar-sign') ?></div><div class="kpi-body"><div class="kpi-label">Commissions estimées</div><div class="kpi-value"><?= Pilotage::fmt($tt['commission'], $devise) ?></div><div class="kpi-sub"><?= $tc ? $evol($tt['commission'], $tc['commission']) . ' vs ' . View::e($compData['periode']['court']) : '' ?></div></div></div>
</div>

<div class="card" style="margin-top:14px">
  <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
    <h2 style="margin:0">Objectifs — <?= View::e($titreMois) ?></h2>
  </div>
  <p class="kpi-note" style="margin:8px 0 14px"><?= Icon::svg('info', 'icon', 12) ?> Chaque personne a son indicateur : marge, chiffre d'affaires HT ou nombre de commandes confirmées sur la période. Commission = taux × réalisé (indicateurs en montant). L'attribution se choisit par personne : responsable du dossier, ou auteur de l'offre retenue.</p>

  <form method="post" action="/index.php?r=pilotage/objectifs">
    <input type="hidden" name="csrf_token" value="<?= View::e(Auth::csrfToken()) ?>">
    <input type="hidden" name="mois" value="<?= View::e($mois) ?>"><input type="hidden" name="comp" value="<?= View::e($comp) ?>">
    <input type="hidden" name="devise" value="<?= View::e($devise) ?>">
    <div style="overflow-x:auto"><style>.tbl-obj select,.tbl-obj input[type=text]{padding:6px 8px;font-size:13px;height:auto}.tbl-obj td,.tbl-obj th{padding-left:8px;padding-right:8px}.tbl-obj .num{white-space:nowrap}.tbl-obj{font-size:13px}.tbl-obj td,.tbl-obj th{padding-left:5px;padding-right:5px}.tbl-obj select{padding:5px 4px;text-overflow:ellipsis}.tbl-obj select[name^=fonction]{width:104px;min-width:0;max-width:104px}.tbl-obj select[name^=indicateur]{width:104px;min-width:0}.tbl-obj select[name^=base]{width:118px;min-width:0;max-width:118px}.tbl-obj input[name^=objectif]{width:68px!important}.tbl-obj input[name^=taux]{width:46px!important}.tbl-obj .badge{white-space:nowrap}.tbl-obj .avatar-with-name{gap:6px;white-space:nowrap}.tbl-obj td:first-child{min-width:150px}</style>
    <table class="pilotage-table responsive-cards tbl-obj">
      <thead><tr>
        <th>Collaborateur</th><th>Fonction</th><th>Indicateur</th><th>Attribution</th><th class="num">Objectif</th><th class="num">Réalisé<?php if ($compData): ?> <small>(vs <?= View::e($compData['periode']['court']) ?>)</small><?php endif; ?></th><th style="min-width:90px">Avancement / reste</th><th>Statut</th><th class="num">Taux (%)</th><th class="num">Commission</th>
      </tr></thead>
      <tbody>
      <?php foreach ($d['lignes'] as $l):
        $cleFct = (string) ($l['fonction_id'] ?? 0);
        if ($fonctionsDispo && $cleFct !== $derniereFct): $derniereFct = $cleFct; ?>
        <tr class="groupe-fonction"><td colspan="10" style="background:<?= View::e($l['fonction_couleur']) ?>;color:#fff;font-weight:700;padding:6px 12px"><?= View::e($l['fonction'] ?? 'Sans fonction') ?></td></tr>
      <?php endif;
        $uid = $l['utilisateur_id']; $lib = $l['statut']; $coul = $l['couleur']; $t0 = $l['type']; ?>
        <tr>
          <td data-label="Collaborateur"><span class="avatar-with-name"><?= Icon::avatar($l['nom'], 32) ?><?= View::e($l['nom']) ?></span></td>
          <td data-label="Fonction">
            <?php if ($fonctionsDispo): ?>
            <select name="fonction[<?= $uid ?>]" class="sel-fonction" data-uid="<?= $uid ?>">
              <option value="">— Aucune —</option>
              <?php foreach ($fonctions as $f): ?>
                <option value="<?= (int) $f['id'] ?>" data-ind="<?= View::e($f['indicateur']) ?>" data-base="<?= View::e($f['base']) ?>" data-taux="<?= (float) $f['taux_commission'] > 0 ? (float) $f['taux_commission'] : '' ?>" <?= (int) ($l['fonction_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['libelle']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php else: ?><span class="cell-na">—</span><?php endif; ?><br><small style="color:#5B6B7B">reste <?= $l['reste'] !== null ? $fmtVal($l['reste'], $t0) : '—' ?></small>
          </td>
          <td data-label="Indicateur">
            <select name="indicateur[<?= $uid ?>]">
              <?php foreach (ObjectifCollaborateur::INDICATEURS as $k => $ii): ?>
                <option value="<?= $k ?>" <?= $l['indicateur'] === $k ? 'selected' : '' ?>><?= View::e($ii[0]) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="Attribution">
            <select name="base[<?= $uid ?>]">
              <?php foreach (ObjectifCollaborateur::BASES as $k => $lb): ?>
                <option value="<?= $k ?>" <?= $l['base'] === $k ? 'selected' : '' ?>><?= View::e($lb) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="Objectif" class="num"><input type="text" inputmode="decimal" name="objectif[<?= $uid ?>]" value="<?= $l['objectif'] !== null ? View::e((string) (float) $l['objectif']) : '' ?>" placeholder="—" style="width:78px;text-align:right"></td>
          <td data-label="Réalisé" class="num"><strong><?= $fmtVal($l['realise'], $t0) ?></strong><?= Pilotage::noteNonConvertis($l['non_convertis']) ?><br><small><?= $l['dossiers'] ?> dossier(s)</small><?php if ($compData): $pl = $compData['lignes'][$uid] ?? null; if ($pl): ?><br><small style="color:#5B6B7B"><?= $fmtVal($pl['realise'], $t0) ?> <?= $evol($l['realise'], $pl['realise']) ?></small><?php endif; endif; ?></td>
          <td data-label="Avancement">
            <?php if ($l['pct'] !== null): ?>
              <div style="background:#E6EEF0;border-radius:99px;height:12px;overflow:hidden"><div style="width:<?= min(100, $l['pct']) ?>%;background:<?= $coul ?>;height:100%"></div></div>
              <small><?= $l['pct'] ?> %</small>
            <?php else: ?><span class="cell-na">—</span><?php endif; ?>
          </td>
          <td data-label="Statut"><span class="badge" style="background:<?= $coul ?>;color:#fff"><?= View::e($lib) ?></span></td>
          <td data-label="Taux" class="num"><input type="text" inputmode="decimal" name="taux[<?= $uid ?>]" value="<?= $l['taux'] > 0 ? View::e((string) $l['taux']) : '' ?>" placeholder="0" style="width:56px;text-align:right"></td>
          <td data-label="Commission" class="num"><?= $l['commission'] !== null ? '<strong>' . Pilotage::fmt($l['commission'], $devise) . '</strong>' : '<span class="cell-na">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php $t = $d['totaux']; ?>
      <tfoot><tr>
        <td><strong>Total équipe</strong><br><small>indicateurs en montant</small></td><td></td><td></td><td></td>
        <td class="num"><strong><?= Pilotage::fmt($t['objectif'], $devise) ?></strong></td>
        <td class="num"><strong><?= Pilotage::fmt($t['realise'], $devise) ?></strong></td>
        <td><?= $t['pct'] !== null ? '<small>' . $t['pct'] . ' %</small><br>' : '' ?><small>reste <?= Pilotage::fmt($t['reste'], $devise) ?></small></td><td></td><td></td>
        <td class="num"><strong><?= Pilotage::fmt($t['commission'], $devise) ?></strong></td>
      </tr></tfoot>
    </table></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
      <button type="submit" class="btn btn-primary" name="action" value="enregistrer">Enregistrer les objectifs et taux</button>
      <button type="submit" class="btn btn-secondary" name="action" value="copier" formnovalidate>Reprendre les objectifs de la période précédente</button>
      <a class="btn btn-pilotage" href="<?= $exportUrl ?>"><?= Icon::svg('download', 'icon', 15) ?> Exporter vers Excel (CSV)</a>
    </div>
  </form>
  <div class="kpi-note" style="margin-top:10px"><?= Icon::svg('info', 'icon', 12) ?> L'indicateur, l'attribution et le taux se règlent une fois par personne et valent pour toutes les périodes. L'export CSV s'ouvre directement dans Excel : tous les calculs (avancement, reste, commission) sont déjà faits. Les économies sur achats ne sont pas proposées : il faudrait d'abord un prix de référence par article.</div>
</div>

<?php $mx = 0; foreach ($historique as $h0) { $mx = max($mx, $h0['objectif'], $h0['realise']); } if (count($historique) > 1): $nh = count($historique); $mx = max($mx, 1); ?>
<div class="card" style="margin-top:18px">
  <h2>Objectif et réalisé — <?= $nh ?> dernières périodes</h2>
  <p class="kpi-note" style="margin:6px 0 10px">Équipe, indicateurs en montant : le réalisé de toute l'équipe, et l'objectif quand il est saisi. Pour voir l'évolution d'un coup d'œil.</p>
  <?php if ($mx <= 1): ?><div class="empty-state">Pas encore de chiffres sur ces périodes. Saisissez des objectifs ci-dessus pour voir l'évolution.</div><?php else: ?>
  <svg viewBox="0 0 640 210" style="width:100%;height:auto" role="img" aria-label="Objectif et réalisé par période">
    <line x1="30" y1="170" x2="630" y2="170" stroke="#C9D3DD"/>
    <?php foreach ($historique as $i => $h0): $x = 50 + $i * (580 / $nh); $ho = $h0['objectif'] / $mx * 150; $hr = $h0['realise'] / $mx * 150; ?>
      <rect x="<?= round($x, 1) ?>" y="<?= round(170 - $ho, 1) ?>" width="26" height="<?= round($ho, 1) ?>" rx="3" fill="#C9D3DD"/>
      <rect x="<?= round($x + 30, 1) ?>" y="<?= round(170 - $hr, 1) ?>" width="26" height="<?= round($hr, 1) ?>" rx="3" fill="#2F6FED"/>
      <text x="<?= round($x + 28, 1) ?>" y="190" font-size="11" text-anchor="middle" fill="#1B2B3A"><?= View::e($h0['court']) ?></text>
    <?php endforeach; ?>
  </svg>
  <div class="linechart-legend"><span class="leg-item"><span class="dot" style="background:#C9D3DD"></span>Objectif</span><span class="leg-item"><span class="dot" style="background:#2F6FED"></span>Réalisé</span></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top:18px">
  <h2>Fonctions de l'équipe</h2>
  <p class="kpi-note" style="margin:6px 0 12px"><?= Icon::svg('info', 'icon', 12) ?> Donnez à chaque personne la fonction qu'elle a dans votre entreprise (commercial, acheteur, vendeur, chargé de compte…) et choisissez les noms que vous voulez. Chaque fonction propose un objectif type : indicateur, attribution et taux par défaut, repris quand on la choisit pour quelqu'un.</p>
  <?php if (!$fonctionsDispo): ?>
    <div class="empty-state">Ouvrez <code>/migrate_v42.php?token=…</code> une fois pour activer les fonctions.</div>
  <?php else: ?>
  <form method="post" action="/index.php?r=pilotage/fonctions">
    <input type="hidden" name="csrf_token" value="<?= View::e(Auth::csrfToken()) ?>">
    <input type="hidden" name="mois" value="<?= View::e($mois) ?>"><input type="hidden" name="comp" value="<?= View::e($comp) ?>">
    <input type="hidden" name="devise" value="<?= View::e($devise) ?>">
    <div style="overflow-x:auto"><table class="pilotage-table" style="white-space:nowrap">
      <thead><tr><th>Nom de la fonction</th><th>Indicateur par défaut</th><th>Attribution par défaut</th><th class="num">Taux (%)</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($fonctions as $f): $i = (int) $f['id']; ?>
        <tr>
          <td><span class="badge" style="background:<?= View::e(FonctionEquipe::couleur($i)) ?>;color:#fff;margin-right:6px">&nbsp;</span><input type="text" name="f_libelle[<?= $i ?>]" value="<?= View::e($f['libelle']) ?>" maxlength="60" style="width:200px"></td>
          <td><select name="f_indicateur[<?= $i ?>]"><?php foreach (ObjectifCollaborateur::INDICATEURS as $k => $ii): ?><option value="<?= $k ?>" <?= $f['indicateur'] === $k ? 'selected' : '' ?>><?= View::e($ii[0]) ?></option><?php endforeach; ?></select></td>
          <td><select name="f_base[<?= $i ?>]"><?php foreach (ObjectifCollaborateur::BASES as $k => $lb): ?><option value="<?= $k ?>" <?= $f['base'] === $k ? 'selected' : '' ?>><?= View::e($lb) ?></option><?php endforeach; ?></select></td>
          <td class="num"><input type="text" inputmode="decimal" name="f_taux[<?= $i ?>]" value="<?= (float) $f['taux_commission'] > 0 ? (float) $f['taux_commission'] : '' ?>" placeholder="0" style="width:70px;text-align:right"></td>
          <td><button type="submit" name="action" value="supprimer:<?= $i ?>" class="btn btn-sm" title="Supprimer" onclick="return confirm('Supprimer cette fonction ? Les personnes rattachées n\'auront plus de fonction.')">Supprimer</button></td>
        </tr>
      <?php endforeach; ?>
        <tr>
          <td><input type="text" name="n_libelle" placeholder="Nouvelle fonction (ex. Vendeur)" maxlength="60" style="width:230px"></td>
          <td><select name="n_indicateur"><?php foreach (ObjectifCollaborateur::INDICATEURS as $k => $ii): ?><option value="<?= $k ?>"><?= View::e($ii[0]) ?></option><?php endforeach; ?></select></td>
          <td><select name="n_base"><?php foreach (ObjectifCollaborateur::BASES as $k => $lb): ?><option value="<?= $k ?>"><?= View::e($lb) ?></option><?php endforeach; ?></select></td>
          <td class="num"><input type="text" inputmode="decimal" name="n_taux" placeholder="0" style="width:70px;text-align:right"></td>
          <td></td>
        </tr>
      </tbody>
    </table></div>
    <button type="submit" class="btn btn-primary" name="action" value="enregistrer" style="margin-top:12px">Enregistrer les fonctions</button>
  </form>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('.sel-fonction').forEach(function (sel) {
  sel.addEventListener('change', function () {
    var o = sel.options[sel.selectedIndex], u = sel.getAttribute('data-uid');
    if (!o || !o.value) return;
    var set = function (n, v) { var e = document.querySelector('[name="' + n + '[' + u + ']"]'); if (e && v !== null) e.value = v; };
    set('indicateur', o.getAttribute('data-ind'));
    set('base', o.getAttribute('data-base'));
    set('taux', o.getAttribute('data-taux') || '');
  });
});
</script>
<?php endif; ?>
