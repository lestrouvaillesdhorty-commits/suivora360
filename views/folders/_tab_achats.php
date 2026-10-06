<?php
// [ajouté 06/10, report de la maquette Dossiers ; restructuré 06/10, étape
// 2 du découpage] "Achats et offres" se scinde en 3 sous-onglets conformes
// à la maquette (Consultations / Offres reçues / Comparaison) : les deux
// premiers sont de simples listes affichées ici (voir
// _tab_achats_consultations.php / _tab_achats_offres.php), le troisième
// pointe vers le Comparateur déjà existant et testé
// (/dossiers/{id}/comparateur) plutôt que d'être dupliqué — voir
// _achats_subtabs.php pour la navigation commune aux 3.
$nbOffres = count($offres);
?>
<?php include __DIR__ . '/_achats_subtabs.php'; ?>

<?php if ($sousOngletAchats === 'offres'): ?>
  <?php include __DIR__ . '/_tab_achats_offres.php'; ?>
<?php else: ?>
  <?php include __DIR__ . '/_tab_achats_consultations.php'; ?>
<?php endif; ?>
