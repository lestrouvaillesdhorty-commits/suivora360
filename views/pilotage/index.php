<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Pilotage;

$onglets = ['general' => 'Vue générale', 'activites' => 'Activités', 'collaborateurs' => 'Collaborateurs', 'objectifs' => 'Objectifs & commissions', 'atraiter' => 'À traiter', 'pertes' => 'Pertes'];
$qs = $queryString;
function pilotageTabUrl(string $onglet, string $qs): string {
    $sep = $qs !== '' ? '&' . $qs : '';
    return '/index.php?r=pilotage&onglet=' . $onglet . $sep;
}
function pilotageExportUrl(string $format, string $onglet, string $qs): string {
    $sep = $qs !== '' ? '&' . $qs : '';
    return '/index.php?r=pilotage/export.' . $format . '&onglet=' . $onglet . $sep;
}
?>
<div class="pilotage-header">
  <div>
    <h1>Pilotage<?php if ($onglet === 'collaborateurs'): ?> <span class="pill-direction" title="Coûts et contributions individuelles confidentiels : réservé aux rôles Propriétaire, Admin d'organisation et Finance, y compris dans les exports."><?= Icon::svg('lock', 'icon', 13) ?> Réservé à la direction</span><?php endif; ?></h1>
    <div class="subtitle" style="margin-bottom:0">
      <?php if ($onglet === 'general'): ?>Comparez les résultats de vos activités
      <?php elseif ($onglet === 'activites'): ?>Comparez les résultats de vos activités
      <?php elseif ($onglet === 'pertes'): ?>Pourquoi on perd des devis
      <?php elseif ($onglet === 'atraiter'): ?>Devis, offres et demandes qui attendent une action
      <?php elseif ($onglet === 'objectifs'): ?>Objectifs mensuels de marge et commissions par collaborateur
      <?php else: ?>Performance et contribution des collaborateurs<?php endif; ?>
    </div>
  </div>
  <?php if (!in_array($onglet, ['objectifs', 'atraiter'], true)): ?>
  <div class="pilotage-actions">
    <div style="position:relative">
      <a href="<?= pilotageExportUrl('csv', $onglet, $qs) ?>" class="btn btn-pilotage" style="margin-right:6px"><?= Icon::svg('download', 'icon', 15) ?> Exporter Excel (CSV)</a>
      <?php if ($onglet !== 'pertes'): ?>
      <a href="<?= pilotageExportUrl('pdf', $onglet, $qs) ?>" class="btn btn-secondary" target="_blank" rel="noopener"><?= Icon::svg('printer', 'icon', 15) ?> Exporter PDF</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="pilotage-tabs">
  <?php foreach ($onglets as $key => $label): ?>
    <a href="<?= pilotageTabUrl($key, $qs) ?>" class="<?= $onglet === $key ? 'active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php if (!in_array($onglet, ['objectifs', 'atraiter'], true)): ?>
<div class="vues-bar">
  <?php if (!empty($vues)): ?>
  <div class="vues-liste"><span class="vues-label">Mes vues</span>
    <?php foreach ($vues as $v): ?>
      <span class="vue-chip"><a href="/index.php?r=pilotage&amp;onglet=<?= View::e($v['onglet']) ?><?= $v['filtres'] !== '' ? '&amp;' . View::e($v['filtres']) : '' ?>"><?= View::e($v['nom']) ?></a><form method="post" action="/index.php?r=pilotage/vues/<?= (int) $v['id'] ?>/supprimer" onsubmit="return confirm('Supprimer cette vue ?')"><input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>"><button type="submit" title="Supprimer cette vue" aria-label="Supprimer la vue <?= View::e($v['nom']) ?>">&times;</button></form></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <details class="vue-save-d"><summary>Enregistrer cette vue</summary>
  <form method="post" action="/index.php?r=pilotage/vues" class="vue-save">
    <input type="hidden" name="csrf_token" value="<?= View::e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="onglet" value="<?= View::e($onglet) ?>"><input type="hidden" name="filtres" value="<?= View::e($qs) ?>">
    <input type="text" name="nom" maxlength="80" required placeholder="Nommer cette vue (ex. Vue direction)" aria-label="Nom de la vue">
    <button type="submit" class="btn btn-secondary btn-sm">Enregistrer</button>
  </form></details>
  <a href="/index.php?r=pilotage&amp;onglet=<?= View::e($onglet) ?>" class="vue-reset">Effacer les filtres</a>
