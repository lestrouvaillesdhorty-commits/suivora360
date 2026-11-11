<?php use App\Core\View;
$fid = !empty($filialeId) ? '&filiale_id=' . (int) $filialeId : '';
$cartes = [
    ['parametres/entreprise', 'Mon entreprise', "Logo, adresse, conditions et signature qui figurent sur vos devis."],
    ['parametres/calcul', 'Calcul des prix', "Taux de conversion, majoration, TVA, assurance, dédouanement, poids volumétrique et droit du Commercial sur la marge."],
    ['parametres/listes', 'Sous-activités et catégories de fournisseur', "Les listes qui alimentent les formulaires de demande et de fournisseur, et les motifs de perte."],
    ['parametres/ia', "Lectures par l'IA", "Compteur du mois et plafond de lectures, par filiale."],
];
?>
<h1>Paramètres</h1>
<div class="subtitle">Les réglages de votre entreprise, par sujet. Les utilisateurs, filiales et la sécurité sont dans le menu Administration.</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:16px">
<?php foreach ($cartes as [$route, $titre, $texte]): ?>
  <a href="/index.php?r=<?= $route . $fid ?>" class="card" style="display:block;text-decoration:none;color:inherit;margin:0">
    <div style="font-weight:700;font-size:16px;color:#085560;margin-bottom:6px"><?= View::e($titre) ?></div>
    <div style="font-size:13px;color:#666;line-height:1.45"><?= View::e($texte) ?></div>
  </a>
<?php endforeach; ?>
</div>
