<?php
use App\Core\View;
use App\Models\Dossier;

// [ajouté 06/10, report de la maquette Dossiers ; extrait en partial
// commun le 06/10, étape 2 du découpage, pour que le Comparateur
// (views/comparateur/index.php, route restée séparée — accès direct
// depuis le menu, décision du 29/09) puisse réutiliser exactement la même
// en-tête (lien retour, titre, badges, bandeau de méta-infos, onglets,
// phase-stepper) plutôt que de la dupliquer : le sous-onglet "Comparaison"
// de "Achats et offres" pointe simplement vers cette route déjà existante
// et testée, pas vers une copie de son affichage.
//
// Attend en entrée : $dossier, $filiale, $demande, $onglet (le code de
// l'onglet actif — "achats" pour le Comparateur).
$enRetard = $dossier['statut'] === 'actif' && $dossier['echeance'] && strtotime($dossier['echeance']) < strtotime('today');
$typeDossier = $dossier['type_dossier'] ?? 'autre';
$libelleFournisseur = Dossier::libelleFournisseur($typeDossier);
$libelleFournisseurMin = mb_strtolower($libelleFournisseur);

$badgeTypeDossier = match ($typeDossier) {
    'achat_sourcing' => 'badge-blue',
    'transport_logistique' => 'badge-purple',
    'prestation_entreprise' => 'badge-green',
    default => 'badge-blue',
};
?>
<a href="/index.php?r=dossiers" style="font-size:13px;color:#666">&larr; Retour aux dossiers</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-top:8px">
  <div>
    <h1 style="margin-bottom:3px"><?= View::e($dossier['objet']) ?></h1>
    <div class="subtitle">
      <?= View::e($dossier['reference']) ?> · <?= View::e($filiale['nom'] ?? '') ?>
      <?php if ($demande): ?>
        · <a href="/index.php?r=demandes/<?= $demande['id'] ?>">Voir la demande d'origine (<?= View::e($demande['reference']) ?>)</a>
      <?php endif; ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <span class="badge <?= $badgeTypeDossier ?>"><?= View::e(Dossier::TYPES_LABELS[$typeDossier] ?? ucfirst($typeDossier)) ?></span>
    <?php if ($typeDossier === 'prestation_entreprise' && !empty($dossier['type_prestation'])): ?>
      <span class="badge badge-green"><?= View::e(Dossier::TYPES_PRESTATION_LABELS[$dossier['type_prestation']] ?? ucfirst($dossier['type_prestation'])) ?></span>
    <?php endif; ?>
    <span class="badge <?= $dossier['statut'] === 'actif' ? 'badge-green' : 'badge-red' ?>"><?= ucfirst($dossier['statut']) ?></span>
    <?php if ($enRetard): ?><span class="badge badge-red">En retard</span><?php endif; ?>
  </div>
</div>

<div class="fiche-header-meta">
  <div class="fhm-item"><div class="fhm-label">Activité</div><div class="fhm-value"><?= View::e($demande['activite'] ?? '—') ?></div></div>
  <div class="fhm-item"><div class="fhm-label">Responsable</div><div class="fhm-value"><?= View::e(\App\Models\Utilisateur::nameOf($dossier['responsable_id'])) ?></div></div>
  <div class="fhm-item"><div class="fhm-label">Priorité</div><div class="fhm-value"><?= \App\Models\Demande::PRIORITES[$dossier['priorite']] ?? ucfirst((string) $dossier['priorite']) ?></div></div>
  <div class="fhm-item"><div class="fhm-label">Échéance client</div><div class="fhm-value"><?= !empty($demande['date_souhaitee_client']) ? date('d/m/Y', strtotime($demande['date_souhaitee_client'])) : '—' ?></div></div>
  <div class="fhm-item"><div class="fhm-label">Échéance interne</div><div class="fhm-value"><?= $dossier['echeance'] ? date('d/m/Y', strtotime($dossier['echeance'])) : '—' ?></div></div>
</div>

<div class="dos-tabs">
  <?php foreach (['synthese' => 'Synthèse', 'besoin' => 'Besoin', 'achats' => 'Achats et offres', 'cotations' => 'Cotations client', 'execution' => 'Exécution', 'documents' => 'Documents', 'equipe' => 'Équipe et historique'] as $code => $label): ?>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>&onglet=<?= $code ?>" class="dos-tab <?= $onglet === $code ? 'active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php
$progressionDossier = Dossier::progression($dossier['etape']);
$etapesStatut = Dossier::etapesAvecStatut($dossier['etape']);
?>
<div class="phase-stepper">
  <?php foreach ($etapesStatut as $e): ?>
    <div class="phase-step <?= $e['etat'] === 'fait' ? 'done' : ($e['etat'] === 'en_cours' ? 'current' : '') ?>">
      <?php if ($e['code'] !== $etapesStatut[0]['code']): ?><div class="ps-line"></div><?php endif; ?>
      <div class="ps-dot"><?= $e['etat'] === 'fait' ? '✓' : (array_search($e['code'], Dossier::ETAPES, true) + 1) ?></div>
      <div class="ps-label"><?= View::e($e['label']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