</div>
<div class="filter-bar">
  <form method="get" action="/index.php" id="filterForm">
    <input type="hidden" name="r" value="pilotage">
    <input type="hidden" name="onglet" value="<?= View::e($onglet) ?>">

    <div class="f-group">
      <label for="fPeriode">Période</label>
      <div class="f-input-wrap"><?= Icon::svg('calendar', 'icon', 14) ?>
      <select name="periode" id="fPeriode" onchange="document.getElementById('customDates').classList.toggle('show', this.value==='personnalise'); this.form.submit()">
        <?php foreach (Pilotage::PERIODES as $k => $l): ?>
          <option value="<?= $k ?>" <?= $filters['periode'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      </div>
    </div>

    <div class="f-group f-custom-dates <?= $filters['periode'] === 'personnalise' ? 'show' : '' ?>" id="customDates" style="flex-direction:row;gap:8px">
      <div>
        <label for="fDebut">Du</label>
        <input type="date" name="date_debut" id="fDebut" value="<?= View::e($filters['date_debut']) ?>">
      </div>
      <div>
        <label for="fFin">Au</label>
        <input type="date" name="date_fin" id="fFin" value="<?= View::e($filters['date_fin']) ?>">
      </div>
    </div>

    <div class="f-group">
      <label for="fActivite">Activité</label>
      <div class="f-input-wrap"><?= Icon::svg('layers', 'icon', 14) ?>
      <select name="activite" id="fActivite" onchange="this.form.submit()">
        <option value="">Toutes</option>
        <?php foreach ($activites as $a): ?>
          <option value="<?= View::e($a) ?>" <?= $filters['activite'] === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
        <?php endforeach; ?>
      </select>
      </div>
    </div>

    <div class="f-group">
      <label for="fResponsable">Responsable</label>
      <div class="f-input-wrap"><?= Icon::svg('user', 'icon', 14) ?>
      <select name="responsable_id" id="fResponsable" onchange="this.form.submit()">
        <option value="">Tous</option>
        <?php foreach ($utilisateurs as $u): ?>
          <option value="<?= $u['id'] ?>" <?= (string) $filters['responsable_id'] === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      </div>
    </div>

    <div class="f-group">
      <label for="fClient">Client</label>
      <div class="f-input-wrap"><?= Icon::svg('users', 'icon', 14) ?>
      <select name="client_id" id="fClient" onchange="this.form.submit()">
        <option value="">Tous</option>
        <?php foreach ($clientsFiltre ?? [] as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['client_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= View::e($c['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      </div>
    </div>

    <?php if ($onglet === 'collaborateurs'): ?>
    <div class="f-group">
      <label for="fRole">Rôle</label>
      <div class="f-input-wrap"><?= Icon::svg('briefcase', 'icon', 14) ?>
      <select name="role" id="fRole" onchange="this.form.submit()">
        <?php foreach (Pilotage::ROLES_COLLABORATEUR as $k => $l): ?>
          <option value="<?= $k ?>" <?= $filters['role'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      </div>
    </div>
    <?php endif; ?>

    <div class="f-group">
      <label for="fBase">Base de calcul</label>
      <div class="f-input-wrap"><?= Icon::svg('bar-chart-2', 'icon', 14) ?>
      <select name="base" id="fBase" onchange="this.form.submit()">
        <?php foreach (Pilotage::BASES as $k => $l): ?>
          <option value="<?= $k ?>" <?= $filters['base'] === $k ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      </div>
    </div>

    <div class="f-group">
      <label for="fDevise">Devise de reporting</label>
      <div class="f-input-wrap"><?= Icon::svg('dollar-sign', 'icon', 14) ?>
      <select name="devise" id="fDevise" onchange="this.form.submit()">
        <option value="EUR" <?= $filters['devise'] === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
        <option value="FCFA" <?= $filters['devise'] === 'FCFA' ? 'selected' : '' ?>>FCFA</option>
      </select>
      </div>
    </div>

    <noscript><div class="f-actions"><button type="submit" class="btn btn-sm">Appliquer</button></div></noscript>
  </form>
  <div class="period-label"><?= Icon::svg('calendar', 'icon', 13) ?> <?= View::e($filters['periode_label']) ?> · Base : <?= Pilotage::BASES[$filters['base']] ?> · Devise : <?= View::e($filters['devise']) ?></div>
</div>

<?php endif; ?>

<?php if ($onglet === 'general'): ?>
  <?php View::renderPlain('pilotage/_general', ['vueGenerale' => $vueGenerale, 'filters' => $filters, 'qs' => $qs]) ?>
<?php elseif ($onglet === 'activites'): ?>
  <?php View::renderPlain('pilotage/_activites', ['activitesData' => $activitesData, 'filters' => $filters, 'qs' => $qs]) ?>
<?php elseif ($onglet === 'pertes'): ?>
  <?php View::renderPlain('pilotage/_pertes', ['pertesData' => $pertesData, 'filters' => $filters, 'qs' => $qs, 'utilisateurs' => $utilisateurs]) ?>
<?php elseif ($onglet === 'atraiter'): ?>
  <?php View::renderPlain('pilotage/_atraiter', ['aTraiterData' => $aTraiterData, 'seuil' => $seuil, 'typeFiltre' => $typeFiltre, 'respFiltre' => $respFiltre, 'utilisateurs' => $utilisateurs]) ?>
<?php elseif ($onglet === 'objectifs'): ?>
  <?php View::renderPlain('pilotage/_objectifs', ['objectifsData' => $objectifsData, 'objectifsDispo' => $objectifsDispo, 'mois' => $mois, 'filters' => $filters]) ?>
<?php else: ?>
  <?php View::renderPlain('pilotage/_collaborateurs', ['collaborateursData' => $collaborateursData, 'achatsData' => $achatsData ?? null, 'filters' => $filters, 'qs' => $qs]) ?>
<?php endif; ?>
