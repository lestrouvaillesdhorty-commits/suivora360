<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Demande;
use App\Models\Utilisateur;

/**
 * [réécrit 04/10, refonte du module Demandes] Vues rapides (onglets) +
 * filtres combinables + colonnes harmonisées avec la maquette + pagination.
 * Voir DemandeController::index() pour la construction des filtres/compteurs.
 */
$statutsFiltre = [
    'a_qualifier' => 'À qualifier',
    'en_attente_info' => "En attente d'infos",
    'en_retard' => 'En retard',
    'qualifiee' => 'Qualifiée',
    'rattachee' => 'Rattachée',
    'transformee' => 'Transformée en dossier',
    'rejetee' => 'Rejetée',
    'archivee' => 'Archivée',
];
$statutBadges = Demande::STATUT_BADGES;
$nonQualifiee = ['a_qualifier', 'en_attente_info'];

// Conserve tous les paramètres de la requête courante, sauf ceux explicitement
// remplacés — utilisé pour les liens de tri et de pagination.
$avecParams = function (array $remplacements) {
    return '/index.php?' . http_build_query(array_merge(['r' => 'demandes'], $_GET, $remplacements));
};

$showFiliale = count($filiales) > 1 && empty($filters['filiale_id']);
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
  <div>
    <h1>Demandes</h1>
    <div class="subtitle"><?= $total ?> résultat<?= $total > 1 ? 's' : '' ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/index.php?r=demandes/export.csv&<?= http_build_query($_GET) ?>" class="btn btn-secondary"><?= Icon::svg('download', 'icon', 15) ?> Exporter (CSV)</a>
    <a href="/index.php?r=demandes/importer" class="btn btn-secondary"><?= Icon::svg('upload', 'icon', 15) ?> Importer</a>
    <a href="/index.php?r=demandes/nouvelle" class="btn">+ Nouvelle demande</a>
  </div>
</div>

<div class="kpi-row" style="margin-top:16px">
  <div class="kpi-card">
    <div class="kpi-icon"><?= Icon::svg('inbox', '', 20) ?></div>
    <div class="kpi-body"><div class="kpi-label">Reçues ce mois</div><div class="kpi-value"><?= $kpis['recues_mois'] ?></div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon orange"><?= Icon::svg('clock', '', 20) ?></div>
    <div class="kpi-body"><div class="kpi-label">À qualifier</div><div class="kpi-value"><?= $kpis['a_qualifier'] ?></div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon red"><?= Icon::svg('alert-triangle', '', 20) ?></div>
    <div class="kpi-body"><div class="kpi-label">En retard</div><div class="kpi-value"><?= $kpis['en_retard'] ?></div></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-icon green"><?= Icon::svg('check-circle', '', 20) ?></div>
    <div class="kpi-body"><div class="kpi-label">Qualifiées ce mois</div><div class="kpi-value"><?= $kpis['qualifiees_mois'] ?></div></div>
  </div>
</div>
<div class="scope-note">Chiffres calculés sur le mois en cours, pour l'organisation et les filtres actifs.</div>

