<?php
use App\Core\Auth;
use App\Core\Telephone;
use App\Core\View;
use App\Models\Client;

/**
 * Liste des clients (refonte 07/10) : indicateurs cliquables, onglets
 * Tous/Actifs/Inactifs, recherche, filtres, pagination, export.
 * Variables : $liste, $filtres, $indicateurs, $filiales, $utilisateurs, $paysListe, $peutExporter, $schemaPret.
 */
$lien = function (array $extra = []) use ($filtres) {
    $q = ['r' => 'clients'];
    foreach (['q', 'pays', 'type', 'relation'] as $k) {
        if ($filtres[$k] !== '') { $q[$k] = $filtres[$k]; }
    }
    foreach (['filiale_id', 'responsable_id'] as $k) {
        if (!empty($filtres[$k])) { $q[$k] = $filtres[$k]; }
    }
    if ($filtres['onglet'] !== 'tous') { $q['onglet'] = $filtres['onglet']; }
    foreach ($extra as $k => $v) {
        if ($v === null) { unset($q[$k]); } else { $q[$k] = $v; }
    }
    return '/index.php?' . http_build_query($q);
};
$ongletLien = fn(string $o) => $lien(['onglet' => $o === 'tous' ? null : $o, 'page' => null]);
$afficherFiliale = count($filiales) > 1 && empty($filtres['filiale_id']);
$filtresActifs = $filtres['q'] !== '' || $filtres['pays'] !== '' || $filtres['type'] !== '' || $filtres['relation'] !== '' || !empty($filtres['filiale_id']) || !empty($filtres['responsable_id']);
$perimetre = http_build_query(array_filter([
    'q' => $filtres['q'], 'pays' => $filtres['pays'], 'type' => $filtres['type'], 'relation' => $filtres['relation'],
    'filiale_id' => $filtres['filiale_id'] ?: '', 'responsable_id' => $filtres['responsable_id'] ?: '',
], fn($v) => $v !== '' && $v !== null));
$lienDossiers = '/index.php?r=dossiers&statut=actif' . ($perimetre !== '' ? '&cp=' . rawurlencode($perimetre) : '&cp=' . rawurlencode('tous=1'));
$lienExport = '/index.php?' . http_build_query(array_filter([
    'r' => 'clients/export.csv', 'q' => $filtres['q'], 'pays' => $filtres['pays'], 'type' => $filtres['type'], 'relation' => $filtres['relation'],
    'filiale_id' => $filtres['filiale_id'] ?: '', 'responsable_id' => $filtres['responsable_id'] ?: '',
    'onglet' => $filtres['onglet'] === 'tous' ? '' : $filtres['onglet'],
], fn($v) => $v !== '' && $v !== null));
$nbInactifs = $indicateurs['total'] - $indicateurs['actifs'];
?>
<div class="page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
  <div>
    <h1>Clients</h1>
    <div class="subtitle">Entreprises et particuliers suivis par l'équipe.</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($peutExporter): ?>
      <a href="<?= View::e($lienExport) ?>" class="btn btn-secondary" title="Exporte la liste selon les filtres appliqués (sans notes internes)">Exporter (CSV)</a>
    <?php endif; ?>
    <?php if (Auth::canWrite()): ?>
      <a href="/index.php?r=clients/nouveau" class="btn">Nouveau client</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!$schemaPret): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e">Mise à jour de la base à finaliser (migration V21) : les filtres Responsable et Relation, les contacts multiples, les adresses et les documents seront disponibles ensuite.</div>
<?php endif; ?>

