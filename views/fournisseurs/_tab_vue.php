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

    <?php if ($peutEcrire):
      $lienPret = \App\Models\FournisseurLien::schemaPret();
      $liensF = $lienPret ? \App\Models\FournisseurLien::liensDuFournisseur((int) $fournisseur['id']) : [];
      $urlBaseF = (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
      $suivi = $lienPret ? \App\Models\FournisseurLien::consultations((int) $fournisseur['id']) : [];
      $baseF = '/index.php?r=fournisseurs/' . (int) $fournisseur['id'];
      $lienActif = null; foreach ($liensF as $lf) { if (empty($lf['revoque_le']) && strtotime((string) $lf['expire_le']) > time()) { $lienActif = $lf; break; } }
      $urlActif = $lienActif ? $urlBaseF . '/index.php?r=lien-fournisseur/' . $lienActif['token'] : '';
      $relance = $lienActif ? \App\Models\FournisseurLien::messageRelance(\App\Models\FournisseurLien::aRelancer((int) $fournisseur['id']), $urlActif) : '';
    ?>
    <div class="card" id="lien-fournisseur">
      <h2>Lien fournisseur</h2>
      <?php if (!$lienPret): ?>
        <div class="empty-state">Migration V68 requise pour activer le lien fournisseur.</div>
      <?php else: ?>
        <p style="font-size:13px;color:#666;margin:0 0 10px">Lien privé, sans compte ni mot de passe : le fournisseur voit ses demandes de prix en cours et y répond (prix, transport, délai). Son offre arrive « à vérifier » : rien n’est retenu sans vous. Vos clients, marges et les offres des autres fournisseurs ne sont jamais visibles.</p>
        <?php if ((int) $fournisseur['is_active'] === 1): ?>
        <form method="post" action="<?= $baseF ?>/lien" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken ?? \App\Core\Auth::csrfToken()) ?>">
          <select name="jours" aria-label="Durée de validité"><?php foreach (\App\Models\FournisseurLien::DUREES_JOURS as $j): ?><option value="<?= $j ?>" <?= $j === \App\Models\FournisseurLien::DUREE_DEFAUT ? 'selected' : '' ?>>Valable <?= $j ?> jours</option><?php endforeach; ?></select>
          <?php if (\App\Core\Schema::colonne('fournisseur_liens', 'contact_id')): ?>
          <select name="contact_id" aria-label="Contact destinataire"><?php foreach (\App\Models\Echange::contacts('fournisseur', $fournisseur) as $_c): ?><option value="<?= (int) $_c['id'] ?>">Pour : <?= View::e($_c['nom']) ?><?= $_c['principal'] ? ' (principal)' : '' ?></option><?php endforeach; ?></select>
          <?php endif; ?>
          <button type="submit" class="btn btn-sm">Créer un lien</button>
        </form>
        <?php endif; ?>
        <?php if ($suivi): ?>
        <div style="margin-bottom:10px">
          <div style="font-size:12px;color:#888;margin-bottom:4px">Suivi des demandes</div>
          <?php foreach ($suivi as $sv): [$libEtat, $coul] = \App\Models\FournisseurLien::etat($sv); ?>
            <div style="display:flex;justify-content:space-between;gap:8px;font-size:13px;padding:4px 0;border-top:1px solid #f0f0f0"><a href="/index.php?r=consultations/<?= (int) $sv['id'] ?>"><?= View::e($sv['reference'] ?? ('#' . $sv['id'])) ?></a><span class="badge badge-<?= $coul === 'red' ? 'red' : ($coul === 'green' ? 'green' : ($coul === 'yellow' ? 'yellow' : 'gray')) ?>"><?= View::e($libEtat) ?></span></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($relance !== ''): ?>
        <div style="background:#E8F5EC;border-radius:8px;padding:8px 10px;font-size:13px;margin-bottom:10px"><div id="msg-relance" style="white-space:pre-wrap"><?= View::e($relance) ?></div>
          <button type="button" class="btn btn-sm btn-secondary" style="margin-top:6px" onclick="var t=document.getElementById('msg-relance').innerText;if(navigator.clipboard){navigator.clipboard.writeText(t);this.textContent='Message copié ✔';}">Copier le message de relance</button></div>
        <?php endif; ?>
        <?php if (empty($liensF)): ?><div class="empty-state">Aucun lien créé pour ce fournisseur.</div><?php endif; ?>
        <?php foreach ($liensF as $l):
          $revoque = !empty($l['revoque_le']); $expire = strtotime((string) $l['expire_le']) <= time();
          $url = $urlBaseF . '/index.php?r=lien-fournisseur/' . $l['token']; ?>
          <div style="border-top:1px solid #eee;padding:10px 0">
            <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center">
              <span>
                <?php if ($revoque): ?><span class="badge badge-red">Révoqué</span><?php elseif ($expire): ?><span class="badge badge-gray">Expiré</span><?php else: ?><span class="badge badge-green">Actif</span><?php endif; ?>
                <span style="font-size:12px;color:#888">expire le <?= date('d/m/Y', strtotime((string) $l['expire_le'])) ?> · <?= (int) $l['nb_acces'] ?> visite(s)<?= !empty($l['dernier_acces']) ? ', dernière le ' . date('d/m/Y H:i', strtotime((string) $l['dernier_acces'])) : '' ?></span>
              </span>
              <?php if (!$revoque && !$expire): ?>
              <form method="post" action="<?= $baseF ?>/lien/<?= (int) $l['id'] ?>/revoquer" style="display:inline"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken ?? \App\Core\Auth::csrfToken()) ?>"><button type="submit" class="btn btn-sm btn-secondary">Révoquer</button></form>
              <?php endif; ?>
            </div>
            <?php if (!$revoque && !$expire): ?><input type="text" readonly value="<?= View::e($url) ?>" onclick="this.select()" style="width:100%;margin-top:6px;font-size:12px" aria-label="Lien fournisseur">
            <?php $type = 'fournisseur'; $fiche = $fournisseur; $phrase = 'voici le lien pour répondre à nos demandes et suivre vos commandes'; $actionContact = $baseF . '/lien/' . (int) $l['id'] . '/contact'; $csrf = $csrfToken ?? \App\Core\Auth::csrfToken(); include __DIR__ . '/../echanges/_envoi_lien.php'; ?><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
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

    <?php if (in_array('transitaire', Fournisseur::typesDe($fournisseur), true)): $tt = \App\Models\TransitaireTarif::courants((int) $fournisseur['id']); ?>
    <div class="card" id="tarifs-transitaire">
      <h2>Tarifs d'achat</h2>
      <?php if (!\App\Models\TransitaireTarif::disponible()): ?>
        <div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v63.php</strong>.</div>
      <?php else: ?>
      <?php $nb = 0; foreach ($tt as $t): $nb++; ?>
        <div class="info-row"><span><?= View::e(\App\Models\Expedition::MODES[$t['mode']] ?? $t['mode']) ?> · <?= trim((string) ($t['destination'] ?? '')) !== '' ? View::e($t['destination']) : 'toutes destinations' ?> · <?= $t['famille'] === 'alimentaire' ? 'Alimentaire' : 'Non alimentaire' ?></span><span><strong><?= number_format((float) $t['montant'], 2, ',', ' ') ?></strong> <?= View::e(\App\Models\Expedition::TARIFS_ACHAT[$t['unite']] ?? $t['unite']) ?> <span style="font-size:12px;color:#888">depuis le <?= date('d/m/Y', strtotime($t['date_debut'])) ?></span></span></div>
      <?php endforeach; ?>
      <?php if (!$nb): ?><div class="empty-state">Aucun tarif enregistré. Ils seront repris automatiquement dans les nouvelles expéditions.</div><?php endif; ?>
      <?php if (!empty($peutEcrire)): ?>
      <form method="post" action="/index.php?r=fournisseurs/<?= (int) $fournisseur['id'] ?>/tarifs" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <select name="mode" aria-label="Mode" style="width:auto"><?php foreach (\App\Models\Expedition::MODES as $k => $l): ?><option value="<?= $k ?>"><?= View::e($l) ?></option><?php endforeach; ?></select>
        <select name="famille" aria-label="Marchandise" style="width:auto"><option value="non_alimentaire">Non alimentaire</option><option value="alimentaire">Alimentaire</option></select>
        <input type="text" name="destination" list="dest-tarifs" placeholder="Destination (facultatif)" aria-label="Destination" style="width:200px">
        <datalist id="dest-tarifs"><?php foreach (array_unique(array_filter(array_map(fn($t) => trim((string) ($t['destination'] ?? '')), $tt))) as $dd): ?><option value="<?= View::e($dd) ?>"><?php endforeach; ?></datalist>
        <input type="text" name="montant" inputmode="decimal" placeholder="Montant" aria-label="Montant" style="width:110px" required>
        <select name="unite" aria-label="Unité" style="width:auto"><?php foreach (\App\Models\Expedition::TARIFS_ACHAT as $k => $l): ?><option value="<?= $k ?>"><?= View::e($l) ?></option><?php endforeach; ?></select>
        <input type="date" name="date_debut" value="<?= date('Y-m-d') ?>" aria-label="Valable depuis" style="width:auto">
        <button class="btn btn-sm btn-primary" type="submit">Enregistrer le tarif</button>
      </form>
      <div class="hint">La destination décide du tarif : sans destination, le tarif vaut pour toutes. Un nouveau tarif remplace l'ancien à partir de sa date. Les expéditions déjà créées gardent leurs valeurs.</div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

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
