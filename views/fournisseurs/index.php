<?php
use App\Core\Auth;
use App\Core\View;
use App\Models\Fournisseur;

/**
 * Liste des fournisseurs (refonte 07/10) : 4 indicateurs cliquables avec leur périmètre,
 * onglets Tous/Actifs/Inactifs, recherche, filtres, tri, pagination, export.
 * Variables : $liste, $filtres, $indicateurs, $filiales, $utilisateurs, $paysListe, $specialitesListe, $peutExporter, $schemaPret.
 */
$CLES = ['q', 'pays', 'specialite', 'type', 'qualification', 'alerte'];
$lien = function (array $extra = []) use ($filtres, $CLES) {
    $q = ['r' => 'fournisseurs'];
    foreach ($CLES as $k) {
        if ($filtres[$k] !== '') { $q[$k] = $filtres[$k]; }
    }
    foreach (['filiale_id', 'responsable_id'] as $k) {
        if (!empty($filtres[$k])) { $q[$k] = $filtres[$k]; }
    }
    if ($filtres['onglet'] !== 'tous') { $q['onglet'] = $filtres['onglet']; }
    if ($filtres['tri'] !== 'nom') { $q['tri'] = $filtres['tri']; }
    if ($filtres['dir'] !== 'asc') { $q['dir'] = $filtres['dir']; }
    foreach ($extra as $k => $v) {
        if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    return '/index.php?' . http_build_query($q);
};
$ongletLien = fn(string $o) => $lien(['onglet' => $o === 'tous' ? null : $o, 'page' => null]);
$filtresActifs = false;
foreach (['q', 'pays', 'specialite', 'type', 'qualification'] as $k) { if ($filtres[$k] !== '') { $filtresActifs = true; } }
if (!empty($filtres['filiale_id']) || !empty($filtres['responsable_id'])) { $filtresActifs = true; }
$afficherFiliale = count($filiales) > 1 && empty($filtres['filiale_id']);
$nbInactifs = $indicateurs['total'] - $indicateurs['actifs'];
$perimetreTxt = $filtresActifs ? 'selon vos filtres' : 'tous vos fournisseurs';
$lienExport = '/index.php?' . http_build_query(array_filter(array_merge(
    ['r' => 'fournisseurs/export.csv'],
    array_intersect_key($filtres, array_flip($CLES)),
    ['filiale_id' => $filtres['filiale_id'] ?: '', 'responsable_id' => $filtres['responsable_id'] ?: '', 'onglet' => $filtres['onglet'] === 'tous' ? '' : $filtres['onglet']]
), fn($v) => $v !== '' && $v !== null));
// En-tête triable
$entete = function (string $cle, string $libelle) use ($filtres, $lien) {
    $actif = $filtres['tri'] === $cle;
    $dir = ($actif && $filtres['dir'] === 'asc') ? 'desc' : 'asc';
    $fleche = $actif ? ($filtres['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . View::e($lien(['tri' => $cle === 'nom' ? null : $cle, 'dir' => $dir === 'asc' ? null : 'desc', 'page' => null])) . '" style="color:inherit;text-decoration:none" title="Trier">' . View::e($libelle) . $fleche . '</a>';
};
?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
  <div>
    <h1>Fournisseurs</h1>
    <div class="subtitle"><?= (int) $liste['total'] ?> résultat<?= $liste['total'] > 1 ? 's' : '' ?><?= $filtresActifs || $filtres['onglet'] !== 'tous' || $filtres['alerte'] !== '' ? ' (filtre appliqué)' : '' ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (Auth::canVoirFinancesFournisseur()): ?><a href="/index.php?r=bons-commande" class="btn btn-secondary">Bons de commande</a><?php endif; ?>
    <?php if ($peutExporter): ?>
      <a href="<?= View::e($lienExport) ?>" class="btn btn-secondary" title="Exporte la liste selon les filtres appliqués (sans notes internes)">Exporter (CSV)</a>
    <?php endif; ?>
    <?php if (Auth::canWrite()): ?>
      <a href="/index.php?r=fournisseurs/nouveau" class="btn">Nouveau fournisseur</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$schemaPret): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e">Mise à jour de la base à finaliser (migration V23) : types de partenaire, qualification, contacts multiples, évaluations et échéances de documents seront disponibles ensuite.</div>
<?php endif; ?>

<div class="grid-3" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
  <a class="stat-tile stat-tile-link" href="<?= View::e($lien(['onglet' => 'actifs', 'alerte' => null, 'qualification' => null, 'page' => null])) ?>">
    <div class="value"><?= (int) $indicateurs['actifs'] ?></div>
    <div class="label">Fournisseurs actifs<br><span style="font-size:11px;color:#999"><?= View::e($perimetreTxt) ?></span></div>
  </a>
  <a class="stat-tile stat-tile-link" href="<?= View::e($lien(['onglet' => 'actifs', 'qualification' => 'a_qualifier', 'alerte' => null, 'page' => null])) ?>">
    <div class="value"><?= (int) $indicateurs['a_qualifier'] ?></div>
    <div class="label">À qualifier<br><span style="font-size:11px;color:#999">actifs au niveau « à qualifier »</span></div>
  </a>
  <a class="stat-tile stat-tile-link" href="<?= View::e($lien(['alerte' => 'consultations', 'onglet' => null, 'page' => null])) ?>">
    <div class="value"><?= (int) $indicateurs['consultations'] ?></div>
    <div class="label">Consultations en cours<br><span style="font-size:11px;color:#999">envoyées ou relancées, sans réponse</span></div>
  </a>
  <?php if ($indicateurs['documents'] !== null): ?>
  <a class="stat-tile stat-tile-link" href="<?= View::e($lien(['alerte' => 'documents', 'onglet' => null, 'page' => null])) ?>">
    <div class="value"><?= (int) $indicateurs['documents'] ?></div>
    <div class="label">Documents à renouveler<br><span style="font-size:11px;color:#999">échéance dépassée ou sous <?= Fournisseur::JOURS_ALERTE_DOCUMENT ?> jours</span></div>
  </a>
  <?php else: ?>
  <div class="stat-tile">
    <div class="value" style="font-size:18px;color:#999">Non disponible</div>
    <div class="label">Documents à renouveler<br><span style="font-size:11px;color:#999">après la migration V23</span></div>
  </div>
  <?php endif; ?>
</div>

<?php if ($filtres['alerte'] !== ''): ?>
  <div class="alert" style="background:#eef2ff;color:#1e3a8a;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <span>Liste limitée aux fournisseurs avec <?= $filtres['alerte'] === 'consultations' ? 'une consultation en cours' : 'un document à renouveler' ?>.</span>
    <a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['alerte' => null, 'page' => null])) ?>">Retirer ce filtre</a>
  </div>