<div class="grid-3">
  <a class="stat-tile stat-tile-link" href="<?= View::e($ongletLien('tous')) ?>">
    <div class="value"><?= (int) $indicateurs['total'] ?></div>
    <div class="label">Total clients<?= $filtresActifs ? ' (selon les filtres)' : '' ?></div>
  </a>
  <a class="stat-tile stat-tile-link" href="<?= View::e($ongletLien('actifs')) ?>">
    <div class="value"><?= (int) $indicateurs['actifs'] ?></div>
    <div class="label">Clients actifs</div>
  </a>
  <a class="stat-tile stat-tile-link" href="<?= View::e($lienDossiers) ?>">
    <div class="value"><?= (int) $indicateurs['dossiers_actifs'] ?></div>
    <div class="label">Dossiers actifs de ces clients</div>
  </a>
</div>

<div class="tabs">
  <a href="<?= View::e($ongletLien('tous')) ?>" class="<?= $filtres['onglet'] === 'tous' ? 'active' : '' ?>">Tous (<?= (int) $indicateurs['total'] ?>)</a>
  <a href="<?= View::e($ongletLien('actifs')) ?>" class="<?= $filtres['onglet'] === 'actifs' ? 'active' : '' ?>">Actifs (<?= (int) $indicateurs['actifs'] ?>)</a>
  <a href="<?= View::e($ongletLien('inactifs')) ?>" class="<?= $filtres['onglet'] === 'inactifs' ? 'active' : '' ?>">Inactifs (<?= (int) $nbInactifs ?>)</a>
</div>

<div class="filter-bar">
  <form method="get" action="/index.php">
    <input type="hidden" name="r" value="clients">
    <?php if ($filtres['onglet'] !== 'tous'): ?><input type="hidden" name="onglet" value="<?= View::e($filtres['onglet']) ?>"><?php endif; ?>
    <div class="f-group" style="min-width:220px;flex:2">
      <label for="fq">Recherche</label>
      <input type="text" id="fq" name="q" value="<?= View::e($filtres['q']) ?>" placeholder="Nom, référence, contact, e-mail, téléphone">
    </div>
    <div class="f-group">
      <label for="fpays">Pays</label>
      <select id="fpays" name="pays">
        <option value="">Tous</option>
        <?php foreach ($paysListe as $p): ?><option <?= $filtres['pays'] === $p ? 'selected' : '' ?>><?= View::e($p) ?></option><?php endforeach; ?>
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
    <div class="f-group">
      <label for="ftype">Type</label>
      <select id="ftype" name="type">
        <option value="">Tous</option>
        <?php foreach (Client::TYPES as $code => $lib): ?><option value="<?= $code ?>" <?= $filtres['type'] === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
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
    <div class="f-group">
      <label for="frel">Relation</label>
      <select id="frel" name="relation">
        <option value="">Toutes</option>
        <?php foreach (Client::RELATIONS as $code => $lib): ?><option value="<?= $code ?>" <?= $filtres['relation'] === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn">Filtrer</button>
      <?php if ($filtresActifs): ?><a class="btn btn-secondary" href="<?= View::e($filtres['onglet'] === 'tous' ? '/index.php?r=clients' : '/index.php?r=clients&onglet=' . $filtres['onglet']) ?>">Réinitialiser</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if (empty($liste['lignes'])): ?>
  <div class="card"><div class="empty-state">
    <?php if ($filtresActifs || $filtres['onglet'] !== 'tous'): ?>
      Aucun client ne correspond à ces critères.
    <?php else: ?>
      Aucun client pour le moment.<?php if (Auth::canWrite()): ?> <a href="/index.php?r=clients/nouveau">Créer le premier client</a>.<?php endif; ?>
    <?php endif; ?>
  </div></div>
