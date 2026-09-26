<?php use App\Core\View; ?>
<h1>Tableau de bord</h1>
<div class="subtitle">Vue d'ensemble de vos opérations</div>

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

<div class="card">
  <h2>Actions rapides</h2>
  <a href="/index.php?r=demandes/nouvelle" class="btn">+ Nouvelle demande</a>
  <a href="/index.php?r=demandes" class="btn btn-secondary" style="margin-left:8px">Voir les demandes</a>
  <a href="/index.php?r=dossiers" class="btn btn-secondary" style="margin-left:8px">Voir les dossiers</a>
</div>
