<?php use App\Core\Icon; use App\Core\View; use App\Core\Storage; use App\Models\Utilisateur; use App\Models\Demande; use App\Models\Dossier; ?>
<?php $retour = '/index.php?' . ($retourListeQuery ?: 'r=demandes'); ?>
<a href="<?= $retour ?>" style="font-size:13px;color:#666">&larr; Retour aux demandes</a>

<?php
$statutInfo = Demande::STATUT_BADGES[$demande['statut']] ?? [ucfirst($demande['statut']), 'badge-gray'];
$peutQualifier = in_array($demande['statut'], ['a_qualifier', 'en_attente_info'], true);
// [précisé 04/10, étendu 05/10] Les voies "Nouveau dossier" ET "Reprise hors
// Suivora" créent désormais le dossier dans la même soumission que la
// qualification (voir qualifierNouvelle() et qualifierReprise()) : ce
// bouton ne reste donc qu'un filet de secours générique, pour les demandes
// qualifiées dont la création du dossier aurait échoué à ce moment-là
// (quelle que soit la voie), ou d'anciennes demandes qualifiées avant ces
// changements.
$peutCreerDossier = $demande['statut'] === 'qualifiee' && !$dossier;
$peutModifier = !in_array($demande['statut'], ['rejetee', 'archivee'], true);
$peutRejeter = in_array($demande['statut'], ['a_qualifier', 'en_attente_info', 'qualifiee'], true);
$echeanceEnRetard = !empty($demande['echeance']) && strtotime($demande['echeance']) < strtotime('today') && in_array($demande['statut'], ['a_qualifier', 'en_attente_info'], true);
$actionLabels = [
    'creation' => 'Demande créée',
    'modification' => 'Demande modifiée',
    'qualification' => 'Qualifiée — nouvelle demande',
    'qualification_rattachement' => 'Qualifiée — rattachée à un élément existant',
    'qualification_reprise' => 'Qualifiée — reprise hors Suivora',
    'creation_dossier' => 'Dossier créé',
    'rejet_demande' => 'Demande rejetée',
    'archivage_demande' => 'Demande archivée',
    'desarchivage_demande' => 'Demande désarchivée',
    'ajout_piece_jointe' => 'Pièce jointe ajoutée',
    'suppression_piece_jointe' => 'Pièce jointe supprimée',
    'extraction_ia_articles' => 'Articles ajoutés via IA',
    'ajout_article' => 'Article ajouté',
    'modification_article' => 'Article modifié',
    'suppression_article' => 'Article retiré',
];
// Unités proposées pour les lignes d'articles (mêmes valeurs que le
// formulaire de création, voir views/requests/create.php).
$unitesArticle = ['Pièce', 'Carton', 'Palette', 'Sac', 'Kg', 'Tonne', 'Litre', 'm³', 'Lot', 'Conteneur'];
?>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px;flex-wrap:wrap;gap:10px">
  <div>
    <h1>
      <?= View::e($demande['reference']) ?>
      <span class="badge <?= $statutInfo[1] ?>"><?= View::e($statutInfo[0]) ?></span>
      <?php if (!empty($demande['qualification_type'])): ?>
        <span class="badge badge-gray"><?= View::e(Demande::QUALIFICATION_TYPES[$demande['qualification_type']] ?? $demande['qualification_type']) ?></span>
      <?php endif; ?>
      <?php if (($demande['priorite'] ?? 'normale') !== 'normale'): ?>
        <span class="badge <?= Demande::PRIORITE_BADGES[$demande['priorite']] ?? 'badge-gray' ?>"><?= Demande::PRIORITES[$demande['priorite']] ?? ucfirst($demande['priorite']) ?></span>
      <?php endif; ?>
    </h1>
    <div class="subtitle"><?= View::e($demande['objet']) ?> — <?= View::e($filiale['nom'] ?? '') ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($peutQualifier): ?>
      <a href="/index.php?r=demandes/<?= $demande['id'] ?>/qualifier" class="btn">Qualifier la demande</a>
    <?php elseif ($peutCreerDossier): ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/creer-dossier" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <select name="type_dossier" title="Type de dossier (déduit de l'activité, modifiable)">
          <?php $typeDeduit = Dossier::deduireType($demande['activite'] ?? null); ?>
          <?php foreach (Dossier::TYPES_LABELS as $val => $label): ?>
            <option value="<?= View::e($val) ?>" <?= $val === $typeDeduit ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Qualifier et créer le dossier</button>
      </form>
    <?php elseif ($dossier): ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn">Voir le dossier <?= View::e($dossier['reference']) ?></a>
    <?php endif; ?>
    <?php if ($peutModifier): ?>
      <a href="/index.php?r=demandes/<?= $demande['id'] ?>/modifier" class="btn btn-secondary"><?= Icon::svg('edit-2', 'icon', 14) ?> Modifier</a>
    <?php endif; ?>
    <?php if ($peutRejeter): ?>
      <button class="btn btn-secondary" onclick="document.getElementById('rejeter-form').style.display='block'">Rejeter</button>
    <?php endif; ?>
    <?php if ($demande['statut'] === 'archivee'): ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/desarchiver">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-secondary"><?= Icon::svg('rotate-ccw', 'icon', 14) ?> Désarchiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/archiver">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="btn btn-secondary"><?= Icon::svg('archive', 'icon', 14) ?> Archiver</button>
      </form>
    <?php endif; ?>
    <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/supprimer" onsubmit="return confirm('Supprimer définitivement la demande <?= View::e(addslashes($demande['reference'])) ?> — <?= View::e(addslashes($demande['objet'])) ?> ? Cette action est irréversible.');">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <button type="submit" class="btn btn-secondary"><?= Icon::svg('trash-2', 'icon', 14) ?> Supprimer</button>
    </form>
  </div>
</div>

<!-- [ajouté 05/10, report de la maquette] Bandeau compact pour les champs de
     suivi les plus consultés — remplace les kv-row équivalentes qui
     vivaient avant dans les cartes "Besoin" et "Suivi" plus bas (Filiale
     reste dans le sous-titre ci-dessus, Notes internes reste dans "Suivi"). -->
<div class="info-strip">
  <div class="ii"><?= Icon::svg('user', '', 16) ?><div><div class="il">Responsable</div><div class="iv"><?= !empty($demande['responsable_id']) ? View::e(Utilisateur::nameOf($demande['responsable_id'])) : 'Non assigné' ?></div></div></div>
  <div class="ii"><?= Icon::svg('briefcase', '', 16) ?><div><div class="il">Activité</div><div class="iv"><?= View::e($demande['activite']) ?: 'Non renseigné' ?></div></div></div>
  <div class="ii"><?= Icon::svg('tag', '', 16) ?><div><div class="il">Priorité</div><div class="iv"><?= Demande::PRIORITES[$demande['priorite']] ?? ucfirst($demande['priorite']) ?></div></div></div>
  <div class="ii"><?= Icon::svg('calendar', '', 16) ?><div><div class="il">Échéance interne</div><div class="iv"><?= $demande['echeance'] ? date('d/m/Y', strtotime($demande['echeance'])) : 'Non renseigné' ?> <?php if ($echeanceEnRetard): ?><span class="badge badge-red">En retard</span><?php endif; ?></div></div></div>
  <div class="ii"><?= Icon::svg('clock', '', 16) ?><div><div class="il">Date souhaitée client</div><div class="iv"><?= !empty($demande['date_souhaitee_client']) ? date('d/m/Y', strtotime($demande['date_souhaitee_client'])) : 'Non renseignée' ?></div></div></div>
</div>

<?php if ($peutRejeter): ?>
<div class="card" id="rejeter-form" style="display:none">
  <h2>Rejeter la demande</h2>
  <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/rejeter">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label>Motif *</label>
      <textarea name="motif" rows="2" required></textarea>
    </div>
    <button type="submit" class="btn">Confirmer le rejet</button>
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('rejeter-form').style.display='none'">Annuler</button>
  </form>
</div>
<?php endif; ?>

<?php if (!empty($demande['qualification_type']) || $demande['statut'] === 'rejetee'): ?>
<div class="card">
  <h2>Qualification</h2>
  <?php if ($demande['qualification_type'] === 'NEW'): ?>
    <div class="kv-row"><span class="label">Client</span><span class="value"><?= View::e($client['nom'] ?? 'Prospect / non enregistré') ?></span></div>
    <div class="kv-row"><span class="label">Destination</span><span class="value"><?= View::e(trim(($demande['lieu_livraison'] ?? '') . (($demande['lieu_livraison'] ?? '') && ($demande['destination_pays'] ?? '') ? ' — ' : '') . ($demande['destination_pays'] ?? ''))) ?: 'Non renseigné' ?></span></div>
    <?php if (!empty($demande['incoterm_souhaite'])): ?>
    <div class="kv-row"><span class="label">Incoterm souhaité</span><span class="value"><?= View::e(Demande::INCOTERMS[$demande['incoterm_souhaite']] ?? '—') ?></span></div>
    <?php endif; ?>
    <div class="kv-row"><span class="label">Mode de paiement souhaité</span><span class="value"><?= View::e(Demande::MODES_PAIEMENT[$demande['mode_paiement_souhaite'] ?? ''] ?? 'Non renseigné') ?></span></div>
  <?php elseif ($demande['qualification_type'] === 'ADDITION'): ?>
    <div class="kv-row"><span class="label">Rattachée à</span><span class="value">
      <?php if ($linkedDemande): ?>
        <a href="/index.php?r=demandes/<?= $linkedDemande['id'] ?>">Demande <?= View::e($linkedDemande['reference']) ?></a>
      <?php elseif ($linkedDossier): ?>
        <a href="/index.php?r=dossiers/<?= $linkedDossier['id'] ?>">Dossier <?= View::e($linkedDossier['reference']) ?></a>
      <?php else: ?>Non renseigné<?php endif; ?>
    </span></div>
  <?php elseif ($demande['qualification_type'] === 'EXTERNAL_TAKEOVER'): ?>
    <div class="kv-row"><span class="label">Client</span><span class="value"><?= View::e($client['nom'] ?? 'Prospect / non enregistré') ?></span></div>
    <div class="kv-row"><span class="label">Étape actuelle</span><span class="value"><?= View::e(Demande::TAKEOVER_STAGES[$demande['takeover_stage']] ?? $demande['takeover_stage']) ?></span></div>
    <div class="kv-row"><span class="label">Démarré le</span><span class="value"><?= $demande['original_started_at'] ? date('d/m/Y', strtotime($demande['original_started_at'])) : 'Non renseigné' ?></span></div>
    <div class="kv-row"><span class="label">Enregistré dans Suivora le</span><span class="value"><?= $demande['registered_in_suivora_at'] ? date('d/m/Y', strtotime($demande['registered_in_suivora_at'])) : 'Non renseigné' ?></span></div>
    <div class="kv-row"><span class="label">Origine externe</span><span class="value"><?= View::e($demande['external_source']) ?: 'Non renseigné' ?></span></div>
    <div class="kv-row"><span class="label">Référence externe</span><span class="value"><?= View::e($demande['external_reference']) ?: 'Non renseigné' ?></span></div>
    <?php // [simplifié 05/10] Le dossier est désormais créé directement à la
          // qualification (voir qualifierReprise()) ; le filet de secours
          // générique en haut de page ($peutCreerDossier) prend le relais si
          // la création a échoué à ce moment-là — plus de formulaire dédié ici. ?>
  <?php endif; ?>
  <?php if (!empty($demande['qualification_notes'])): ?>
    <div class="kv-row"><span class="label">Notes</span><span class="value"><?= nl2br(View::e($demande['qualification_notes'])) ?></span></div>
  <?php endif; ?>
  <?php if (!empty($demande['qualified_at'])): ?>
    <div class="kv-row"><span class="label">Qualifiée le</span><span class="value"><?= date('d/m/Y H:i', strtotime($demande['qualified_at'])) ?> par <?= View::e(Utilisateur::nameOf($demande['qualified_by'] ?? null)) ?></span></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Besoin et message original</h2>
      <div class="kv-row"><span class="label">Objet</span><span class="value"><?= View::e($demande['objet']) ?></span></div>
      <div class="kv-row">
        <span class="label">Message</span>
        <span class="value" style="display:block;white-space:pre-wrap;word-break:break-word"><?= $demande['message'] ? nl2br(View::e($demande['message'])) : 'Non renseigné' ?></span>
      </div>
      <div class="kv-row"><span class="label">Canal</span><span class="value"><?= View::e($demande['canal']) ?: 'Non renseigné' ?></span></div>
      <div class="kv-row"><span class="label">Reçue le</span><span class="value"><?= $demande['recue_le'] ? date('d/m/Y', strtotime($demande['recue_le'])) : 'Non renseigné' ?></span></div>
      <?php // [déplacé 05/10, report de la maquette] "Date souhaitée par le client" vit désormais dans le bandeau d'infos en haut de page. ?>
      <?php if (!empty(trim($demande['message'] ?? ''))): ?>
        <?php if (\App\Services\AiExtracteur::estDisponible()): ?>
        <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/extraction-ia" style="margin-top:12px;padding-top:12px;border-top:1px solid #eef0f4">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          <button type="submit" class="btn btn-sm btn-secondary">Extraire les articles avec l'IA</button>
        </form>
        <?php else: ?>
        <div style="display:flex;align-items:center;gap:10px;color:#888;font-size:12.5px;background:#f7f8fa;border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-top:12px">
          <?= Icon::svg('info', '', 16) ?>
          Extraction IA indisponible pour le moment — ajoutez les articles manuellement ci-dessous.
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Client et contact</h2>
      <?php if ($client): ?>
        <div class="kv-row"><span class="label">Client</span><span class="value"><a href="/index.php?r=clients/<?= $client['id'] ?>"><?= View::e($client['nom']) ?></a></span></div>
        <div class="kv-row"><span class="label">E-mail</span><span class="value"><?= View::e($client['email']) ?: 'Non renseigné' ?></span></div>
        <div class="kv-row"><span class="label">Téléphone</span><span class="value"><?= View::e($client['telephone']) ?: 'Non renseigné' ?></span></div>
      <?php else: ?>
        <div class="kv-row"><span class="label">Nom</span><span class="value"><?= View::e($demande['expediteur_nom']) ?: 'Non renseigné' ?></span></div>
        <div class="kv-row"><span class="label">Entreprise</span><span class="value"><?= View::e($demande['expediteur_entreprise']) ?: 'Non renseigné' ?></span></div>
        <div class="kv-row"><span class="label">E-mail</span><span class="value"><?= View::e($demande['expediteur_email']) ?: 'Non renseigné' ?></span></div>
        <div class="kv-row"><span class="label">Téléphone</span><span class="value"><?= View::e($demande['expediteur_telephone']) ?: 'Non renseigné' ?></span></div>
      <?php endif; ?>
      <?php
        $waNumero = preg_replace('/[^0-9]/', '', $client['telephone'] ?? $demande['expediteur_telephone'] ?? '');
        $emailContact = $client['email'] ?? $demande['expediteur_email'] ?? '';
      ?>
      <?php if ($waNumero || $emailContact): ?>
        <div style="margin-top:10px">
          <?php if ($waNumero): ?>
            <a href="https://wa.me/<?= $waNumero ?>" target="_blank" class="btn btn-sm" style="background:#2E7D5B">WhatsApp</a>
          <?php endif; ?>
          <?php if ($emailContact): ?>
            <a href="mailto:<?= View::e($emailContact) ?>" class="btn btn-sm btn-secondary">E-mail</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Articles / besoins</h2>
      <?php if (empty($articles)): ?>
        <div class="empty-state">Aucun article pour le moment — facultatif pour une demande de service encore peu détaillée.</div>
      <?php else: ?>
        <?php foreach ($articles as $a): ?>
          <!-- Un <form> ne peut pas être enfant direct d'un <tr> ; ce formulaire
               vide (placé hors du tableau) porte le csrf + l'action, et chaque
               champ de la ligne s'y rattache via l'attribut form="..." — le
               bouton "Retirer" réutilise le même formulaire avec formaction
               pour éviter d'imbriquer un second <form> dans la cellule. -->
          <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/articles/<?= $a['id'] ?>/modifier" id="art-form-<?= $a['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
          </form>
        <?php endforeach; ?>
        <table class="responsive-cards">
          <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Conditionnement / précision</th><th>Référence</th><th>Marque</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($articles as $a): ?>
            <tr>
              <td data-label="Désignation"><input type="text" name="designation" value="<?= View::e($a['designation']) ?>" form="art-form-<?= $a['id'] ?>" style="width:100%;min-width:160px"></td>
              <td data-label="Qté"><input type="number" step="0.01" name="quantite" value="<?= View::e((string) $a['quantite']) ?>" form="art-form-<?= $a['id'] ?>" style="width:75px"></td>
              <td data-label="Unité">
                <select name="unite" form="art-form-<?= $a['id'] ?>">
                  <option value="" <?= $a['unite'] === '' ? 'selected' : '' ?>></option>
                  <?php foreach ($unitesArticle as $u): ?>
                    <option <?= $a['unite'] === $u ? 'selected' : '' ?>><?= $u ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td data-label="Conditionnement"><input type="text" name="conditionnement" value="<?= View::e($a['conditionnement'] ?? '') ?>" form="art-form-<?= $a['id'] ?>" style="width:100%"></td>
              <td data-label="Référence"><input type="text" name="reference" value="<?= View::e($a['reference']) ?>" form="art-form-<?= $a['id'] ?>" style="width:90px"></td>
              <td data-label="Marque"><input type="text" name="marque" value="<?= View::e($a['marque']) ?>" form="art-form-<?= $a['id'] ?>" style="width:90px"></td>
              <td data-label="Actions" style="white-space:nowrap">
                <button type="submit" form="art-form-<?= $a['id'] ?>" class="btn btn-sm btn-secondary" title="Enregistrer"><?= Icon::svg('check-circle', 'icon', 14) ?></button>
                <button type="submit" form="art-form-<?= $a['id'] ?>" formaction="/index.php?r=demandes/<?= $demande['id'] ?>/articles/<?= $a['id'] ?>/supprimer" class="btn btn-sm btn-secondary" title="Retirer" onclick="return confirm('Retirer cette ligne ?');"><?= Icon::svg('trash-2', 'icon', 14) ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/articles" style="margin-top:14px;padding-top:14px;border-top:1px solid #eef0f4;display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group" style="margin:0"><label style="font-size:12px">Désignation</label><input type="text" name="designation" required></div>
        <div class="form-group" style="margin:0"><label style="font-size:12px">Qté</label><input type="number" step="0.01" name="quantite" style="width:75px"></div>
        <div class="form-group" style="margin:0"><label style="font-size:12px">Unité</label>
          <select name="unite">
            <option value=""></option>
            <?php foreach ($unitesArticle as $u): ?><option><?= $u ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0"><label style="font-size:12px">Conditionnement</label><input type="text" name="conditionnement"></div>
        <div class="form-group" style="margin:0"><label style="font-size:12px">Référence</label><input type="text" name="reference" style="width:90px"></div>
        <div class="form-group" style="margin:0"><label style="font-size:12px">Marque</label><input type="text" name="marque" style="width:90px"></div>
        <button type="submit" class="btn btn-sm">+ Ajouter</button>
      </form>
    </div>

    <div class="card">
      <h2>Documents</h2>
      <?php if (empty($piecesJointes)): ?>
        <div class="empty-state">Aucune pièce jointe.</div>
      <?php else: ?>
        <table class="responsive-cards">
          <thead><tr><th>Fichier</th><th>Taille</th><th>Ajouté le</th><th>Par</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($piecesJointes as $p): ?>
            <tr>
              <td data-label="Fichier"><?= View::e($p['nom_original']) ?></td>
              <td data-label="Taille"><?= number_format($p['taille'] / 1024, 0) ?> Ko</td>
              <td data-label="Ajouté le"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
              <td data-label="Par"><?= View::e($p['uploaded_by_nom']) ?></td>
              <td data-label="Actions" style="white-space:nowrap">
                <?php if (Storage::estPrevisualisable($p['type_mime'])): ?>
                  <a href="/index.php?r=demandes/<?= $demande['id'] ?>/pieces/<?= $p['id'] ?>/telecharger&apercu=1" target="_blank" class="btn btn-sm btn-secondary" title="Aperçu"><?= Icon::svg('eye', 'icon', 14) ?></a>
                <?php endif; ?>
                <a href="/index.php?r=demandes/<?= $demande['id'] ?>/pieces/<?= $p['id'] ?>/telecharger" class="btn btn-sm btn-secondary" title="Télécharger"><?= Icon::svg('download', 'icon', 14) ?></a>
                <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/pieces/<?= $p['id'] ?>/supprimer" style="display:inline" onsubmit="return confirm('Supprimer cette pièce jointe ?');">
                  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
                  <button type="submit" class="btn btn-sm btn-secondary" title="Supprimer"><?= Icon::svg('trash-2', 'icon', 14) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/pieces" enctype="multipart/form-data" style="margin-top:12px;padding-top:12px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="file" name="fichier" required>
        <button type="submit" class="btn btn-sm" style="margin-top:8px"><?= Icon::svg('paperclip', 'icon', 14) ?> Ajouter</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Suivi</h2>
      <div class="kv-row"><span class="label">Filiale</span><span class="value"><?= View::e($filiale['nom'] ?? '') ?: 'Non renseigné' ?></span></div>
      <?php // [déplacé 05/10, report de la maquette] Activité, Responsable, Priorité et Échéance interne vivent désormais dans le bandeau d'infos en haut de page. ?>
      <?php if (!empty($demande['notes_internes'])): ?>
      <div class="kv-row"><span class="label">Notes internes</span><span class="value"><?= nl2br(View::e($demande['notes_internes'])) ?></span></div>
      <?php endif; ?>
    </div>

    <?php if (!empty($historique)): ?>
    <details class="card" style="padding:20px" open>
      <summary style="cursor:pointer;list-style:none"><h2 style="display:inline">Historique</h2></summary>
      <div style="margin-top:10px">
      <?php foreach ($historique as $h): ?>
        <div class="kv-row">
          <span class="label"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></span>
          <span class="value"><?= View::e($actionLabels[$h['action']] ?? $h['action']) ?><?php if ($h['utilisateur_nom']): ?> — <?= View::e($h['utilisateur_nom']) ?><?php endif; ?></span>
        </div>
      <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>
  </div>
</div>
