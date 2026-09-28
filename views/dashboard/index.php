<?php
use App\Core\Auth;
use App\Core\View;
use App\Models\Commande;
use App\Models\Demande;
?>
<h1>Tableau de bord</h1>
<div class="subtitle">Vue d'ensemble de vos opérations — pour l'analyse détaillée par activité/responsable, voir <a href="/index.php?r=pilotage">Pilotage</a><?php if (!Auth::canVoirPilotage()): ?> (réservé à certains rôles)<?php endif; ?>.</div>

<div class="grid-3">
  <a href="/index.php?r=demandes&statut=a_qualifier" class="stat-tile stat-tile-link">
    <div class="value"><?= $demandeCounts['a_qualifier'] ?></div>
    <div class="label">Demandes à qualifier</div>
  </a>
  <a href="/index.php?r=demandes&statut=en_retard" class="stat-tile stat-tile-link">
    <div class="value" style="<?= $demandeCounts['en_retard'] > 0 ? 'color:#991b1b' : '' ?>"><?= $demandeCounts['en_retard'] ?></div>
    <div class="label">Demandes en retard</div>
  </a>
  <a href="/index.php?r=dossiers&statut=actif" class="stat-tile stat-tile-link">
    <div class="value"><?= $dossierCounts['actifs'] ?></div>
    <div class="label">Dossiers actifs</div>
  </a>
  <a href="/index.php?r=dossiers&statut=en_retard" class="stat-tile stat-tile-link">
    <div class="value" style="<?= $dossierCounts['en_retard'] > 0 ? 'color:#991b1b' : '' ?>"><?= $dossierCounts['en_retard'] ?></div>
    <div class="label">Dossiers en retard</div>
  </a>
  <div class="stat-tile">
    <div class="value"><?= count($filiales) ?></div>
    <div class="label">Filiale<?= count($filiales) > 1 ? 's' : '' ?> visible<?= count($filiales) > 1 ? 's' : '' ?></div>
  </div>
</div>

<div class="grid-3">
  <div class="stat-tile">
    <div class="value"><?= $offresAAnalyserCount ?></div>
    <div class="label">Offres à analyser (Achats)</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= $cotationsARelancerCount ?></div>
    <div class="label">Cotations envoyées, en attente (Commercial)</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= $commandesEnCoursCount ?></div>
    <div class="label">Commandes en cours (Exécution)</div>
  </div>
</div>

<div class="card">
  <h2>Actions rapides</h2>
  <a href="/index.php?r=demandes/nouvelle" class="btn">+ Nouvelle demande</a>
  <a href="/index.php?r=demandes" class="btn btn-secondary" style="margin-left:8px">Voir les demandes</a>
  <a href="/index.php?r=dossiers" class="btn btn-secondary" style="margin-left:8px">Voir les dossiers</a>
</div>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Dernières demandes</h2>
      <?php if (empty($dernieresDemandes)): ?>
        <div class="empty-state">Aucune demande pour le moment.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Référence</th><th>Objet / Expéditeur</th><th>Priorité</th><th>Reçue le</th></tr></thead>
          <tbody>
            <?php foreach ($dernieresDemandes as $d): ?>
            <tr onclick="window.location='/index.php?r=demandes/<?= $d['id'] ?>'" style="cursor:pointer">
              <td><?= View::e($d['reference']) ?></td>
              <td><strong><?= View::e($d['objet']) ?></strong><br><span style="color:#888"><?= View::e($d['expediteur_nom']) ?></span></td>
              <td><span class="badge <?= Demande::PRIORITE_BADGES[$d['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$d['priorite']] ?? ucfirst($d['priorite']) ?></span></td>
              <td><?= $d['recue_le'] ? date('d/m/Y', strtotime($d['recue_le'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Offres reçues à analyser</h2>
      <?php if (empty($offresAAnalyser)): ?>
        <div class="empty-state">Aucune offre en attente d'analyse.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Dossier</th><th>Fournisseur</th><th>Montant</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($offresAAnalyser as $o): ?>
            <tr onclick="window.location='/index.php?r=dossiers/<?= $o['dossier_id'] ?>/comparateur'" style="cursor:pointer">
              <td><?= View::e($o['dossier_reference']) ?><br><span style="color:#888"><?= View::e($o['dossier_objet']) ?></span></td>
              <td><?= View::e($o['fournisseur_nom']) ?></td>
              <td><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?> <?= View::e($o['devise']) ?></td>
              <td><a href="/index.php?r=dossiers/<?= $o['dossier_id'] ?>/comparateur" class="btn btn-sm btn-secondary">Comparateur</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Cotations en attente de réponse</h2>
      <?php if (empty($cotationsARelancer)): ?>
        <div class="empty-state">Aucune cotation en attente.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Dossier</th><th>Client</th><th>Montant</th><th>Validité</th></tr></thead>
          <tbody>
            <?php foreach ($cotationsARelancer as $c): ?>
            <tr onclick="window.location='/index.php?r=cotations/<?= $c['id'] ?>'" style="cursor:pointer">
              <td><?= View::e($c['dossier_reference']) ?></td>
              <td><?= View::e($c['client_nom']) ?></td>
              <td><?= number_format((float) $c['montant_total'], 2, ',', ' ') ?> <?= View::e($c['devise']) ?></td>
              <td>
                <?= $c['validite_devis'] ? date('d/m/Y', strtotime($c['validite_devis'])) : '—' ?>
                <?php if ($c['validite_devis'] && strtotime($c['validite_devis']) < strtotime('today')): ?> <span class="badge badge-red">Expirée</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Commandes en cours</h2>
      <?php if (empty($commandesEnCours)): ?>
        <div class="empty-state">Aucune commande en cours.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Dossier</th><th>Étape</th><th>Prochaine action</th><th>Relance</th></tr></thead>
          <tbody>
            <?php foreach ($commandesEnCours as $c): ?>
            <?php $enRetard = Commande::estEnRetard($c); ?>
            <tr onclick="window.location='/index.php?r=dossiers/<?= $c['dossier_id'] ?>/commande'" style="cursor:pointer">
              <td><?= View::e($c['dossier_reference']) ?><br><span style="color:#888"><?= View::e($c['dossier_objet']) ?></span></td>
              <td><span class="badge badge-blue"><?= Commande::libelleStatutEtape($c['etape'], 'en_cours') ?></span></td>
              <td><?= View::e($c['prochaine_action'] ?: '—') ?></td>
              <td>
                <?= $c['date_relance'] ? date('d/m/Y', strtotime($c['date_relance'])) : '—' ?>
                <?php if ($enRetard): ?> <span class="badge badge-red">En retard</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Échéances à venir <span style="font-weight:400;font-size:12px;color:#888">(7 jours)</span></h2>
      <?php if (empty($echeancesAVenir)): ?>
        <div class="empty-state">Rien à venir dans les 7 prochains jours.</div>
      <?php else: ?>
        <?php foreach ($echeancesAVenir as $e): ?>
        <div class="info-row" style="cursor:pointer" onclick="window.location='/index.php?r=<?= $e['type'] === 'demande' ? 'demandes' : 'dossiers' ?>/<?= $e['id'] ?>'">
          <div>
            <div><strong><?= View::e($e['reference']) ?></strong> <span class="badge badge-gray" style="text-transform:capitalize"><?= $e['type'] ?></span></div>
            <div style="color:#888;font-size:13px"><?= View::e($e['objet']) ?></div>
          </div>
          <div class="label"><?= date('d/m/Y', strtotime($e['echeance'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
