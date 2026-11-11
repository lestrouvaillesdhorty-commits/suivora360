<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Cotation;
use App\Models\Pilotage;
use App\Models\Utilisateur;

$d = $pertesData;
$dev = $filters['devise'];
$palette = ['prix' => '#E5484D', 'delai' => '#F57C2B', 'concurrent' => '#7C5CE0', 'qualite' => '#0B9AAE', 'besoin' => '#2F6FED', 'abandon' => '#8A97A0', 'sans_reponse' => '#FFC83D', 'autre' => '#1FA463', '' => '#C9D3D8'];
$libelle = fn($k, $f = null) => \App\Models\MotifPerte::libelle($k, $f);
$couleurs = ['#2F6FED', '#0B7C8A', '#E36A4C', '#7C5CE0', '#1FA463', '#F57C2B'];
$couleur = fn($k) => $palette[$k] ?? ($k === '' ? '#C9D3D8' : $couleurs[(int) substr((string) $k, 1) % count($couleurs)]);
$tp = $d['type_perte'] ?? '';
$qsT = fn($v) => '/index.php?' . http_build_query(array_merge($_GET, ['r' => 'pilotage', 'onglet' => 'pertes', 'type_perte' => $v]));
$retourP = $_SERVER['REQUEST_URI'] ?? '/index.php?r=pilotage';
$nbDossiers = count($d['dossiers_sans_commande'] ?? []);
$motifsParFiliale = fn($fid) => \App\Models\MotifPerte::actifs((int) $fid);
$maxNb = $d['par_motif'] ? max(array_column($d['par_motif'], 'nb')) : 1;
?>
<div class="kpi-row" style="margin-top:16px">
  <div class="kpi-card" style="border-top:4px solid #E5484D"><div class="kpi-body"><div class="kpi-label">Devis perdus</div><div class="kpi-value"><?= (int) $d['nb'] ?></div></div></div>
  <div class="kpi-card" style="border-top:4px solid #F57C2B"><div class="kpi-body"><div class="kpi-label">Valeur perdue</div><div class="kpi-value"><?= Pilotage::fmt($d['montant'], $dev) ?></div><?= Pilotage::avertissementNonConvertis($d['non_convertis']) ?></div></div>
  <div class="kpi-card" style="border-top:4px solid #7C5CE0"><div class="kpi-body"><div class="kpi-label">Taux de perte <span class="info-tip" data-tip="Devis refusés / (devis acceptés + refusés) sur la période.">i</span></div><div class="kpi-value"><?= Pilotage::fmtPct($d['taux_perte']) ?></div><div class="kpi-sub"><?= (int) $d['acceptes'] ?> accepté(s)</div></div></div>
  <div class="kpi-card" style="border-top:4px solid #FFC83D"><div class="kpi-body"><div class="kpi-label">Motif à renseigner</div><div class="kpi-value"><?= (int) $d['sans_motif'] ?></div></div></div>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0">
  <a class="btn btn-sm <?= $tp === '' ? '' : 'btn-secondary' ?>" href="<?= View::e($qsT('')) ?>">Tout</a>
  <a class="btn btn-sm <?= $tp === 'devis' ? '' : 'btn-secondary' ?>" href="<?= View::e($qsT('devis')) ?>">Devis refusés</a>
  <a class="btn btn-sm <?= $tp === 'dossiers' ? '' : 'btn-secondary' ?>" href="<?= View::e($qsT('dossiers')) ?>">Dossiers clôturés sans devis</a>
</div>
<?php if (!Cotation::colonnesPerte()): ?>
  <div class="card"><div class="empty-state">Ouvrez <code>/migrate_v45.php?token=…</code> une fois pour activer les motifs de perte.</div></div>
<?php elseif ($d['nb'] === 0 && $nbDossiers === 0): ?>
  <div class="card"><div class="empty-state">Aucune perte sur cette période.</div></div>
<?php else: ?>
<div class="card">
  <h2>Pourquoi on perd</h2>
  <div class="hbar-list">
    <?php foreach ($d['par_motif'] as $k => $v): $c = $couleur($k); ?>
    <div class="hbar-item">
      <div class="hbar-label"><span><?= View::e($libelle($k)) ?></span></div>
      <div class="hbar-row">
        <div class="hbar-track"><div class="hbar-fill" style="width:<?= round($v['nb'] / $maxNb * 100) ?>%;background:<?= $c ?>"></div></div>
        <div class="hbar-value"><?= (int) $v['nb'] ?> · <?= Pilotage::fmt($v['montant'], $dev) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($tp !== 'dossiers'): ?>
