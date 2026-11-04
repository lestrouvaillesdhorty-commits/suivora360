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
    'demande' => ['bg' => '#DDF1F3', 'fg' => '#0B7C8A', 'icon' => 'file-text'],
    'offre' => ['bg' => '#DFF3E8', 'fg' => '#14633C', 'icon' => 'file'],
    'cotation' => ['bg' => '#FFE3DA', 'fg' => '#8F3217', 'icon' => 'tag'],
    'commande' => ['bg' => '#DFF3E8', 'fg' => '#14633C', 'icon' => 'shopping-cart'],
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
    <h1>Bonjour <?= View::e(trim((string) ($currentUser['nom'] ?? ''))) ?></h1>
    <div class="subtitle" style="margin-bottom:0">Voici ce qui demande votre attention aujourd'hui.</div>
  </div>
  <a href="/index.php?r=demandes/nouvelle" class="btn btn-pilotage"><?= Icon::svg('plus', 'icon', 16) ?> Nouvelle demande</a>
</div>

<?php /* [ajouté 03/10, demande explicite de Marie Laure] Switcher filiale/activité — jusqu'ici impossible de filtrer le tableau de bord. */ ?>
<form method="get" action="/index.php" class="dash-filtres" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:14px 0">
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
  <div class="form-group" style="margin-bottom:0;min-width:240px">
    <label style="font-size:12px">Activité</label>
    <select name="activite" onchange="this.form.submit()">
      <option value="">Toutes les activités</option>
      <?php foreach ($activites as $a): ?>
        <option value="<?= View::e($a) ?>" <?= $activiteSelection === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (!empty($sousOptions)): ?>
  <div class="form-group" style="margin-bottom:0;min-width:200px">
    <label style="font-size:12px">Sous-activité</label>
    <select name="sous_activite" onchange="this.form.submit()">
      <option value="">Toutes</option>
      <?php foreach ($sousOptions as $n): ?>
        <option value="<?= View::e($n) ?>" <?= $sousSelection === $n ? 'selected' : '' ?>><?= View::e($n) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if (!empty($familleDispo) && !empty($sousOptions)): ?>
  <div class="form-group" style="margin-bottom:0;min-width:200px">
    <label style="font-size:12px">Famille de produit</label>
    <select name="famille_produit" onchange="this.form.submit()">
      <option value="">Toutes</option>
      <?php foreach (\App\Models\SousActivite::FAMILLES as $fam): ?>
        <option <?= $familleSelection === $fam ? 'selected' : '' ?>><?= View::e($fam) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <?php if ($filialeIdSelection || $activiteSelection): ?>
    <a href="/index.php?activite=" style="margin-left:auto;font-size:13px;font-weight:600">Réinitialiser</a>
  <?php endif; ?>
</form>

