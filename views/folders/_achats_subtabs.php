<?php
// [ajouté 06/10, étape 2 du découpage Dossiers] Navigation des 3
// sous-onglets de "Achats et offres" (maquette AchatsConsultations.dc.html
// / AchatsOffres.dc.html / AchatsComparaison.dc.html), partagée entre
// _tab_achats.php (sous-onglets Consultations/Offres, qui restent sur la
// fiche Dossier) et views/comparateur/index.php (sous-onglet Comparaison,
// route restée séparée — accès direct depuis le menu, décision du 29/09).
// Attend : $dossier, $sousOngletAchats ('consultations'|'offres'|
// 'comparaison'), $nbOffres (compte affiché à côté de "Offres reçues").
?>
<div class="sub-tabs">
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=achats&sous=consultations" class="sub-tab <?= $sousOngletAchats === 'consultations' ? 'active' : '' ?>">Consultations</a>
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=achats&sous=offres" class="sub-tab <?= $sousOngletAchats === 'offres' ? 'active' : '' ?>">Offres reçues <span style="opacity:.7">(<?= (int) $nbOffres ?>)</span></a>
  <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur" class="sub-tab <?= $sousOngletAchats === 'comparaison' ? 'active' : '' ?>">Comparaison</a>
</div>