<?php endif; ?>

<div class="tabs">
  <a href="<?= View::e($ongletLien('tous')) ?>" class="<?= $filtres['onglet'] === 'tous' ? 'active' : '' ?>">Tous (<?= (int) $indicateurs['total'] ?>)</a>
  <a href="<?= View::e($ongletLien('actifs')) ?>" class="<?= $filtres['onglet'] === 'actifs' ? 'active' : '' ?>">Actifs (<?= (int) $indicateurs['actifs'] ?>)</a>
  <a href="<?= View::e($ongletLien('inactifs')) ?>" class="<?= $filtres['onglet'] === 'inactifs' ? 'active' : '' ?>">Inactifs (<?= (int) $nbInactifs ?>)</a>
</div>

<div class="filter-bar">
  <form method="get" action="/index.php">
    <input type="hidden" name="r" value="fournisseurs">
    <?php if ($filtres['onglet'] !== 'tous'): ?><input type="hidden" name="onglet" value="<?= View::e($filtres['onglet']) ?>"><?php endif; ?>
    <?php if ($filtres['alerte'] !== ''): ?><input type="hidden" name="alerte" value="<?= View::e($filtres['alerte']) ?>"><?php endif; ?>
    <div class="f-group" style="min-width:220px;flex:2">
      <label for="fq">Recherche</label>
      <input type="text" id="fq" name="q" value="<?= View::e($filtres['q']) ?>" placeholder="Nom, référence, spécialité, contact, e-mail, téléphone">
    </div>
    <?php if ($schemaPret): ?>
    <div class="f-group">
      <label for="ftype">Type</label>
      <select id="ftype" name="type">
        <option value="">Tous</option>
        <?php foreach (Fournisseur::TYPES as $code => $lib): ?><option value="<?= $code ?>" <?= $filtres['type'] === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="f-group">
      <label for="fpays">Pays</label>
      <select id="fpays" name="pays">
        <option value="">Tous</option>
        <?php foreach ($paysListe as $p): ?><option <?= $filtres['pays'] === $p ? 'selected' : '' ?>><?= View::e($p) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="f-group">
      <label for="fspec">Spécialité</label>
      <select id="fspec" name="specialite">
        <option value="">Toutes</option>
        <?php foreach ($specialitesListe as $s): ?><option <?= mb_strtolower($filtres['specialite']) === mb_strtolower($s) ? 'selected' : '' ?>><?= View::e($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($schemaPret): ?>
    <div class="f-group">
      <label for="fresp">Responsable</label>
      <select id="fresp" name="responsable_id">
        <option value="">Tous</option>
        <?php foreach ($utilisateurs as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $filtres['responsable_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="f-group">
      <label for="fqual">Qualification</label>
      <select id="fqual" name="qualification">
        <option value="">Toutes</option>
        <?php foreach (Fournisseur::QUALIFICATIONS as $code => $lib): ?><option value="<?= $code ?>" <?= $filtres['qualification'] === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if (count($filiales) > 1): ?>
    <div class="f-group">
      <label for="ffil">Filiale</label>
      <select id="ffil" name="filiale_id">
        <option value="">Toutes</option>
        <?php foreach ($filiales as $fi): ?><option value="<?= (int) $fi['id'] ?>" <?= (int) $filtres['filiale_id'] === (int) $fi['id'] ? 'selected' : '' ?>><?= View::e($fi['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn">Filtrer</button>
      <?php if ($filtresActifs): ?><a class="btn btn-secondary" href="<?= View::e('/index.php?r=fournisseurs' . ($filtres['onglet'] !== 'tous' ? '&onglet=' . $filtres['onglet'] : '') . ($filtres['alerte'] !== '' ? '&alerte=' . $filtres['alerte'] : '')) ?>">Réinitialiser</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if (empty($liste['lignes'])): ?>
  <div class="card"><div class="empty-state">
    <?php if ($filtresActifs || $filtres['onglet'] !== 'tous' || $filtres['alerte'] !== ''): ?>
      Aucun fournisseur ne correspond à ces critères.
    <?php else: ?>
      Aucun fournisseur pour le moment.<?php if (Auth::canWrite()): ?> <a href="/index.php?r=fournisseurs/nouveau">Ajouter le premier fournisseur</a>.<?php endif; ?>
    <?php endif; ?>
  </div></div>
<?php else: ?>
<table class="responsive-cards liste-cartes">
  <thead>
    <tr>
      <th><?= $entete('nom', 'Fournisseur') ?></th>
      <th><?= $entete('type', 'Type') ?></th>
      <th>Spécialités</th>
      <th><?= $entete('pays', 'Pays / ville') ?></th>
      <?php if ($afficherFiliale): ?><th>Filiale</th><?php endif; ?>
      <th>Contact principal</th>
      <th><?= $entete('qualification', 'Qualification') ?></th>
      <th><?= $entete('statut', 'Statut') ?></th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($liste['lignes'] as $f):
      $actif = (int) $f['is_active'] === 1;
      $qual = Fournisseur::qualificationDe($f);
      $types = Fournisseur::typesDe($f);
      $specs = Fournisseur::specialitesDe($f);
      $contact = trim(($f['contact_prenom'] ?? '') . ' ' . ($f['contact_nom'] ?? ''));
  ?>
    <tr onclick="window.location='/index.php?r=fournisseurs/<?= (int) $f['id'] ?>'" style="cursor:pointer">
      <td data-label="Fournisseur"><strong><?= View::e($f['nom']) ?></strong><br><span style="font-size:12px;color:#888"><?= View::e($f['code'] ?? '') ?></span></td>
      <td data-label="Type">
        <?php if ($types): foreach ($types as $t): ?><span class="badge <?= Fournisseur::TYPES_BADGES[$t] ?>"><?= View::e(Fournisseur::TYPES[$t]) ?></span> <?php endforeach; else: ?><span style="color:#999">—</span><?php endif; ?>
      </td>
      <td data-label="Spécialités"><?= $specs ? View::e(implode(', ', array_slice($specs, 0, 3))) . (count($specs) > 3 ? ' …' : '') : '<span style="color:#999">—</span>' ?></td>
      <td data-label="Pays / ville"><?= View::e(trim(($f['pays'] ?? '') . ((!empty($f['pays']) && !empty($f['ville'])) ? ' · ' : '') . ($f['ville'] ?? ''))) ?: '—' ?></td>
      <?php if ($afficherFiliale): ?><td data-label="Filiale"><?= View::e($f['filiale_nom']) ?></td><?php endif; ?>
      <td data-label="Contact principal">
        <?php if ($contact !== '' || !empty($f['fonction_contact'])): ?>
          <?= View::e($contact) ?><?= !empty($f['fonction_contact']) ? '<br><span style="font-size:12px;color:#888">' . View::e($f['fonction_contact']) . '</span>' : '' ?>
        <?php endif; ?>
        <?php if (!empty($f['email'])): ?><br><span style="font-size:12px"><?= View::e($f['email']) ?></span><?php endif; ?>
        <?php if (!empty($f['telephone'])): ?><br><span style="font-size:12px"><?= View::e($f['telephone']) ?></span><?php endif; ?>
        <?php if ($contact === '' && empty($f['fonction_contact']) && empty($f['email']) && empty($f['telephone'])): ?><span style="color:#999">—</span><?php endif; ?>
      </td>
      <td data-label="Qualification"><span class="badge <?= Fournisseur::QUALIFICATION_BADGES[$qual] ?>"><?= View::e(Fournisseur::QUALIFICATIONS[$qual]) ?></span></td>
      <td data-label="Statut"><span class="badge <?= $actif ? 'badge-green' : 'badge-gray' ?>"><?= $actif ? 'Actif' : 'Inactif' ?></span></td>
      <td data-label="Actions" onclick="event.stopPropagation()" style="white-space:nowrap">
        <a href="/index.php?r=fournisseurs/<?= (int) $f['id'] ?>" class="btn btn-sm btn-secondary">Voir</a>
        <?php if (Auth::canWrite()): ?><a href="/index.php?r=fournisseurs/<?= (int) $f['id'] ?>/modifier" class="btn btn-sm btn-secondary">Modifier</a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($liste['pages'] > 1): ?>
<nav class="pagination" aria-label="Pagination" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:16px 0">
  <?php if ($liste['page'] > 1): ?><a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['page' => $liste['page'] - 1 > 1 ? $liste['page'] - 1 : null])) ?>">Précédent</a><?php endif; ?>
  <?php for ($i = 1; $i <= $liste['pages']; $i++): ?>
    <?php if ($i === 1 || $i === $liste['pages'] || abs($i - $liste['page']) <= 2): ?>
      <a class="btn btn-sm <?= $i === $liste['page'] ? '' : 'btn-secondary' ?>" href="<?= View::e($lien(['page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a>
    <?php elseif (abs($i - $liste['page']) === 3): ?><span>…</span><?php endif; ?>
  <?php endfor; ?>
  <?php if ($liste['page'] < $liste['pages']): ?><a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['page' => $liste['page'] + 1])) ?>">Suivant</a><?php endif; ?>
  <span style="font-size:12px;color:#888;margin-left:8px"><?= (int) $liste['total'] ?> fournisseur<?= $liste['total'] > 1 ? 's' : '' ?></span>
</nav>
<?php endif; ?>
<?php endif; ?>
