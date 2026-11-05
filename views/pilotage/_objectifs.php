<?php
use App\Core\Auth;
use App\Core\Icon;
use App\Core\View;
use App\Models\FonctionEquipe;
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

$fonctions = $d['fonctions'] ?? [];
$fonctionsDispo = FonctionEquipe::disponible();
$derniereFct = '__init__';
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
        <option value="XAF" <?= $devise === 'XAF' ? 'selected' : '' ?>>XAF</option>
      </select>
    </div>
  </div>
  <p class="kpi-note" style="margin:8px 0 14px"><?= Icon::svg('info', 'icon', 12) ?> Chaque personne a son indicateur : marge, chiffre d'affaires HT ou nombre de commandes confirmées dans le mois. Commission = taux × réalisé (indicateurs en montant). L'attribution se choisit par personne : responsable du dossier, ou auteur de l'offre retenue.</p>

  <form method="post" action="/index.php?r=pilotage/objectifs">
    <input type="hidden" name="csrf_token" value="<?= View::e(Auth::csrfToken()) ?>">
    <input type="hidden" name="mois" value="<?= View::e($mois) ?>">
    <input type="hidden" name="devise" value="<?= View::e($devise) ?>">
    <div style="overflow-x:auto"><style>.tbl-obj select,.tbl-obj input[type=text]{padding:6px 8px;font-size:13px;height:auto}.tbl-obj td,.tbl-obj th{padding-left:8px;padding-right:8px}.tbl-obj .num{white-space:nowrap}</style>
    <table class="pilotage-table responsive-cards tbl-obj">
      <thead><tr>
        <th>Collaborateur</th><th>Fonction</th><th>Indicateur</th><th>Attribution</th><th class="num">Objectif</th><th class="num">Réalisé</th><th style="min-width:90px">Avancement</th><th class="num">Reste à faire</th><th>Statut</th><th class="num">Taux (%)</th><th class="num">Commission</th>
      </tr></thead>
      <tbody>
      <?php foreach ($d['lignes'] as $l):
        $cleFct = (string) ($l['fonction_id'] ?? 0);
        if ($fonctionsDispo && $cleFct !== $derniereFct): $derniereFct = $cleFct; ?>
        <tr class="groupe-fonction"><td colspan="11" style="background:<?= View::e($l['fonction_couleur']) ?>;color:#fff;font-weight:700;padding:6px 12px"><?= View::e($l['fonction'] ?? 'Sans fonction') ?></td></tr>
      <?php endif;
        $uid = $l['utilisateur_id']; $lib = $l['statut']; $coul = $l['couleur']; $t0 = $l['type']; ?>
        <tr>
          <td data-label="Collaborateur"><span class="avatar-with-name"><?= Icon::avatar($l['nom'], 32) ?><?= View::e($l['nom']) ?></span></td>
          <td data-label="Fonction">
            <?php if ($fonctionsDispo): ?>
            <select name="fonction[<?= $uid ?>]" class="sel-fonction" style="min-width:110px;max-width:130px" data-uid="<?= $uid ?>">
              <option value="">— Aucune —</option>
              <?php foreach ($fonctions as $f): ?>
                <option value="<?= (int) $f['id'] ?>" data-ind="<?= View::e($f['indicateur']) ?>" data-base="<?= View::e($f['base']) ?>" data-taux="<?= (float) $f['taux_commission'] > 0 ? (float) $f['taux_commission'] : '' ?>" <?= (int) ($l['fonction_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['libelle']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php else: ?><span class="cell-na">—</span><?php endif; ?>
          </td>
          <td data-label="Indicateur">
            <select name="indicateur[<?= $uid ?>]" style="min-width:130px">
              <?php foreach (ObjectifCollaborateur::INDICATEURS as $k => $ii): ?>
                <option value="<?= $k ?>" <?= $l['indicateur'] === $k ? 'selected' : '' ?>><?= View::e($ii[0]) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="Attribution">
            <select name="base[<?= $uid ?>]" style="min-width:120px;max-width:135px">
              <?php foreach (ObjectifCollaborateur::BASES as $k => $lb): ?>
                <option value="<?= $k ?>" <?= $l['base'] === $k ? 'selected' : '' ?>><?= View::e($lb) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td data-label="Objectif" class="num"><input type="text" inputmode="decimal" name="objectif[<?= $uid ?>]" value="<?= $l['objectif'] !== null ? View::e((string) (float) $l['objectif']) : '' ?>" placeholder="—" style="width:78px;text-align:right"></td>
          <td data-label="Réalisé" class="num"><strong><?= $fmtVal($l['realise'], $t0) ?></strong><?= Pilotage::noteNonConvertis($l['non_convertis']) ?><br><small><?= $l['dossiers'] ?> dossier(s)</small></td>
          <td data-label="Avancement">
            <?php if ($l['pct'] !== null): ?>
              <div style="background:#E6EEF0;border-radius:99px;height:12px;overflow:hidden"><div style="width:<?= min(100, $l['pct']) ?>%;background:<?= $coul ?>;height:100%"></div></div>
              <small><?= $l['pct'] ?> %</small>
            <?php else: ?><span class="cell-na">—</span><?php endif; ?>
          </td>
          <td data-label="Reste à faire" class="num"><?= $l['reste'] !== null ? $fmtVal($l['reste'], $t0) : '—' ?></td>
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

<div class="card" style="margin-top:18px">
  <h2>Fonctions de l'équipe</h2>
  <p class="kpi-note" style="margin:6px 0 12px"><?= Icon::svg('info', 'icon', 12) ?> Donnez à chaque personne la fonction qu'elle a dans votre entreprise (commercial, acheteur, vendeur, chargé de compte…) et choisissez les noms que vous voulez. Chaque fonction propose un objectif type : indicateur, attribution et taux par défaut, repris quand on la choisit pour quelqu'un.</p>
  <?php if (!$fonctionsDispo): ?>
    <div class="empty-state">Ouvrez <code>/migrate_v42.php?token=…</code> une fois pour activer les fonctions.</div>
  <?php else: ?>
  <form method="post" action="/index.php?r=pilotage/fonctions">
    <input type="hidden" name="csrf_token" value="<?= View::e(Auth::csrfToken()) ?>">
    <input type="hidden" name="mois" value="<?= View::e($mois) ?>">
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
