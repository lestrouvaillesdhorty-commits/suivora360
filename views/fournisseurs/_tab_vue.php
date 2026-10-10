<?php
use App\Core\View;
use App\Models\Client;
use App\Models\ConsultationFournisseur;
use App\Models\Fournisseur;
use App\Models\FournisseurPieceJointe;

$ligne = fn(string $lib, ?string $v) => '<div class="info-row"><span class="label">' . View::e($lib) . '</span><span>' . (($v !== null && $v !== '') ? View::e($v) : '—') . '</span></div>';
$adresseComplete = trim(implode(', ', array_filter([$fournisseur['adresse'] ?? '', trim(($fournisseur['code_postal'] ?? '') . ' ' . ($fournisseur['ville'] ?? '')), $fournisseur['pays'] ?? ''])));
$contactPrincipal = trim(($fournisseur['contact_prenom'] ?? '') . ' ' . ($fournisseur['contact_nom'] ?? ''));
$specs = Fournisseur::specialitesDe($fournisseur);
$activites = array_filter(array_map('trim', explode(',', (string) ($fournisseur['activites'] ?? ''))));
$devises = array_filter(array_map('trim', explode(',', (string) ($fournisseur['devises_proposees'] ?? ''))));
$incoterms = array_filter(array_map('trim', explode(',', (string) ($fournisseur['incoterms_pratiques'] ?? ''))));
$noteGlobale = Fournisseur::noteGlobale($fournisseur);
$nbNotes = count(array_filter(array_keys(Fournisseur::CRITERES_NOTE), fn($c) => isset($fournisseur[$c]) && $fournisseur[$c] !== null && $fournisseur[$c] !== ''));
$aujourdhui = date('Y-m-d');

// Dossiers liés (consultations + offre retenue), sans doublon.
$dossiersLies = [];
foreach ($consultations as $c) { $dossiersLies[$c['dossier_id']] = ['id' => $c['dossier_id'], 'reference' => $c['dossier_reference'], 'info' => 'Consultation ' . mb_strtolower(ConsultationFournisseur::STATUTS[$c['statut']] ?? $c['statut'])]; }
foreach ($commandes as $c) { $dossiersLies[$c['dossier_id']] = ['id' => $c['dossier_id'], 'reference' => $c['dossier_reference'], 'info' => 'Offre retenue']; }

