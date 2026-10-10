<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Dossier;
use App\Models\Utilisateur;

/**
 * Liste des dossiers (refonte 07/10, d'après la maquette « Dossiers ») :
 * 3 indicateurs cliquables, onglets Tous / Mes dossiers / En retard / Clôturés, recherche et filtres,
 * tableau Dossier-Client / Type / Étape / Responsable / Échéance / Statut / Actions, pagination.
 * Variables : $dossiers (page courante), $total, $page, $pages, $parPage, $compteurs, $filters, $utilisateurs, $filialesListe, $filtreClient.
 */
$aujourdhui = date('Y-m-d');
$CLES = ['q' => 'recherche', 'etape' => 'etape', 'responsable_id' => 'responsable_id', 'type_dossier' => 'type_dossier', 'filiale_id' => 'filiale_id', 'date_debut' => 'date_debut', 'date_fin' => 'date_fin'];
$lien = function (array $extra = []) use ($filters, $CLES) {
    $q = ['r' => 'dossiers'];
    foreach ($CLES as $param => $cle) {
        if (!empty($filters[$cle])) { $q[$param] = $filters[$cle]; }
    }
    if (!empty($_GET['client_id'])) { $q['client_id'] = (int) $_GET['client_id']; }
    if (isset($_GET['cp'])) { $q['cp'] = (string) $_GET['cp']; }
    if (!empty($filters['statut'])) { $q['statut'] = $filters['statut']; }
    if (!empty($filters['mes_dossiers'])) { $q['mes'] = 1; }
    foreach ($extra as $k => $v) {
        if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    return '/index.php?' . http_build_query($q);
};
$ongletLien = fn(?string $statut, bool $mes = false) => $lien(['statut' => $statut, 'mes' => $mes ? 1 : null, 'page' => null]);
$ongletActif = !empty($filters['mes_dossiers']) ? 'mes' : ($filters['statut'] ?? 'tous');
if ($ongletActif === null || $ongletActif === '') { $ongletActif = 'tous'; }
$filtresActifs = false;
foreach (['recherche', 'etape', 'responsable_id', 'type_dossier', 'filiale_id', 'date_debut', 'date_fin'] as $k) { if (!empty($filters[$k])) { $filtresActifs = true; } }
$typeIcone = ['achat_sourcing' => 'package', 'transport_logistique' => 'truck', 'prestation_entreprise' => 'briefcase', 'autre' => 'folder'];
$couleursAvatar = ['#2d18fa', '#0f766e', '#b45309', '#9d174d', '#4338ca', '#047857'];
$initiales = function (string $nom): string {
    $mots = preg_split('/\s+/', trim($nom)) ?: [];
    $i = '';
    foreach (array_slice($mots, 0, 2) as $m) { $i .= mb_strtoupper(mb_substr($m, 0, 1)); }
    return $i !== '' ? $i : '?';
};
$debut = $total === 0 ? 0 : ($page - 1) * $parPage + 1;
$fin = min($total, $page * $parPage);
$tuiles = [
    ['cle' => 'actif', 'lib' => 'Dossiers actifs', 'n' => $compteurs['actifs'], 'icone' => 'folder', 'classe' => '', 'aide' => 'en cours, retards compris'],
    ['cle' => 'en_retard', 'lib' => 'En retard', 'n' => $compteurs['en_retard'], 'icone' => 'clock', 'classe' => $compteurs['en_retard'] > 0 ? 'dos-tuile-alerte' : '', 'aide' => 'échéance dépassée'],
    ['cle' => 'a_cloturer', 'lib' => 'À clôturer', 'n' => $compteurs['a_cloturer'], 'icone' => 'file-text', 'classe' => '', 'aide' => 'livrés, clôture à faire'],
];
?>
<style>
  .dos-tuiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 16px 0; }
  .dos-tuile { display: flex; align-items: center; gap: 14px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 16px 18px; text-decoration: none; color: inherit; transition: box-shadow .15s; }
  .dos-tuile:hover { box-shadow: 0 4px 10px rgba(0,0,0,.08); }
  .dos-tuile.actif { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary); }
  .dos-tuile .pastille { width: 42px; height: 42px; border-radius: 10px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .dos-tuile-alerte { background: #fff7ed; border-color: #fed7aa; }
  .dos-tuile-alerte .pastille { background: #ffedd5; color: #c2410c; }
  .dos-tuile .lib { font-size: 13px; color: #555; }
  .dos-tuile .val { font-size: 26px; font-weight: 700; line-height: 1.1; }
  .dos-tuile .aide { font-size: 11px; color: #999; }
  .dos-onglets { display: flex; gap: 22px; border-bottom: 1px solid var(--border); padding: 0 6px; overflow-x: auto; white-space: nowrap; }
  .dos-onglets a { padding: 12px 2px; font-size: 14px; color: #555; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -1px; }
  .dos-onglets a.actif { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
  .dos-filtres { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; padding: 14px 6px; }
  .dos-filtres .champ { display: flex; flex-direction: column; gap: 3px; }
  .dos-filtres label { font-size: 11px; color: #777; }
  .dos-recherche { flex: 1 1 260px; position: relative; }
  .dos-recherche input { width: 100%; padding-left: 34px; }
  .dos-recherche .loupe { position: absolute; left: 10px; bottom: 9px; color: #999; pointer-events: none; }
  .dos-titre { display: flex; align-items: center; gap: 10px; }
  .dos-titre .puce { width: 34px; height: 34px; border-radius: 8px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .dos-actions { display: flex; gap: 6px; }
  .dos-actions a { width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #444; background: #fff; }
  .dos-actions a:hover { background: var(--primary-light); color: var(--primary); }
  .responsive-cards .badge { white-space: nowrap; }
  .dos-retard { color: #c2410c; font-size: 12px; font-weight: 600; }
  .dos-avatar { width: 28px; height: 28px; border-radius: 999px; color: #fff; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
  @media (max-width: 768px) { .dos-tuiles { grid-template-columns: 1fr; gap: 10px; } .dos-onglets { gap: 16px; } }
</style>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
  <div>
    <h1 style="margin-bottom:2px">Dossiers</h1>
    <div class="subtitle">Suivez vos opérations, de la qualification à la livraison.</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/index.php?r=dossiers/export.csv&<?= http_build_query(array_filter($filters, fn($v) => $v !== null && $v !== '')) ?>" class="btn btn-secondary"><?= Icon::svg('download', 'icon', 15) ?> Exporter (CSV)</a>
    <a href="/index.php?r=dossiers/importer" class="btn btn-secondary"><?= Icon::svg('upload', 'icon', 15) ?> Importer</a>
  </div>
</div>

<?php if (!empty($filtreClient)): ?>
  <div class="alert" style="background:#eef0ff;color:#2d18fa;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px">
    <span>Dossiers de : <strong><?= View::e($filtreClient) ?></strong></span>
    <a href="/index.php?r=dossiers">Voir tous les dossiers</a>
  </div>
<?php endif; ?>

<div class="dos-tuiles">
  <?php foreach ($tuiles as $t): ?>
    <a class="dos-tuile <?= $t['classe'] ?> <?= ($filters['statut'] ?? '') === $t['cle'] && empty($filters['mes_dossiers']) ? 'actif' : '' ?>" href="<?= View::e($ongletLien($t['cle'])) ?>">
      <span class="pastille"><?= Icon::svg($t['icone'], 'icon', 20) ?></span>
      <span><span class="lib"><?= View::e($t['lib']) ?></span><br><span class="val"><?= (int) $t['n'] ?></span><br><span class="aide"><?= View::e($t['aide']) ?></span></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="card" style="padding:0">
  <nav class="dos-onglets" aria-label="Filtrer par statut">
    <a href="<?= View::e($ongletLien(null)) ?>" class="<?= $ongletActif === 'tous' ? 'actif' : '' ?>">Tous (<?= (int) $compteurs['tous'] ?>)</a>
    <a href="<?= View::e($ongletLien(null, true)) ?>" class="<?= $ongletActif === 'mes' ? 'actif' : '' ?>">Mes dossiers (<?= (int) $compteurs['mes'] ?>)</a>
    <a href="<?= View::e($ongletLien('en_retard')) ?>" class="<?= $ongletActif === 'en_retard' ? 'actif' : '' ?>">En retard (<?= (int) $compteurs['en_retard'] ?>)</a>
    <a href="<?= View::e($ongletLien('cloture')) ?>" class="<?= $ongletActif === 'cloture' ? 'actif' : '' ?>">Clôturés (<?= (int) $compteurs['clotures'] ?>)</a>
    <?php if (!empty($compteurs['annules']) || $ongletActif === 'annule'): ?>
    <a href="<?= View::e($ongletLien('annule')) ?>" class="<?= $ongletActif === 'annule' ? 'actif' : '' ?>">Annulés (<?= (int) $compteurs['annules'] ?>)</a>
    <?php endif; ?>
  </nav>

  <form method="get" action="/index.php" class="dos-filtres">
    <input type="hidden" name="r" value="dossiers">
    <?php if (!empty($_GET['client_id'])): ?><input type="hidden" name="client_id" value="<?= (int) $_GET['client_id'] ?>"><?php endif; ?>
    <?php if (isset($_GET['cp'])): ?><input type="hidden" name="cp" value="<?= View::e((string) $_GET['cp']) ?>"><?php endif; ?>
    <?php if (!empty($filters['statut'])): ?><input type="hidden" name="statut" value="<?= View::e($filters['statut']) ?>"><?php endif; ?>
    <?php if (!empty($filters['mes_dossiers'])): ?><input type="hidden" name="mes" value="1"><?php endif; ?>
    <div class="champ dos-recherche">
      <label for="dq">Recherche</label>
      <span class="loupe"><?= Icon::svg('search', 'icon', 16) ?></span>
      <input type="text" id="dq" name="q" placeholder="Rechercher un dossier, un client ou une référence…" value="<?= View::e($filters['recherche'] ?? '') ?>">
    </div>
    <div class="champ"><label for="dtype">Type de dossier</label>
      <select id="dtype" name="type_dossier"><option value="">Tous</option>
        <?php foreach (Dossier::TYPES_LABELS as $code => $lib): ?><option value="<?= $code ?>" <?= ($filters['type_dossier'] ?? '') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
    <div class="champ"><label for="detape">Étape</label>
      <select id="detape" name="etape"><option value="">Toutes</option>
        <?php foreach (Dossier::ETAPES as $etape): ?><option value="<?= $etape ?>" <?= ($filters['etape'] ?? '') === $etape ? 'selected' : '' ?>><?= View::e(Dossier::ETAPES_LABELS[$etape]) ?></option><?php endforeach; ?></select></div>
    <div class="champ"><label for="dresp">Responsable</label>
      <select id="dresp" name="responsable_id"><option value="">Tous</option>
        <?php foreach ($utilisateurs as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (string) ($filters['responsable_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option><?php endforeach; ?></select></div>
    <?php if (count($filialesListe) > 1): ?>
    <div class="champ"><label for="dfil">Filiale</label>
      <select id="dfil" name="filiale_id"><option value="">Toutes</option>
        <?php foreach ($filialesListe as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (int) ($filters['filiale_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="champ"><label for="dd1">Créé du</label><input type="date" id="dd1" name="date_debut" value="<?= View::e($filters['date_debut'] ?? '') ?>"></div>
    <div class="champ"><label for="dd2">au</label><input type="date" id="dd2" name="date_fin" value="<?= View::e($filters['date_fin'] ?? '') ?>"></div>
    <div class="champ" style="flex-direction:row;gap:8px;align-items:center">
      <button type="submit" class="btn"><?= Icon::svg('filter', 'icon', 15) ?> Filtrer</button>
      <?php if ($filtresActifs || !empty($filters['statut']) || !empty($filters['mes_dossiers'])): ?><a href="/index.php?r=dossiers" style="font-size:13px">Réinitialiser</a><?php endif; ?>
    </div>
  </form>

  <?php if (empty($dossiers)): ?>
    <div class="empty-state" style="padding:30px"><?= $filtresActifs || !empty($filters['statut']) || !empty($filters['mes_dossiers']) ? 'Aucun dossier ne correspond à ces critères.' : 'Aucun dossier pour le moment. Un dossier se crée en qualifiant une demande.' ?></div>
  <?php else: ?>
  <table class="responsive-cards liste-cartes">
    <thead>
      <tr><th>Dossier / Client</th><th>Type</th><th>Étape</th><th>Responsable</th><th>Échéance</th><th>Statut</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($dossiers as $d):
      $enRetard = $d['statut'] === 'actif' && $d['echeance'] && $d['echeance'] < $aujourdhui;
      $aCloturer = $d['statut'] === 'actif' && $d['etape'] === 'livraison';
      $resp = !empty($d['responsable_id']) ? Utilisateur::nameOf((int) $d['responsable_id']) : '';
      $prog = Dossier::progression($d['etape']);
    ?>
      <tr>
        <td data-label="Dossier / Client">
          <div class="dos-titre">
            <span class="puce"><?= Icon::svg('folder', 'icon', 16) ?></span>
            <span><a href="/index.php?r=dossiers/<?= (int) $d['id'] ?>" style="font-weight:600;color:inherit"><?= View::e($d['objet']) ?></a><br>
              <span style="font-size:12px;color:#888"><?= View::e($d['reference']) ?><?= !empty($d['client_nom']) ? ' · ' . View::e($d['client_nom']) : '' ?><?= count($filialesListe) > 1 ? ' · ' . View::e($d['filiale_nom']) : '' ?></span></span>
          </div>
        </td>
        <td data-label="Type"><span style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap"><?= Icon::svg($typeIcone[$d['type_dossier'] ?? 'autre'] ?? 'folder', 'icon', 15) ?> <?= View::e(Dossier::TYPES_LABELS[$d['type_dossier'] ?? 'autre'] ?? '—') ?></span></td>
        <td data-label="Étape" style="min-width:120px">
          <span class="badge badge-blue"><?= View::e(Dossier::ETAPES_LABELS[$d['etape']] ?? $d['etape']) ?></span>
          <div style="background:#f1f2f5;border-radius:6px;height:4px;overflow:hidden;margin-top:6px;max-width:110px" title="Avancement <?= $prog ?> %"><div style="background:<?= $prog === 100 ? '#16a34a' : '#4f46e5' ?>;height:100%;width:<?= $prog ?>%"></div></div>
        </td>
        <td data-label="Responsable">
          <?php if ($resp !== '' && $resp !== '—'): ?>
            <span style="display:inline-flex;align-items:center;gap:8px"><span class="dos-avatar" style="background:<?= $couleursAvatar[(int) $d['responsable_id'] % count($couleursAvatar)] ?>"><?= View::e($initiales($resp)) ?></span> <?= View::e(explode(' ', $resp)[0]) ?></span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td data-label="Échéance" style="white-space:nowrap">
          <?= $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '—' ?>
          <?php if ($enRetard): ?><br><span class="dos-retard"><?= Icon::svg('clock', 'icon', 12) ?> En retard</span><?php endif; ?>
        </td>
        <td data-label="Statut">
          <?php if ($d['statut'] === 'annule'): ?><span class="badge badge-red">Annulé</span>
          <?php elseif ($d['statut'] === 'cloture'): ?><span class="badge badge-green">Clôturé</span>
          <?php elseif ($enRetard): ?><span class="badge badge-orange">En retard</span>
          <?php elseif ($aCloturer): ?><span class="badge badge-yellow">À clôturer</span>
          <?php else: ?><span class="badge badge-blue">En cours</span><?php endif; ?>
        </td>
        <td data-label="Actions"><span class="dos-actions"><a href="/index.php?r=dossiers/<?= (int) $d['id'] ?>" title="Ouvrir le dossier" aria-label="Ouvrir le dossier <?= View::e($d['reference']) ?>"><?= Icon::svg('eye', 'icon', 16) ?></a></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:12px 16px;border-top:1px solid var(--border);font-size:13px;color:#666">
    <span><?= $debut ?>–<?= $fin ?> sur <?= (int) $total ?> dossier<?= $total > 1 ? 's' : '' ?></span>
    <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pagination" style="display:flex;gap:6px;align-items:center">
      <?php if ($page > 1): ?><a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['page' => $page - 1])) ?>">Précédent</a><?php endif; ?>
      <span>Page <?= $page ?> / <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['page' => $page + 1])) ?>">Suivant</a><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
