<?php
use App\Core\Auth;
use App\Core\Icon;
use App\Core\View;
use App\Models\Commande;
use App\Models\Demande;
use App\Models\Utilisateur;

if (!function_exists('strftime_fr')) {
    function strftime_fr(string $date): string {
        $mois = [1=>'janv.',2=>'févr.',3=>'mars',4=>'avr.',5=>'mai',6=>'juin',7=>'juil.',8=>'août',9=>'sept.',10=>'oct.',11=>'nov.',12=>'déc.'];
        return $mois[(int) date('n', strtotime($date))];
    }
}

/* [ajouté 04/10] Libellés/couleurs locaux au statut d'une demande — même
   principe que views/requests/index.php ($statutBadges), pas centralisé sur
   le modèle. Sert à distinguer le Statut (à qualifier, en cours...) de la
   Priorité (normale, haute...) sur "Dernières demandes", qui affichait par
   erreur la priorité sous une étiquette "Statut". */
$statutDemandeBadges = [
    'a_qualifier' => ['À qualifier', 'badge-yellow'],
    'en_attente_info' => ["En attente d'infos", 'badge-yellow'],
    'qualifiee' => ['Qualifiée', 'badge-blue'],
    'rejetee' => ['Rejetée', 'badge-red'],
];

/* [ajouté 04/10] Couleurs par type d'entité pour "Actions prioritaires" —
   reprend exactement la palette déjà utilisée pour "Suivi des opérations",
   pour que le même type de dossier se reconnaisse visuellement d'un bloc à
   l'autre du tableau de bord. */
$prioTypeStyle = [
    'demande' => ['bg' => '#EEEBFF', 'fg' => '#2D18FA', 'icon' => 'file-text'],
    'offre' => ['bg' => '#dbeafe', 'fg' => '#2563eb', 'icon' => 'file'],
    'cotation' => ['bg' => '#ffedd5', 'fg' => '#c2410c', 'icon' => 'tag'],
    'commande' => ['bg' => '#dbeafe', 'fg' => '#2563eb', 'icon' => 'shopping-cart'],
];
$prioTabLabels = ['retard' => 'En retard', 'aujourd_hui' => "Aujourd'hui", 'a_venir' => 'À venir'];
$prioEmptyMessages = [
    'retard' => 'Aucune action en retard — bien joué.',
    'aujourd_hui' => "Rien à traiter aujourd'hui.",
    'a_venir' => 'Rien de prévu dans les prochains jours.',
];
$prioTabDefaut = 'a_venir';
foreach (['retard', 'aujourd_hui', 'a_venir'] as $cle) {
    if (!empty($actionsPrioritaires[$cle])) {
        $prioTabDefaut = $cle;
        break;
    }
}
?>
<div class="pilotage-header">
  <div>
    <h1>Tableau de bord</h1>
    <div class="subtitle" style="margin-bottom:0">Vos priorités et opérations à suivre — pour l'analyse détaillée par activité/responsable, voir <a href="/index.php?r=pilotage">Pilotage</a><?php if (!Auth::canVoirPilotage()): ?> (réservé à certains rôles)<?php endif; ?>.</div>
  </div>
  <a href="/index.php?r=demandes/nouvelle" class="btn btn-pilotage"><?= Icon::svg('plus', 'icon', 16) ?> Nouvelle demande</a>
</div>

<?php /* [ajouté 03/10, demande explicite de Marie Laure] Switcher filiale/activité — jusqu'ici impossible de filtrer le tableau de bord. */ ?>
<?php if (count($filiales) > 1 || !empty($activites)): ?>
<form method="get" action="/index.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin:14px 0">
  <?php /* Le tableau de bord est la route "/" (pas "r=dashboard") — pas de champ "r" ici, sinon le routeur renvoie un 404. */ ?>
  <?php if (count($filiales) > 1): ?>
  <div class="form-group" style="margin-bottom:0;min-width:200px">
    <label style="font-size:12px">Filiale</label>
    <select name="filiale_id" onchange="this.form.submit()">
      <option value="">Toutes mes filiales</option>
      <?php foreach ($filiales as $f): ?>
        <option value="<?= $f['id'] ?>" <?= $filialeIdSelection === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="form-group" style="margin-bottom:0;min-width:220px">
    <label style="font-size:12px">Activité</label>
    <select name="activite" onchange="this.form.submit()">
      <option value="">Toutes les activités</option>
      <?php foreach ($activites as $a): ?>
        <option value="<?= View::e($a) ?>" <?= $activiteSelection === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($filialeIdSelection || $activiteSelection): ?>
    <a href="/index.php" class="btn btn-sm btn-secondary">Réinitialiser les filtres</a>
  <?php endif; ?>
