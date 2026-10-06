<?php
// [ajouté 06/10, report de la maquette Dossiers] Coquille commune aux 7
// onglets (Synthèse / Besoin / Achats et offres / Cotations client /
// Exécution / Documents / Équipe et historique) — en-tête, bandeau de
// méta-infos, navigation à onglets, puis le contenu de l'onglet actif
// (voir DossierController::show(), qui calcule $onglet et charge toutes
// les données nécessaires une seule fois, quel que soit l'onglet affiché).
// [extrait en partial le 06/10, étape 2] L'en-tête elle-même (lien
// retour/titre/badges/méta/onglets/stepper) vit maintenant dans
// _dossier_header.php, réutilisée telle quelle par le Comparateur.
?>
<?php include __DIR__ . '/_dossier_header.php'; ?>

<?php include __DIR__ . '/_tab_' . $onglet . '.php'; ?>