<?php else: ?>
<table class="responsive-cards liste-cartes">
  <thead>
    <tr>
      <th>Client</th><th>Contact principal</th><th>Pays / ville</th>
      <?php if ($afficherFiliale): ?><th>Filiale</th><?php endif; ?>
      <th class="num">Dossiers actifs</th><th>Relation / statut</th><th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($liste['lignes'] as $c):
      $contact = trim(($c['contact_prenom'] ?? '') . ' ' . ($c['contact_nom'] ?? ''));
      $actif = (int) $c['is_active'] === 1;
      $relation = $c['relation'] ?? 'client';
  ?>
    <tr onclick="window.location='/index.php?r=clients/<?= (int) $c['id'] ?>'" style="cursor:pointer">
      <td data-label="Client"><strong><?= View::e($c['nom']) ?></strong><br><span style="font-size:12px;color:#888"><?= View::e($c['code'] ?? '') ?><?= !empty($c['type']) ? ' · ' . View::e(Client::TYPES[$c['type']] ?? $c['type']) : '' ?></span></td>
      <td data-label="Contact principal">
        <?php if ($contact !== '' || !empty($c['fonction_contact'])): ?>
          <?= View::e($contact) ?><?= !empty($c['fonction_contact']) ? '<br><span style="font-size:12px;color:#888">' . View::e($c['fonction_contact']) . '</span>' : '' ?>
        <?php else: ?>
          <span style="color:#999">—</span>
        <?php endif; ?>
        <?php if (!empty($c['email'])): ?><br><span style="font-size:12px"><?= View::e($c['email']) ?></span><?php endif; ?>
        <?php if (!empty($c['telephone'])): ?><br><span style="font-size:12px"><?= View::e($c['telephone']) ?></span><?php endif; ?>
      </td>
      <td data-label="Pays / ville"><?= View::e(trim(($c['pays'] ?? '') . ((!empty($c['pays']) && !empty($c['ville'])) ? ' · ' : '') . ($c['ville'] ?? ''))) ?: '—' ?></td>
      <?php if ($afficherFiliale): ?><td data-label="Filiale"><?= View::e($c['filiale_nom']) ?></td><?php endif; ?>
      <td data-label="Dossiers actifs" class="num"><?= (int) $c['nb_dossiers_actifs'] ?></td>
      <td data-label="Relation / statut">
        <span class="badge <?= Client::RELATIONS_BADGES[$relation] ?? 'badge-gray' ?>"><?= View::e(Client::RELATIONS[$relation] ?? $relation) ?></span>
        <span class="badge <?= $actif ? 'badge-green' : 'badge-gray' ?>"><?= $actif ? 'Actif' : 'Inactif' ?></span>
      </td>
      <td data-label="Actions" onclick="event.stopPropagation()" style="white-space:nowrap">
        <a href="/index.php?r=clients/<?= (int) $c['id'] ?>" class="btn btn-sm btn-secondary">Voir</a>
        <?php if (Auth::canWrite()): ?><a href="/index.php?r=clients/<?= (int) $c['id'] ?>/modifier" class="btn btn-sm btn-secondary">Modifier</a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($liste['pages'] > 1): ?>
<nav class="pagination" aria-label="Pagination" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:16px 0">
  <?php if ($liste['page'] > 1): ?><a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['page' => $liste['page'] - 1])) ?>">Précédent</a><?php endif; ?>
  <?php for ($i = 1; $i <= $liste['pages']; $i++): ?>
    <?php if ($i === 1 || $i === $liste['pages'] || abs($i - $liste['page']) <= 2): ?>
      <a class="btn btn-sm <?= $i === $liste['page'] ? '' : 'btn-secondary' ?>" href="<?= View::e($lien(['page' => $i > 1 ? $i : null])) ?>"><?= $i ?></a>
    <?php elseif (abs($i - $liste['page']) === 3): ?><span>…</span><?php endif; ?>
  <?php endfor; ?>
  <?php if ($liste['page'] < $liste['pages']): ?><a class="btn btn-sm btn-secondary" href="<?= View::e($lien(['page' => $liste['page'] + 1])) ?>">Suivant</a><?php endif; ?>
  <span style="font-size:12px;color:#888;margin-left:8px"><?= (int) $liste['total'] ?> client<?= $liste['total'] > 1 ? 's' : '' ?></span>
</nav>
<?php endif; ?>
<?php endif; ?>
