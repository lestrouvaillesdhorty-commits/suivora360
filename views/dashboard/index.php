<?php use App\Core\View; ?>
<h1>Tableau de bord</h1>
<div class="subtitle">Vue d'ensemble de vos opérations</div>

<div class="grid-3">
  <div class="stat-tile">
    <div class="value"><?= $demandeCounts['a_qualifier'] ?></div>
    <div class="label">Demandes à qualifier</div>
  </div>
  <div class="stat-tile">
    <div class="value"><?= $dossierCounts['actifs'] ?></div>
    <div class="label">Dossiers actifs</div>
  </div>
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