<?php if (!empty($estTransport)): ?>
<div class="dash-kpi-row" style="margin-top:18px">
  <a href="/index.php?r=demandes&statut=a_qualifier" class="dash-kpi">
    <div class="dk-icon" style="background:#DDF1F3;color:#0B7C8A"><?= Icon::svg('file-text', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $demandeCounts['a_qualifier'] ?></div><div class="dk-label">Demandes à qualifier</div><div class="dk-link">Voir les demandes <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=cotations" class="dash-kpi">
    <div class="dk-icon" style="background:#FFE3DA;color:#8F3217"><?= Icon::svg('tag', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $cotationsARelancerCount ?></div><div class="dk-label">Devis à relancer</div><div class="dk-link">Voir les devis <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="#flux" class="dash-kpi">
    <div class="dk-icon" style="background:#DFF3E8;color:#14633C"><?= Icon::svg('truck', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= count($fluxEnCours) ?></div><div class="dk-label">Transports en cours</div><div class="dk-link">Voir les flux <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="#livraisons" class="dash-kpi">
    <div class="dk-icon" style="background:#FDE5DF;color:#C8402A"><?= Icon::svg('clock', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= (int) $livraisonsEnCoursCount ?></div><div class="dk-label">Livraisons à suivre</div><div class="dk-link">Voir la liste <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
</div>
<?php elseif (!empty($estPrestation)): ?>
<div class="dash-kpi-row" style="margin-top:18px">
  <a href="/index.php?r=demandes&statut=a_qualifier" class="dash-kpi">
    <div class="dk-icon" style="background:#DDF1F3;color:#0B7C8A"><?= Icon::svg('file-text', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $demandeCounts['a_qualifier'] ?></div><div class="dk-label">Demandes à qualifier</div><div class="dk-link">Voir les demandes <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=cotations" class="dash-kpi">
    <div class="dk-icon" style="background:#FFE3DA;color:#8F3217"><?= Icon::svg('tag', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $cotationsARelancerCount ?></div><div class="dk-label">Devis à relancer</div><div class="dk-link">Voir les devis <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=dossiers&statut=actif" class="dash-kpi">
    <div class="dk-icon" style="background:#DFF3E8;color:#14633C"><?= Icon::svg('folder', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $dossierCounts['actifs'] ?></div><div class="dk-label">Prestations en cours</div><div class="dk-link">Voir les prestations <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="#livrables" class="dash-kpi">
    <div class="dk-icon" style="background:#FDE5DF;color:#C8402A"><?= Icon::svg('clock', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= count($livrablesARemettre) ?></div><div class="dk-label">Livrables à remettre</div><div class="dk-link">Voir la liste <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
</div>
<?php else: ?>
<div class="dash-kpi-row" style="margin-top:18px">
  <a href="/index.php?r=demandes&statut=a_qualifier" class="dash-kpi">
    <div class="dk-icon" style="background:#DDF1F3;color:#0B7C8A"><?= Icon::svg('file-text', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $demandeCounts['a_qualifier'] ?></div><div class="dk-label">À qualifier</div><div class="dk-link">Voir les demandes <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=demandes" class="dash-kpi">
    <div class="dk-icon" style="background:#DFF3E8;color:#14633C"><?= Icon::svg('file', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $offresAAnalyserCount ?></div><div class="dk-label">Offres à analyser</div><div class="dk-link">Voir les offres <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <a href="/index.php?r=dossiers" class="dash-kpi">
    <div class="dk-icon" style="background:#FFE3DA;color:#8F3217"><?= Icon::svg('tag', 'icon', 21) ?></div>
    <div><div class="dk-value"><?= $cotationsARelancerCount ?></div><div class="dk-label">Cotations à relancer</div><div class="dk-link">Voir les cotations <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
  <?php /* [corrigé 04/10] Reliait "En retard" au seul compteur demandeCounts['en_retard']
     (4) alors que le bloc Alertes d'en dessous affichait 6 lignes (demandes +
     commandes + cotations + offres en retard confondues) — l'écart venait du
     périmètre, pas d'un bug de calcul. Ce 4e indicateur compte désormais
     exactement la même chose que l'onglet "En retard" d'Actions prioritaires,
     et le libellé est explicite sur son périmètre élargi. */ ?>
  <a href="#actions-prioritaires" class="dash-kpi">
    <div class="dk-icon" style="background:#FDE5DF;color:#C8402A"><?= Icon::svg('clock', 'icon', 21) ?></div>
    <div><div class="dk-value" style="<?= $actionsEnRetardCount > 0 ? 'color:#A63A1F' : '' ?>"><?= $actionsEnRetardCount ?></div><div class="dk-label">Actions en retard</div><div class="dk-link">Voir les actions <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
</div>
<?php endif; ?>

<?php
$opsColors = [
  'demandes'   => ['bg' => '#DDF1F3', 'fg' => '#0B7C8A', 'icon' => 'file-text'],
  'dossiers'   => ['bg' => '#d1fae5', 'fg' => '#059669', 'icon' => 'folder'],
  'offres'     => ['bg' => '#DFF3E8', 'fg' => '#14633C', 'icon' => 'file'],
  'cotations'  => ['bg' => '#FFE3DA', 'fg' => '#8F3217', 'icon' => 'tag'],
  'commandes'  => ['bg' => '#DFF3E8', 'fg' => '#14633C', 'icon' => 'shopping-cart'],
  'livraisons' => ['bg' => '#DDF1F3', 'fg' => '#0B7C8A', 'icon' => 'truck'],
];
$opsLabels = ['demandes' => 'Demandes', 'dossiers' => 'Dossiers', 'offres' => 'Offres', 'cotations' => 'Cotations', 'commandes' => 'Commandes', 'livraisons' => 'Livraisons'];
$opsLinks = ['demandes' => 'demandes', 'dossiers' => 'dossiers&statut=actif', 'offres' => 'demandes', 'cotations' => 'dossiers', 'commandes' => 'dossiers', 'livraisons' => 'dossiers'];
$opsKeys = array_keys($opsLabels);
$graviteBorder = ['haute' => '#C8402A', 'moyenne' => '#f97316', 'basse' => '#eab308'];
?>

<?php /* [réorganisé 04/10, sur la base de la maquette de référence de Marie
   Laure] Les blocs ci-dessous sont désormais les enfants directs d'une même
   grille (.dash-grid, voir app.css), alignée sur les colonnes de la ligne
   d'indicateurs au-dessus — plus de décalage entre "blocs de droite" et
   indicateurs signalé sur ordinateur. "Suivi des opérations" est repliable
   sur téléphone et se réordonne après "Actions prioritaires" (cf. CSS),
   sans dupliquer son contenu. */ ?>
<div class="dash2" style="margin-top:18px">
<div class="dash2-left">
  <div class="card dg-actions" id="actions-prioritaires">
    <?php
    $toutes = [];
    foreach (['retard', 'aujourd_hui', 'a_venir'] as $cle) {
        foreach ($actionsPrioritaires[$cle] as $it) { $it['_cle'] = $cle; $toutes[] = $it; }
    }
    $rendreAction = function (array $it) use ($prioTypeStyle) {
        $style = $prioTypeStyle[$it['type']];
        $cle = $it['_cle'];
        $j = (int) round((strtotime($it['echeance']) - strtotime(date('Y-m-d'))) / 86400);
        if ($cle === 'retard') { $txt = 'En retard · ' . abs($j) . ' j'; $cls = 'ac-late'; }
        elseif ($cle === 'aujourd_hui') { $txt = "Aujourd'hui"; $cls = 'ac-today'; }
        else { $txt = $j <= 1 ? 'Demain' : 'Dans ' . $j . ' j'; $cls = 'ac-next'; }
        ?>
        <li class="ac-row">
          <span class="prio-type ac-ico" style="background:<?= $style['bg'] ?>;color:<?= $style['fg'] ?>"><?= Icon::svg($style['icon'], 'icon', 20) ?></span>
          <div class="ac-main">
            <div class="ac-title"><?= View::e($it['action']) ?></div>
            <div class="ac-sub"><?= View::e(trim((string) ($it['reference'] ?? ''))) ?> · <?= View::e($it['titre'] ?: $it['type_label']) ?></div>
          </div>
          <span class="ac-chip <?= $cls ?>"><?= $txt ?></span>
          <a href="<?= View::e($it['lien']) ?>" class="btn btn-sm btn-secondary"><?= View::e($it['bouton'] ?? 'Ouvrir') ?></a>
        </li>
        <?php
    };
    ?>
    <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('target', 'icon', 17) ?> À faire maintenant <?php if ($actionsEnRetardCount > 0): ?><span class="badge badge-orange"><?= $actionsEnRetardCount ?> en retard</span><?php endif; ?></span></h2>
    <?php if (empty($toutes)): ?>
      <div class="empty-state">Rien à traiter pour le moment — bien joué.</div>
    <?php else: ?>
      <ul class="ac-list"><?php foreach (array_slice($toutes, 0, 5) as $it) { $rendreAction($it); } ?></ul>
      <?php if (count($toutes) > 5): ?>
        <ul class="ac-list" id="ac-more" hidden><?php foreach (array_slice($toutes, 5) as $it) { $rendreAction($it); } ?></ul>
        <button type="button" class="prio-more-toggle" id="ac-toggle" data-more="Voir les <?= count($toutes) ?> actions" data-less="Voir moins">Voir les <?= count($toutes) ?> actions</button>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card dg-demandes">
    <?php $nbAQualifier = 0; foreach ($dernieresDemandes as $dd) { if (($dd['statut'] ?? '') === 'a_qualifier') { $nbAQualifier++; } } ?>
    <h2 style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('inbox', 'icon', 17) ?> Nouvelles demandes <?php if ($nbAQualifier > 0): ?><span class="badge badge-blue"><?= $nbAQualifier ?> à qualifier</span><?php endif; ?></span><a href="/index.php?r=demandes" style="font-size:13px;font-weight:600">Toutes les demandes</a></h2>
    <?php if (empty($dernieresDemandes)): ?>
      <div class="empty-state">Aucune demande pour le moment.</div>
    <?php else: ?>
      <ul class="feed-list nd-list">
      <?php foreach (array_slice($dernieresDemandes, 0, 4) as $d): $aQ = ($d['statut'] ?? '') === 'a_qualifier'; ?>
      <li onclick="window.location='/index.php?r=demandes/<?= $d['id'] ?>'">
        <div class="fl-body">
          <div class="fl-title"><?= View::e($d['objet']) ?></div>
          <div class="fl-sub"><?= View::e(trim((string) ($d['client_nom'] ?? '')) !== '' ? $d['client_nom'] . ' · ' : '') ?><?= View::e($d['filiale_nom'] ?? '') ?> · reçue <?= !empty($d['recue_le']) ? date('d/m/Y', strtotime($d['recue_le'])) : '—' ?></div>
        </div>
        <span class="badge <?= $statutDemandeBadges[$d['statut']][1] ?? 'badge-gray' ?>"><?= $statutDemandeBadges[$d['statut']][0] ?? ucfirst($d['statut']) ?></span>
        <a href="/index.php?r=demandes/<?= $d['id'] ?>" class="btn btn-sm btn-secondary" onclick="event.stopPropagation()"><?= $aQ ? 'Qualifier' : 'Ouvrir' ?></a>
      </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php if (!empty($estTransport)): ?>
  <div class="card" id="livraisons">
    <h2 style="display:flex;align-items:center;gap:8px"><?= Icon::svg('truck', 'icon', 17) ?> Livraisons à suivre</h2>
    <?php if (empty($livraisonsEnCours)): ?>
      <div class="empty-state">Aucune livraison en cours.</div>
    <?php else: ?>
      <ul class="ac-list">
      <?php foreach (array_slice($livraisonsEnCours, 0, 5) as $l): ?>
        <li class="ac-row">
          <div class="ac-main"><div class="ac-title"><?= View::e($l['reference']) ?> · <?= View::e($l['destination_pays'] ?: 'Destination à compléter') ?></div><div class="ac-sub"><?= View::e($l['fournisseur_nom'] ?: '—') ?></div></div>
          <span class="ac-chip ac-next"><?= $l['livraison_prevue'] ? date('d/m/Y', strtotime($l['livraison_prevue'])) : 'Date à fixer' ?></span>
          <a href="/index.php?r=dossiers/<?= (int) $l['dossier_id'] ?>/commande" class="btn btn-sm btn-secondary">Suivre</a>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<div class="dash2-right">
  <?php if (!empty($estPrestation)): ?>
  <div class="card flux-card dash2-flux" id="livrables">
    <h2>Livrables à remettre</h2>
    <?php if (empty($livrablesARemettre)): ?>
      <div class="empty-state">Aucun livrable en attente.</div>
    <?php else: ?>
    <ul class="ac-list">
      <?php foreach ($livrablesARemettre as $l): ?>
      <li class="ac-row">
        <div class="ac-main"><div class="ac-title"><?= View::e($l['dossier_objet']) ?></div><div class="ac-sub"><?= View::e($l['dossier_reference']) ?><?= !empty($l['dossier_echeance']) ? ' · échéance ' . date('d/m/Y', strtotime($l['dossier_echeance'])) : '' ?></div></div>
        <a href="/index.php?r=dossiers/<?= (int) $l['dossier_id'] ?>/commande" class="btn btn-sm btn-secondary">Renseigner</a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="card flux-card dash2-flux" id="flux">
    <h2 style="display:flex;justify-content:space-between;align-items:center"><span>Vos flux en cours</span><a href="/index.php?r=dossiers&statut=actif" style="font-size:13px;font-weight:600">Tout voir</a></h2>
    <?php if (empty($fluxEnCours)): ?>
      <div class="empty-state">Aucun flux en cours. Renseignez le trajet dans la commande d'un dossier expédié.</div>
    <?php else: ?>
    <div class="flux-grid">
    <?php foreach ($fluxEnCours as $f): $mlib = Commande::MODES_TRAJET[$f['mode']] ?? 'Maritime'; ?>
      <a class="flux-item" href="/index.php?r=dossiers/<?= (int) $f['dossier_id'] ?>/commande">
        <div class="flux-top"><span class="flux-mode flux-<?= View::e($f['mode']) ?>"><?= View::e($mlib) ?></span><span class="flux-ref"><?= View::e($f['reference']) ?></span></div>
        <div class="flux-route">
          <span class="flux-lieu"><?= View::e($f['depart'] ?: '—') ?></span>
          <span class="flux-line"><i style="left:<?= (int) $f['pct'] ?>%"></i></span>
          <span class="flux-lieu"><?= View::e($f['arrivee'] ?: '—') ?></span>
        </div>
        <div class="flux-statut <?= $f['retard'] ? 'late' : '' ?>"><?= View::e($f['statut']) ?><?php if ($f['arrivee_prevue']): ?> · arrivée prévue le <?= date('d/m/Y', strtotime($f['arrivee_prevue'])) ?><?= $f['retard'] ? ' (dépassée)' : '' ?><?php endif; ?></div>
      </a>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</div>

<?php if (!empty($dossiersEnCours)): ?>
<div class="card dos-encours" style="margin-top:20px">
  <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('folder', 'icon', 17) ?> Dossiers en cours</span><a href="/index.php?r=dossiers" style="font-size:13px;font-weight:600">Tous les dossiers</a></h2>
  <div style="overflow-x:auto">
  <table class="table-soft" style="width:100%;min-width:640px">
    <thead><tr><th>Dossier</th><th>Objet et client</th><th>Étape</th><th style="width:26%">Avancement</th><th>Échéance</th></tr></thead>
    <tbody>
    <?php foreach ($dossiersEnCours as $dc):
        $pct = \App\Models\Dossier::progression((string) $dc['etape']);
        $enRetard = !empty($dc['echeance']) && $dc['echeance'] < date('Y-m-d');
    ?>
      <tr onclick="location.href='/index.php?r=dossiers/<?= (int) $dc['id'] ?>'" style="cursor:pointer">
        <td><a href="/index.php?r=dossiers/<?= (int) $dc['id'] ?>"><strong><?= View::e($dc['reference']) ?></strong></a></td>
        <td><div style="font-weight:600"><?= View::e($dc['objet']) ?></div><div style="font-size:12.5px;color:#6b7280"><?= View::e((string) ($dc['client_nom'] ?? '')) ?></div></td>
        <td><span class="badge badge-blue"><?= View::e(\App\Models\Dossier::ETAPES_LABELS[$dc['etape']] ?? $dc['etape']) ?></span></td>
        <td><div style="display:flex;align-items:center;gap:10px"><div class="bar-soft<?= $enRetard ? ' late' : '' ?>" style="flex:1"><i style="width:<?= (int) $pct ?>%"></i></div><strong style="font-size:12.5px;width:36px;text-align:right"><?= (int) $pct ?> %</strong></div></td>
        <td><?= !empty($dc['echeance']) ? '<span class="badge ' . ($enRetard ? 'badge-orange' : 'badge-gray') . '">' . ($enRetard ? 'En retard' : date('d/m/Y', strtotime($dc['echeance']))) . '</span>' : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>
<script>
(function () {
  var t = document.getElementById('ac-toggle'), m = document.getElementById('ac-more');
  if (!t || !m) { return; }
  t.addEventListener('click', function () { m.hidden = !m.hidden; t.textContent = m.hidden ? t.dataset.more : t.dataset.less; });
})();
</script>