</form>
<?php endif; ?>

<div class="dash-kpi-row" style="margin-top:18px">
  <a href="/index.php?r=demandes&statut=a_qualifier" class="dash-kpi">
    <div class="dk-icon" style="background:#EEEBFF;color:#2D18FA"><?= Icon::svg('file-text', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $demandeCounts['a_qualifier'] ?></div><div class="dk-label">À qualifier</div><div class="dk-link">Voir les demandes <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=demandes" class="dash-kpi">
    <div class="dk-icon" style="background:#dbeafe;color:#2563eb"><?= Icon::svg('file', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $offresAAnalyserCount ?></div><div class="dk-label">Offres à analyser</div><div class="dk-link">Voir les offres <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=dossiers" class="dash-kpi">
    <div class="dk-icon" style="background:#ffedd5;color:#c2410c"><?= Icon::svg('tag', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $cotationsARelancerCount ?></div><div class="dk-label">Cotations à relancer</div><div class="dk-link">Voir les cotations <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <?php /* [corrigé 04/10] Reliait "En retard" au seul compteur demandeCounts['en_retard']
     (4) alors que le bloc Alertes d'en dessous affichait 6 lignes (demandes +
     commandes + cotations + offres en retard confondues) — l'écart venait du
     périmètre, pas d'un bug de calcul. Ce 4e indicateur compte désormais
     exactement la même chose que l'onglet "En retard" d'Actions prioritaires,
     et le libellé est explicite sur son périmètre élargi. */ ?>
  <a href="#actions-prioritaires" class="dash-kpi">
    <div class="dk-icon" style="background:#fee2e2;color:#dc2626"><?= Icon::svg('clock', 'icon', 21) ?></div>
    <div><div class="dk-value" style="<?= $actionsEnRetardCount > 0 ? 'color:#991b1b' : '' ?>"><?= $actionsEnRetardCount ?></div><div class="dk-label">Actions en retard</div><div class="dk-link">Voir les actions <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
</div>

<?php
$opsColors = [
  'demandes'   => ['bg' => '#EEEBFF', 'fg' => '#2D18FA', 'icon' => 'file-text'],
  'dossiers'   => ['bg' => '#d1fae5', 'fg' => '#059669', 'icon' => 'folder'],
  'offres'     => ['bg' => '#dbeafe', 'fg' => '#2563eb', 'icon' => 'file'],
  'cotations'  => ['bg' => '#ffedd5', 'fg' => '#c2410c', 'icon' => 'tag'],
  'commandes'  => ['bg' => '#dbeafe', 'fg' => '#2563eb', 'icon' => 'shopping-cart'],
  'livraisons' => ['bg' => '#ede9fe', 'fg' => '#7c3aed', 'icon' => 'truck'],
];
$opsLabels = ['demandes' => 'Demandes', 'dossiers' => 'Dossiers', 'offres' => 'Offres', 'cotations' => 'Cotations', 'commandes' => 'Commandes', 'livraisons' => 'Livraisons'];
$opsLinks = ['demandes' => 'demandes', 'dossiers' => 'dossiers&statut=actif', 'offres' => 'demandes', 'cotations' => 'dossiers', 'commandes' => 'dossiers', 'livraisons' => 'dossiers'];
$opsKeys = array_keys($opsLabels);
$graviteBorder = ['haute' => '#dc2626', 'moyenne' => '#f97316', 'basse' => '#eab308'];
?>

<?php /* [réorganisé 04/10, sur la base de la maquette de référence de Marie
   Laure] Les blocs ci-dessous sont désormais les enfants directs d'une même
   grille (.dash-grid, voir app.css), alignée sur les colonnes de la ligne
   d'indicateurs au-dessus — plus de décalage entre "blocs de droite" et
   indicateurs signalé sur ordinateur. "Suivi des opérations" est repliable
   sur téléphone et se réordonne après "Actions prioritaires" (cf. CSS),
   sans dupliquer son contenu. */ ?>
<div class="dash-grid" style="margin-top:18px">

  <?php /* [corrigé 04/10] Les liens de "Suivi des opérations" ne doivent pas
     se trouver dans <summary> : un clic dessus déclenchait à la fois la
     navigation ET le repli/dépli, ce qui ouvrait la mauvaise page au premier
     clic sur mobile. Seul l'intitulé (non cliquable à part le repli) est
     dans <summary> ; les liens restent dans un bloc frère normal. */ ?>
  <details class="ops-row ops-collapse dg-ops" open>
    <summary>
      <h2><?= Icon::svg('trending-up', 'icon', 17) ?> Suivi des opérations</h2>
      <span class="ops-chevron"><?= Icon::svg('chevron-right', 'icon', 16) ?></span>
    </summary>
    <div class="ops-items">
      <?php foreach ($opsKeys as $i => $key): ?>
        <?php if ($i > 0): ?><span class="ops-sep"><?= Icon::svg('chevron-right') ?></span><?php endif; ?>
        <a href="/index.php?r=<?= $opsLinks[$key] ?>" class="ops-item">
          <span class="oi-icon" style="background:<?= $opsColors[$key]['bg'] ?>;color:<?= $opsColors[$key]['fg'] ?>"><?= Icon::svg($opsColors[$key]['icon'], 'icon', 15) ?></span>
          <span><span class="oi-n"><?= $suivi[$key] ?></span><br><span class="oi-label"><?= $opsLabels[$key] ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  </details>

  <div class="card dg-actions" id="actions-prioritaires">
    <h2 style="display:flex;align-items:center;gap:8px"><?= Icon::svg('target', 'icon', 17) ?> Actions prioritaires</h2>
    <div class="prio-tabs" role="tablist">
      <?php foreach (['retard', 'aujourd_hui', 'a_venir'] as $cle): ?>
        <button type="button" class="prio-tab<?= $cle === $prioTabDefaut ? ' active' : '' ?>" data-tab="<?= $cle ?>">
          <?= $prioTabLabels[$cle] ?> <span class="prio-tab-count"><?= count($actionsPrioritaires[$cle]) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <?php foreach (['retard', 'aujourd_hui', 'a_venir'] as $cle): ?>
      <?php $items = $actionsPrioritaires[$cle]; ?>
      <div class="prio-panel" data-panel="<?= $cle ?>" <?= $cle !== $prioTabDefaut ? 'hidden' : '' ?>>
        <?php if (empty($items)): ?>
          <div class="empty-state"><?= $prioEmptyMessages[$cle] ?></div>
        <?php else: ?>
          <?php
          $premiers = array_slice($items, 0, 3);
          $reste = array_slice($items, 3);
          $renderPrioItem = function (array $it) use ($prioTypeStyle, $cle) {
              $style = $prioTypeStyle[$it['type']];
              $joursEcart = (int) round((strtotime($it['echeance']) - strtotime(date('Y-m-d'))) / 86400);
              if ($cle === 'retard') {
                  $echeanceTexte = abs($joursEcart) . ' j de retard';
                  $echeanceCouleur = '#dc2626';
              } elseif ($cle === 'aujourd_hui') {
                  $echeanceTexte = "Aujourd'hui";
                  $echeanceCouleur = '#c2410c';
              } else {
                  $echeanceTexte = $joursEcart <= 1 ? 'Demain' : 'Dans ' . $joursEcart . ' j';
                  $echeanceCouleur = 'inherit';
              }
              ?>
              <li class="prio-item" onclick="window.location='<?= View::e($it['lien']) ?>'">
                <span class="prio-type" style="background:<?= $style['bg'] ?>;color:<?= $style['fg'] ?>"><?= Icon::svg($style['icon'], 'icon', 16) ?></span>
                <div class="prio-main">
                  <div class="pm-title"><?= View::e($it['titre'] ?: $it['type_label']) ?></div>
                  <div class="pm-ref"><?= View::e($it['reference'] ?? '') ?> <span class="badge badge-gray" style="text-transform:capitalize"><?= $it['type_label'] ?></span></div>
                </div>
                <div class="prio-action"><?= View::e($it['action']) ?></div>
                <div class="prio-side">
                  <span class="ps-echeance" style="color:<?= $echeanceCouleur ?>" <?= $it['echeance_synthetique'] ? 'title="Échéance estimée — aucune date de relance enregistrée pour ce type de dossier"' : '' ?>><?= $echeanceTexte ?><?= $it['echeance_synthetique'] ? ' *' : '' ?></span>
                  <span class="ps-resp"><?= !empty($it['responsable_id']) ? View::e(Utilisateur::nameOf((int) $it['responsable_id'])) : 'Non assigné' ?></span>
                </div>
                <?= Icon::rowArrow() ?>
              </li>
              <?php
          };
          ?>
          <ul class="prio-list">
            <?php foreach ($premiers as $it): $renderPrioItem($it); endforeach; ?>
          </ul>
          <?php if (!empty($reste)): ?>
            <ul class="prio-list prio-list-more" hidden>
              <?php foreach ($reste as $it): $renderPrioItem($it); endforeach; ?>
            </ul>
            <button type="button" class="prio-more-toggle" data-label-more="Voir les <?= count($items) ?> actions" data-label-less="Voir moins">Voir les <?= count($items) ?> actions <?= Icon::svg('chevron-right', 'icon', 13) ?></button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card dg-echeances">
    <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('calendar', 'icon', 17) ?> Échéances à venir</span> <span style="font-size:12px;font-weight:400;color:#888">(7 jours)</span></h2>
    <?php if (empty($echeancesAVenir)): ?>
      <div class="empty-state">Rien à venir dans les 7 prochains jours.</div>
    <?php else: ?>
      <ul class="timeline">
      <?php foreach ($echeancesAVenir as $e): ?>
      <li onclick="window.location='/index.php?r=<?= $e['type'] === 'demande' ? 'demandes' : 'dossiers' ?>/<?= $e['id'] ?>'">
        <div class="tl-date"><div class="d"><?= date('d', strtotime($e['echeance'])) ?></div><div class="m"><?= strftime_fr($e['echeance']) ?></div></div>
        <div class="tl-dot" style="background:<?= $e['type'] === 'demande' ? '#2D18FA' : '#10b981' ?>"></div>
        <div class="tl-body">
          <div class="tl-title truncate"><?= View::e($e['objet']) ?></div>
          <div class="tl-sub"><?= View::e($e['reference']) ?> <span class="badge badge-gray" style="text-transform:capitalize"><?= $e['type'] ?></span></div>
        </div>
        <?= Icon::rowArrow() ?>
      </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card dg-demandes">
    <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('inbox', 'icon', 17) ?> Dernières demandes</span> <span style="font-size:12px;font-weight:400"><a href="/index.php?r=demandes">Toutes <?= Icon::svg('arrow-right', 'icon', 12) ?></a></span></h2>
    <?php if (empty($dernieresDemandes)): ?>
      <div class="empty-state">Aucune demande pour le moment.</div>
    <?php else: ?>
      <ul class="feed-list">
      <?php foreach (array_slice($dernieresDemandes, 0, 3) as $d): ?>
      <li onclick="window.location='/index.php?r=demandes/<?= $d['id'] ?>'">
        <span class="fl-icon" style="background:#EEEBFF;color:#2D18FA"><?= Icon::svg('file-text', 'icon', 16) ?></span>
        <div class="fl-body">
          <div class="fl-title"><?= View::e($d['objet']) ?></div>
          <div class="fl-sub"><?= View::e($d['reference']) ?> ·
            <span class="badge <?= $statutDemandeBadges[$d['statut']][1] ?? 'badge-gray' ?>"><?= $statutDemandeBadges[$d['statut']][0] ?? ucfirst($d['statut']) ?></span>
          </div>
        </div>
        <div class="fl-meta">
          <span class="badge <?= Demande::PRIORITE_BADGES[$d['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$d['priorite']] ?? ucfirst($d['priorite']) ?></span><br>
          <?= $d['recue_le'] ? date('d/m/Y', strtotime($d['recue_le'])) : '—' ?>
        </div>
      </li>
      <?php endforeach; ?>
      </ul>
      <?php if (count($dernieresDemandes) > 3): ?>
        <a href="/index.php?r=demandes" class="prio-more-toggle" style="margin-top:4px">Voir toutes les demandes <?= Icon::svg('arrow-right', 'icon', 13) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card dg-livraisons">
    <h2 style="display:flex;align-items:center;gap:8px"><?= Icon::svg('truck', 'icon', 17) ?> Livraisons à suivre</h2>
    <?php if (empty($livraisonsEnCours)): ?>
      <div class="empty-state">Aucune livraison en cours.</div>
    <?php else: ?>
      <ul class="feed-list">
      <?php foreach (array_slice($livraisonsEnCours, 0, 3) as $l): ?>
      <li onclick="window.location='/index.php?r=dossiers/<?= $l['dossier_id'] ?>/commande'">
        <span class="fl-icon" style="background:#ede9fe;color:#7c3aed"><?= Icon::svg('truck', 'icon', 16) ?></span>
        <div class="fl-body">
          <div class="fl-title"><?= View::e($l['reference']) ?> <span class="badge badge-blue" style="margin-left:4px"><?= Commande::libelleStatutEtape('livraison', $l['livraison_statut'] ?? 'a_faire') ?></span></div>
          <div class="fl-sub">
            <?= View::e($l['fournisseur_nom'] ?: '—') ?> ·
            <?php if ($l['destination_pays']): ?>
              <?= View::e($l['destination_pays']) ?>
            <?php else: ?>
              Destination non renseignée
              <?php /* [ajouté 04/10] Pas de parcours d'édition après qualification
                 pour l'instant (destination_pays n'est saisissable qu'à la
                 qualification de la demande) — lien provisoire vers la fiche
                 dossier, à remplacer si un vrai champ éditable est construit. */ ?>
              <a href="/index.php?r=dossiers/<?= $l['dossier_id'] ?>" onclick="event.stopPropagation()" style="color:var(--pilotage-violet);font-weight:600">Compléter</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="fl-meta"><?= $l['livraison_prevue'] ? date('d/m/Y', strtotime($l['livraison_prevue'])) : 'Date non renseignée' ?></div>
      </li>
      <?php endforeach; ?>
      </ul>
      <?php if (count($livraisonsEnCours) > 3): ?>
        <a href="/index.php?r=dossiers" class="prio-more-toggle" style="margin-top:4px">Voir toutes les livraisons <?= Icon::svg('arrow-right', 'icon', 13) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  var card = document.getElementById('actions-prioritaires');
  if (!card) { return; }
  var tabs = card.querySelectorAll('.prio-tab');
  var panels = card.querySelectorAll('.prio-panel');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(function (t) { t.classList.remove('active'); });
      panels.forEach(function (p) { p.hidden = true; });
      tab.classList.add('active');
      var panel = card.querySelector('.prio-panel[data-panel="' + tab.dataset.tab + '"]');
      if (panel) { panel.hidden = false; }
    });
  });
  card.querySelectorAll('.prio-more-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.closest('.prio-panel');
      var more = panel.querySelector('.prio-list-more');
      if (!more) { return; }
      more.hidden = !more.hidden;
      btn.firstChild.textContent = more.hidden ? btn.dataset.labelMore + ' ' : btn.dataset.labelLess + ' ';
    });
  });
})();
</script>