<div class="card">
  <h2>Devis perdus</h2>
  <div style="margin-bottom:10px"><a class="btn btn-pilotage" href="<?= View::e('/index.php?r=pilotage/export.csv&onglet=pertes' . ($qs !== '' ? '&' . $qs : '')) ?>"><?= Icon::svg('download', 'icon', 15) ?> Exporter vers Excel (CSV)</a></div>
  <div class="table-scroll"><table class="pilotage-table responsive-cards">
    <thead><tr><th>Devis</th><th>Client</th><th>Responsable</th><th>Perdu le</th><th class="num">Montant</th><th>Motif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['lignes'] as $l): $k = $l['motif_perte'] ?: ''; ?>
      <tr>
        <td data-label="Devis"><?= View::e($l['reference']) ?><br><small><?= View::e($l['dossier_reference']) ?></small></td>
        <td data-label="Client"><?= View::e($l['client_nom']) ?></td>
        <td data-label="Responsable"><?= $l['responsable_id'] ? View::e(Utilisateur::nameOf((int) $l['responsable_id'])) : '<span class="cell-na">Non assigné</span>' ?></td>
        <td data-label="Perdu le"><?= View::e(date('d/m/Y', strtotime((string) $l['perdu_le']))) ?></td>
        <td data-label="Montant" class="num"><?= number_format((float) $l['montant_total'], 2, ',', ' ') ?> <?= View::e($l['devise']) ?></td>
        <td data-label="Motif"><span class="badge" style="background:<?= $couleur($k) ?>;color:<?= in_array($k, ['sans_reponse', ''], true) ? '#1B2B3A' : '#fff' ?>"><?= View::e($libelle($k, (int) $l['filiale_id'])) ?></span><?php if (!empty($l['motif_perte_detail'])): ?><br><small><?= View::e($l['motif_perte_detail']) ?></small><?php endif; ?></td>
        <td><a class="btn btn-sm" style="color:#fff;white-space:nowrap" href="/index.php?r=cotations/<?= (int) $l['id'] ?>"><?= $k === '' ? 'Renseigner le motif' : 'Ouvrir' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php if ($tp !== 'devis' && $nbDossiers > 0): ?>
<div class="card">
  <h2>Dossiers clôturés sans commande</h2>
  <div class="hint" style="margin-bottom:8px">Dossiers sans devis refusé : leur motif est celui choisi à la clôture. Un dossier n'apparaît qu'une fois.</div>
  <div class="table-scroll"><table class="pilotage-table responsive-cards">
    <thead><tr><th>Dossier</th><th>Client</th><th>Responsable</th><th>Clôturé le</th><th>Motif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($d['dossiers_sans_commande'] as $dc): $k = $dc['motif_cloture'] ?: ''; ?>
      <tr>
        <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $dc['id'] ?>"><?= View::e($dc['reference']) ?></a></td>
        <td data-label="Client"><?= View::e($dc['client_nom']) ?></td>
        <td data-label="Responsable"><?= $dc['responsable_id'] ? View::e(Utilisateur::nameOf((int) $dc['responsable_id'])) : '<span class="cell-na">Non assigné</span>' ?></td>
        <td data-label="Clôturé le"><?= View::e(date('d/m/Y', strtotime((string) $dc['cloture_le']))) ?></td>
        <td data-label="Motif"><span class="badge" style="background:<?= $couleur($k) ?>;color:<?= in_array($k, ['sans_reponse', ''], true) ? '#1B2B3A' : '#fff' ?>"><?= View::e($libelle($k, (int) $dc['filiale_id'])) ?></span><?php if (!empty($dc['motif_cloture_detail'])): ?><br><small><?= View::e($dc['motif_cloture_detail']) ?></small><?php endif; ?></td>
        <td>
          <?php if ($k === ''): ?>
          <form method="post" action="/index.php?r=dossiers/<?= (int) $dc['id'] ?>/motif-cloture" style="display:flex;gap:6px;margin:0;flex-wrap:wrap">
            <input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="retour" value="<?= View::e($retourP) ?>">
            <select name="motif_cloture" required aria-label="Motif">
              <option value="">— Motif —</option>
              <?php foreach ($motifsParFiliale($dc['filiale_id']) as $mk => $ml): ?><option value="<?= View::e($mk) ?>"><?= View::e($ml) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm" type="submit" style="color:#fff">Renseigner</button>
          </form>
          <?php else: ?><a class="btn btn-sm" style="color:#fff" href="/index.php?r=dossiers/<?= (int) $dc['id'] ?>">Ouvrir</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php endif; ?>