// Prochaines actions datées.
$actions = [];
if (!empty($fournisseur['reexamen_le'])) {
    $actions[] = ['date' => $fournisseur['reexamen_le'], 'texte' => 'Réexamen de la qualification', 'lien' => $base . '&onglet=evaluation'];
}
foreach ($consultations as $c) {
    if (in_array($c['statut'], ['envoyee', 'relance'], true) && !empty($c['echeance_reponse'])) {
        $actions[] = ['date' => $c['echeance_reponse'], 'texte' => 'Réponse attendue — ' . $c['reference'], 'lien' => '/index.php?r=consultations/' . (int) $c['id']];
    }
}
foreach ($alertesDocuments as $d) {
    $actions[] = ['date' => $d['expire_le'], 'texte' => 'Renouveler : ' . (FournisseurPieceJointe::CATEGORIES[$d['categorie']] ?? $d['categorie']) . ' (' . $d['nom_original'] . ')', 'lien' => $base . '&onglet=documents'];
}
usort($actions, fn($a, $b) => strcmp($a['date'], $b['date']));
$actions = array_slice($actions, 0, 5);
$nbConsultEnCours = count(array_filter($consultations, fn($c) => in_array($c['statut'], ['envoyee', 'relance'], true)));
?>
<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Coordonnées</h2>
      <?= $ligne('Contact principal', $contactPrincipal) ?>
      <?= $ligne('Fonction', $fournisseur['fonction_contact'] ?? '') ?>
      <?= $ligne('E-mail', $fournisseur['email'] ?? '') ?>
      <?= $ligne('Téléphone', $fournisseur['telephone'] ?? '') ?>
      <?= $ligne('Adresse', $adresseComplete) ?>
      <div class="info-row"><span class="label">Site internet</span><span><?php if (!empty($fournisseur['site_web'])): ?><a href="<?= View::e($fournisseur['site_web']) ?>" target="_blank" rel="noopener"><?= View::e($fournisseur['site_web']) ?></a><?php else: ?>—<?php endif; ?></span></div>
      <?= $ligne('Immatriculation', $fournisseur['siret'] ?? '') ?>
      <?= $ligne('Identifiant fiscal / TVA', $fournisseur['tva'] ?? '') ?>
      <div style="margin-top:10px"><?php $bcEmail = $fournisseur['email'] ?? ''; $bcTel = $fournisseur['telephone'] ?? ''; include __DIR__ . '/../clients/_boutons_contact.php'; ?></div>
    </div>

    <div class="card">
      <h2>Spécialités et conditions indicatives</h2>
      <?= $ligne('Spécialités', implode(', ', $specs)) ?>
      <?= $ligne('Activités couvertes', implode(', ', $activites)) ?>
      <?= $ligne('Marques', $fournisseur['marques'] ?? '') ?>
      <?= $ligne('Devises proposées', implode(', ', $devises)) ?>
      <?= $ligne('Devise préférentielle', $fournisseur['devise'] ?? '') ?>
      <?= $ligne('Conditions de paiement', Client::CONDITIONS_PAIEMENT[$fournisseur['conditions_paiement'] ?? ''] ?? '') ?>
      <?= $ligne('Délai indicatif', $fournisseur['delai_indicatif'] ?? '') ?>
      <?= $ligne('Zones desservies', $fournisseur['pays_desservis'] ?? '') ?>
      <?= $ligne('Minimum de commande', $fournisseur['quantite_min'] ?? '') ?>
      <?= $ligne('Incoterms pratiqués', implode(', ', $incoterms)) ?>
      <?= $ligne('Conditions de livraison', $fournisseur['conditions_livraison'] ?? '') ?>
      <div style="font-size:12px;color:#888;margin-top:8px">Informations indicatives : une offre ou une commande conserve ses propres conditions.</div>
    </div>

    <?php if ($noteGlobale !== null): ?>
    <div class="card">
      <h2>Appréciations — <?= $noteGlobale ?>/5</h2>
      <?php foreach (Fournisseur::CRITERES_NOTE as $champ => $label): if (($fournisseur[$champ] ?? null) === null || $fournisseur[$champ] === '') { continue; } ?>
        <div class="info-row"><span class="label"><?= View::e($label) ?></span><span><?= (int) $fournisseur[$champ] ?>/5</span></div>
      <?php endforeach; ?>
      <div style="font-size:12px;color:#888;margin-top:8px">Moyenne des <?= $nbNotes ?> critère(s) renseigné(s) ; les critères non renseignés ne comptent pas.</div>
    </div>
    <?php endif; ?>

    <?php if (!empty($fournisseur['notes'])): ?>
    <div class="card"><h2>Notes internes</h2><div><?= nl2br(View::e($fournisseur['notes'])) ?></div></div>
    <?php endif; ?>
  </div>

  <div>
    <?php if ($nbAlertes > 0): ?>
    <div class="card" style="border-color:#fca5a5">
      <h2>Alertes documentaires</h2>
      <?php foreach ($alertesDocuments as $d): [$etat, $texte] = FournisseurPieceJointe::etatEcheance($d['expire_le']); ?>
        <div class="info-row" style="display:block"><strong><?= View::e(FournisseurPieceJointe::CATEGORIES[$d['categorie']] ?? $d['categorie']) ?></strong> — <?= View::e($d['nom_original']) ?><br>
          <span class="badge <?= $etat === 'expire' ? 'badge-red' : 'badge-orange' ?>"><?= View::e($texte) ?></span></div>
      <?php endforeach; ?>
      <div style="margin-top:8px"><a href="<?= $base ?>&onglet=documents">Voir les documents</a></div>
    </div>
    <?php endif; ?>

    <div class="card">
      <h2>Activité</h2>
      <div class="info-row"><span class="label">Consultations en cours</span><span><?= $nbConsultEnCours ?></span></div>
      <div class="info-row"><span class="label">Consultations (total)</span><span><?= count($consultations) ?></span></div>
      <div class="info-row"><span class="label">Offres courantes</span><span><?= (int) ($performance['offres']['courantes'] ?? 0) ?></span></div>
      <div class="info-row"><span class="label">Offres retenues</span><span><?= (int) ($performance['offres']['retenues'] ?? 0) ?></span></div>
      <div style="font-size:12px;color:#888;margin-top:8px">Une offre révisée compte une seule fois (les versions remplacées sont exclues). <a href="<?= $base ?>&onglet=evaluation">Indicateurs de performance</a></div>
    </div>

    <div class="card">
      <h2>Prochaines actions</h2>
      <?php if (empty($actions)): ?><div class="empty-state">Aucune échéance enregistrée.</div><?php endif; ?>
      <?php foreach ($actions as $a): $retard = $a['date'] < $aujourdhui; ?>
        <div class="info-row"><span><a href="<?= View::e($a['lien']) ?>"><?= View::e($a['texte']) ?></a></span>
          <span style="<?= $retard ? 'color:#991b1b;font-weight:600' : '' ?>"><?= date('d/m/Y', strtotime($a['date'])) ?><?= $retard ? ' (dépassée)' : '' ?></span></div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <h2>Dossiers liés</h2>
      <?php if (empty($dossiersLies)): ?><div class="empty-state">Aucun dossier lié pour le moment.</div><?php endif; ?>
      <?php foreach (array_slice($dossiersLies, 0, 6) as $d): ?>
        <div class="info-row"><span><a href="/index.php?r=dossiers/<?= (int) $d['id'] ?>"><?= View::e($d['reference']) ?></a></span><span style="font-size:12px;color:#888"><?= View::e($d['info']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <h2>Gestion</h2>
      <?= $ligne('Responsable interne', $responsableNom) ?>
      <?= $ligne('Filiale', $filiale['nom'] ?? '') ?>
      <?= $ligne('Origine du contact', Fournisseur::ORIGINES[$fournisseur['origine_contact'] ?? ''] ?? '') ?>
      <div class="info-row"><span class="label">Client lié</span><span><?php if ($clientLie): ?><a href="/index.php?r=clients/<?= (int) $clientLie['id'] ?>"><?= View::e($clientLie['nom']) ?></a><?php else: ?>—<?php endif; ?></span></div>
      <?= $ligne('Créé le', !empty($fournisseur['created_at']) ? date('d/m/Y', strtotime($fournisseur['created_at'])) : '') ?>
    </div>
  </div>
</div>
