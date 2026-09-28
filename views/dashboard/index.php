<?php
use App\Core\Auth;
use App\Core\Icon;
use App\Core\View;
use App\Models\Commande;
use App\Models\Demande;

if (!function_exists('strftime_fr')) {
    function strftime_fr(string $date): string {
        $mois = [1=>'janv.',2=>'févr.',3=>'mars',4=>'avr.',5=>'mai',6=>'juin',7=>'juil.',8=>'août',9=>'sept.',10=>'oct.',11=>'nov.',12=>'déc.'];
        return $mois[(int) date('n', strtotime($date))];
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

<div class="dash-kpi-row" style="margin-top:18px">
  <a href="/index.php?r=demandes&statut=a_qualifier" class="dash-kpi">
    <div class="dk-icon" style="background:#EEEBFF;color:#5036F5"><?= Icon::svg('file-text', 'icon', 21) ?></div>
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
  <a href="/index.php?r=demandes&statut=en_retard" class="dash-kpi">
    <div class="dk-icon" style="background:#fee2e2;color:#dc2626"><?= Icon::svg('clock', 'icon', 21) ?></div>
    <div><div class="dk-value" style="<?= $demandeCounts['en_retard'] > 0 ? 'color:#991b1b' : '' ?>"><?= $demandeCounts['en_retard'] ?></div><div class="dk-label">En retard</div><div class="dk-link">Voir les demandes <?= Icon::svg('arrow-right', 'icon', 13) ?></div></div>
  </a>
</div>

<?php
$opsColors = [
  'demandes'   => ['bg' => '#EEEBFF', 'fg' => '#5036F5', 'icon' => 'file-text'],
  'dossiers'   => ['bg' => '#d1fae5', 'fg' => '#059669', 'icon' => 'folder'],
  'offres'     => ['bg' => '#dbeafe', 'fg' => '#2563eb', 'icon' => 'file'],
  'cotations'  => ['bg' => '#ffedd5', 'fg' => '#c2410c', 'icon' => 'tag'],
  'commandes'  => ['bg' => '#dbeafe', 'fg' => '#2563eb', 'icon' => 'shopping-cart'],
  'livraisons' => ['bg' => '#ede9fe', 'fg' => '#7c3aed', 'icon' => 'truck'],
];
$opsLabels = ['demandes' => 'Demandes', 'dossiers' => 'Dossiers', 'offres' => 'Offres', 'cotations' => 'Cotations', 'commandes' => 'Commandes', 'livraisons' => 'Livraisons'];
$opsLinks = ['demandes' => 'demandes', 'dossiers' => 'dossiers&statut=actif', 'offres' => 'demandes', 'cotations' => 'dossiers', 'commandes' => 'dossiers', 'livraisons' => 'dossiers'];
$opsKeys = array_keys($opsLabels);
?>
<div class="ops-row">
  <h2><?= Icon::svg('trending-up', 'icon', 17) ?> Suivi des opérations</h2>
  <div class="ops-items">
    <?php foreach ($opsKeys as $i => $key): ?>
      <?php if ($i > 0): ?><span class="ops-sep"><?= Icon::svg('chevron-right') ?></span><?php endif; ?>
      <a href="/index.php?r=<?= $opsLinks[$key] ?>" class="ops-item">
        <span class="oi-icon" style="background:<?= $opsColors[$key]['bg'] ?>;color:<?= $opsColors[$key]['fg'] ?>"><?= Icon::svg($opsColors[$key]['icon'], 'icon', 15) ?></span>
        <span><span class="oi-n"><?= $suivi[$key] ?></span><br><span class="oi-label"><?= $opsLabels[$key] ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="detail-grid">
  <div>
    <div class="card alert-card">
      <h2 style="display:flex;align-items:center;gap:8px;color:#b91c1c"><?= Icon::svg('alert-triangle', 'icon', 17) ?> Alertes — en attente depuis trop longtemps</h2>
      <?php if (empty($alertes)): ?>
        <div class="empty-state">Rien ne traîne au-delà des seuils habituels — bien joué.</div>
      <?php else: ?>
        <table class="responsive-cards">
          <thead><tr><th>Type</th><th>Dossier</th><th>Depuis</th><th class="col-arrow"></th></tr></thead>
          <tbody>
            <?php foreach ($alertes as $a): ?>
            <tr class="row-clickable" onclick="window.location='<?= View::e($a['lien']) ?>'">
              <td data-label="Type"><?= View::e($a['type']) ?></td>
              <td data-label="Dossier"><?= View::e($a['objet']) ?></td>
              <td data-label="Depuis"><span class="badge badge-red"><?= $a['jours'] ?> j</span></td>
              <td class="col-arrow"><?= Icon::rowArrow() ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('list', 'icon', 17) ?> Mes priorités</span> <span style="font-size:12px;font-weight:400"><a href="/index.php?r=dossiers">Voir toutes les priorités <?= Icon::svg('arrow-right', 'icon', 12) ?></a></span></h2>
      <?php if (empty($mesPriorites)): ?>
        <div class="empty-state">Aucune priorité en cours.</div>
      <?php else: ?>
        <table class="responsive-cards">
          <thead><tr><th>Dossier</th><th>Prochaine action</th><th>Échéance</th><th>Responsable</th><th class="col-arrow"></th></tr></thead>
          <tbody>
            <?php foreach ($mesPriorites as $c): ?>
            <?php $enRetard = Commande::estEnRetard($c); ?>
            <tr class="row-clickable" onclick="window.location='/index.php?r=dossiers/<?= $c['dossier_id'] ?>/commande'">
              <td data-label="Dossier">
                <div class="dossier-cell">
                  <span class="thumb-icon"><?= Icon::svg('package', 'icon', 18) ?></span>
                  <span><strong><?= View::e($c['dossier_objet']) ?></strong><br><span style="color:#888;font-size:12px"><?= View::e($c['dossier_reference']) ?></span></span>
                </div>
              </td>
              <td data-label="Prochaine action"><span style="display:inline-flex;align-items:center;gap:7px"><span class="tl-dot" style="display:inline-block;width:7px;height:7px;border-radius:999px;background:<?= $enRetard ? '#ef4444' : '#5036F5' ?>"></span><?= View::e($c['prochaine_action'] ?: Commande::libelleStatutEtape($c['etape'], 'en_cours')) ?></span></td>
              <td data-label="Échéance"><span style="display:inline-flex;align-items:center;gap:5px;color:<?= $enRetard ? '#dc2626' : 'inherit' ?>"><?= Icon::svg('calendar', 'icon', 13) ?> <?= $c['date_relance'] ? date('d/m/Y', strtotime($c['date_relance'])) : '—' ?></span><?php if ($enRetard): ?> <span class="badge badge-red">Retard</span><?php endif; ?></td>
              <td data-label="Responsable"><?= !empty($c['responsable_id']) ? View::e(\App\Models\Utilisateur::nameOf((int) $c['responsable_id'])) : '—' ?></td>
              <td class="col-arrow"><?= Icon::rowArrow() ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('inbox', 'icon', 17) ?> Dernières demandes</span> <span style="font-size:12px;font-weight:400"><a href="/index.php?r=demandes">Voir toutes les demandes <?= Icon::svg('arrow-right', 'icon', 12) ?></a></span></h2>
      <?php if (empty($dernieresDemandes)): ?>
        <div class="empty-state">Aucune demande pour le moment.</div>
      <?php else: ?>
        <table class="responsive-cards">
          <thead><tr><th>Référence</th><th>Objet</th><th>Date</th><th>Statut</th><th class="col-arrow"></th></tr></thead>
          <tbody>
            <?php foreach ($dernieresDemandes as $d): ?>
            <tr class="row-clickable" onclick="window.location='/index.php?r=demandes/<?= $d['id'] ?>'">
              <td data-label="Référence"><?= View::e($d['reference']) ?></td>
              <td data-label="Objet"><strong><?= View::e($d['objet']) ?></strong><br><span style="color:#888"><?= View::e($d['expediteur_nom']) ?></span></td>
              <td data-label="Date"><?= $d['recue_le'] ? date('d/m/Y', strtotime($d['recue_le'])) : '—' ?></td>
              <td data-label="Statut"><span class="badge <?= Demande::PRIORITE_BADGES[$d['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$d['priorite']] ?? ucfirst($d['priorite']) ?></span></td>
              <td class="col-arrow"><?= Icon::rowArrow() ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2 style="display:flex;align-items:center;gap:8px"><?= Icon::svg('truck', 'icon', 17) ?> Livraisons à suivre</h2>
      <?php if (empty($livraisonsEnCours)): ?>
        <div class="empty-state">Aucune livraison en cours.</div>
      <?php else: ?>
        <table class="responsive-cards">
          <thead><tr><th>Commande</th><th>Fournisseur</th><th>Statut</th><th>Destination</th><th>Date prévue</th><th class="col-arrow"></th></tr></thead>
          <tbody>
            <?php foreach ($livraisonsEnCours as $l): ?>
            <tr class="row-clickable" onclick="window.location='/index.php?r=dossiers/<?= $l['dossier_id'] ?>/commande'">
              <td data-label="Commande"><?= View::e($l['reference']) ?><br><span style="color:#888;font-size:12px"><?= View::e($l['dossier_reference']) ?></span></td>
              <td data-label="Fournisseur"><?= View::e($l['fournisseur_nom'] ?: '—') ?></td>
              <td data-label="Statut"><span class="badge badge-blue"><?= \App\Models\Commande::libelleStatutEtape('livraison', $l['livraison_statut'] ?? 'a_faire') ?></span></td>
              <td data-label="Destination"><?= View::e($l['destination_pays'] ?: 'Non renseigné') ?></td>
              <td data-label="Date prévue"><?= $l['livraison_prevue'] ? date('d/m/Y', strtotime($l['livraison_prevue'])) : 'Non renseignée' ?></td>
              <td class="col-arrow"><?= Icon::rowArrow() ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2 style="display:flex;justify-content:space-between;align-items:center"><span style="display:flex;align-items:center;gap:8px"><?= Icon::svg('calendar', 'icon', 17) ?> Échéances à venir</span> <span style="font-size:12px;font-weight:400;color:#888">(7 jours)</span></h2>
      <?php if (empty($echeancesAVenir)): ?>
        <div class="empty-state">Rien à venir dans les 7 prochains jours.</div>
      <?php else: ?>
        <ul class="timeline">
        <?php foreach ($echeancesAVenir as $e): ?>
        <li onclick="window.location='/index.php?r=<?= $e['type'] === 'demande' ? 'demandes' : 'dossiers' ?>/<?= $e['id'] ?>'">
          <div class="tl-date"><div class="d"><?= date('d', strtotime($e['echeance'])) ?></div><div class="m"><?= strftime_fr($e['echeance']) ?></div></div>
          <div class="tl-dot" style="background:<?= $e['type'] === 'demande' ? '#5036F5' : '#10b981' ?>"></div>
          <div class="tl-body">
            <div class="tl-title"><?= View::e($e['objet']) ?></div>
            <div class="tl-sub"><?= View::e($e['reference']) ?> <span class="badge badge-gray" style="text-transform:capitalize"><?= $e['type'] ?></span></div>
          </div>
          <?= Icon::rowArrow() ?>
        </li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