<div class="prio-tabs" style="margin-top:16px">
  <?php
  $vues = [
      'toutes' => ['Toutes', '/index.php?r=demandes'],
      'a_qualifier' => ['À qualifier', '/index.php?r=demandes&statut=a_qualifier'],
      'mes_demandes' => ['Mes demandes', '/index.php?r=demandes&responsable_id=me'],
      'en_retard' => ['En retard', '/index.php?r=demandes&statut=en_retard'],
      'archivees' => ['Archivées', '/index.php?r=demandes&statut=archivee'],
  ];
  foreach ($vues as $code => [$label, $href]): ?>
    <a href="<?= $href ?>" class="prio-tab <?= $vueActive === $code ? 'active' : '' ?>" style="text-decoration:none">
      <?= View::e($label) ?> <span class="prio-tab-count"><?= $compteursVues[$code] ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="filter-bar">
  <form method="get" action="/index.php">
    <input type="hidden" name="r" value="demandes">
    <div class="f-group">
      <label for="fq">Recherche</label>
      <div class="f-input-wrap"><?= Icon::svg('search', 'icon', 14) ?>
        <input type="text" id="fq" name="q" placeholder="Référence, objet, contact, entreprise..." value="<?= View::e($filters['recherche'] ?? '') ?>">
      </div>
    </div>
    <?php if (count($filiales) > 1): ?>
    <div class="f-group">
      <label for="fFiliale">Filiale</label>
      <select id="fFiliale" name="filiale_id">
        <option value="">Toutes</option>
        <?php foreach ($filiales as $f): ?>
          <option value="<?= $f['id'] ?>" <?= (string) ($filters['filiale_id'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="f-group">
      <label for="fActivite">Activité</label>
      <select id="fActivite" name="activite">
        <option value="">Toutes</option>
        <?php foreach (Demande::ACTIVITES as $a): ?>
          <option <?= ($filters['activite'] ?? '') === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="f-group">
      <label for="fStatut">Statut</label>
      <select id="fStatut" name="statut">
        <option value="">Tous les statuts</option>
        <?php foreach ($statutsFiltre as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($filters['statut'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="f-group">
      <label for="fResponsable">Responsable</label>
      <select id="fResponsable" name="responsable_id">
        <option value="">Tous</option>
        <option value="non_assigne" <?= ($filters['responsable_id'] ?? '') === 'non_assigne' ? 'selected' : '' ?>>— Non assigné —</option>
        <?php foreach ($utilisateurs as $u): ?>
          <option value="<?= $u['id'] ?>" <?= (string) ($filters['responsable_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="f-group">
      <label for="fPriorite">Priorité</label>
      <select id="fPriorite" name="priorite">
        <option value="">Toutes</option>
        <?php foreach (Demande::PRIORITES as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($filters['priorite'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!empty($canaux)): ?>
    <div class="f-group">
      <label for="fCanal">Canal</label>
      <select id="fCanal" name="canal">
        <option value="">Tous</option>
        <?php foreach ($canaux as $c): ?>
          <option value="<?= View::e($c) ?>" <?= ($filters['canal'] ?? '') === $c ? 'selected' : '' ?>><?= View::e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="f-group" style="flex-direction:row;gap:8px;min-width:230px">
      <div>
        <label for="fDebut">Reçue du</label>
        <input type="date" id="fDebut" name="date_debut" value="<?= View::e($filters['date_debut'] ?? '') ?>">
      </div>
      <div>
        <label for="fFin">au</label>
        <input type="date" id="fFin" name="date_fin" value="<?= View::e($filters['date_fin'] ?? '') ?>">
      </div>
    </div>
    <div class="f-group">
      <label for="fTri">Trier par</label>
      <select id="fTri" name="tri">
        <?php foreach (Demande::TRIS as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($filters['tri'] ?? 'created_at') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="f-group" style="max-width:110px">
      <label for="fSens">Ordre</label>
      <select id="fSens" name="sens">
        <option value="desc" <?= ($filters['sens'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>Décroissant</option>
        <option value="asc" <?= ($filters['sens'] ?? '') === 'asc' ? 'selected' : '' ?>>Croissant</option>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filtrer</button>
    <a href="/index.php?r=demandes" class="btn btn-secondary">Réinitialiser</a>
  </form>
</div>

<?php if (empty($demandes)): ?>
  <div class="card"><div class="empty-state">Aucune demande ne correspond à ces filtres.</div></div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
<table class="responsive-cards">
  <thead>
    <tr><th>Référence</th><th>Objet / Client ou contact</th><th>Activité</th><th>Responsable</th><th>Priorité</th><th>Statut</th><th>Échéance</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($demandes as $d): ?>
    <?php $contact = $d['client_id'] ? \App\Models\Client::nameOf($d['client_id']) : ($d['expediteur_nom'] ?: '—'); ?>
    <tr onclick="window.location='/index.php?r=demandes/<?= $d['id'] ?>'" style="cursor:pointer">
      <td data-label="Référence">
        <a href="/index.php?r=demandes/<?= $d['id'] ?>" onclick="event.stopPropagation()"><?= View::e($d['reference']) ?></a>
        <?php if ($showFiliale): ?><div style="font-size:11.5px;color:#888"><?= View::e($d['filiale_nom']) ?></div><?php endif; ?>
      </td>
      <td data-label="Objet"><strong><?= View::e($d['objet']) ?></strong><br><span style="color:#888"><?= View::e($contact) ?></span></td>
      <td data-label="Activité"><?= View::e($d['activite']) ?: '—' ?></td>
      <td data-label="Responsable"><?= $d['responsable_id'] ? View::e(Utilisateur::nameOf($d['responsable_id'])) : '— Non assigné —' ?></td>
      <td data-label="Priorité"><span class="badge <?= Demande::PRIORITE_BADGES[$d['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$d['priorite']] ?? ucfirst($d['priorite']) ?></span></td>
      <td data-label="Statut">
        <?php $si = $statutBadges[$d['statut']] ?? [ucfirst($d['statut']), 'badge-gray']; ?>
        <span class="badge <?= $si[1] ?>"><?= $si[0] ?></span>
        <?php if (in_array($d['statut'], ['qualifiee', 'rattachee'], true)): ?>
          <?php $dossierId = Demande::dossierLieId($d); ?>
          <?php if ($dossierId): ?>
            <br><a href="/index.php?r=dossiers/<?= $dossierId ?>" style="font-size:12px" onclick="event.stopPropagation()">Voir le dossier →</a>
          <?php endif; ?>
        <?php endif; ?>
      </td>
      <td data-label="Échéance">
        <?php $enRetard = $d['echeance'] && strtotime($d['echeance']) < strtotime('today') && in_array($d['statut'], $nonQualifiee, true); ?>
        <?= $d['echeance'] ? date('d/m/Y', strtotime($d['echeance'])) : '—' ?>
        <?php if ($enRetard): ?> <span class="badge badge-red">En retard</span><?php endif; ?>
      </td>
      <td data-label="Actions" style="white-space:nowrap">
        <a href="/index.php?r=demandes/<?= $d['id'] ?>" class="btn btn-sm btn-secondary" title="Consulter" onclick="event.stopPropagation()"><?= Icon::svg('eye', 'icon', 14) ?></a>
        <?php if (!in_array($d['statut'], ['rejetee', 'archivee'], true)): ?>
          <a href="/index.php?r=demandes/<?= $d['id'] ?>/modifier" class="btn btn-sm btn-secondary" title="Modifier" onclick="event.stopPropagation()"><?= Icon::svg('edit-2', 'icon', 14) ?></a>
        <?php endif; ?>
        <form method="post" action="/index.php?r=demandes/<?= $d['id'] ?>/supprimer" style="display:inline" onclick="event.stopPropagation()" onsubmit="return confirm('Supprimer définitivement la demande <?= View::e(addslashes($d['reference'])) ?> — <?= View::e(addslashes($d['objet'])) ?> ?');">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <button type="submit" class="btn btn-sm btn-secondary" title="Supprimer"><?= Icon::svg('trash-2', 'icon', 14) ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:14px;margin-top:16px">
  <?php if ($page > 1): ?><a href="<?= $avecParams(['page' => $page - 1]) ?>" class="btn btn-sm btn-secondary">&larr; Précédent</a><?php endif; ?>
  <span style="font-size:13px;color:#666">Page <?= $page ?> / <?= $totalPages ?></span>
  <?php if ($page < $totalPages): ?><a href="<?= $avecParams(['page' => $page + 1]) ?>" class="btn btn-sm btn-secondary">Suivant &rarr;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
