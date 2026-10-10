<?php
use App\Core\Telephone;
use App\Core\View;
use App\Models\Client;
use App\Models\Demande;
use App\Models\Fournisseur;

/**
 * Formulaire unique « Nouveau fournisseur » / « Modifier le fournisseur » (07/10).
 * Variables : $fournisseur (null en création), $old, $erreurs, $doublons, $filiales, $utilisateurs, $clients, $defauts, $schemaPret, $retour.
 */
$estModif = $fournisseur !== null;
$actionUrl = $estModif ? '/index.php?r=fournisseurs/' . (int) $fournisseur['id'] . '/modifier' : '/index.php?r=fournisseurs';
$aSaisie = !empty($old);
$val = function (string $k, string $def = '') use ($old, $fournisseur, $aSaisie) {
    if ($aSaisie && array_key_exists($k, $old)) { return (string) $old[$k]; }
    return (string) ($fournisseur[$k] ?? $def);
};
$coches = function (string $postKey, string $dbKey, string $sep = ',') use ($old, $fournisseur, $aSaisie): array {
    if ($aSaisie) { return array_map('strval', (array) ($old[$postKey] ?? [])); }
    return array_values(array_filter(array_map('trim', explode($sep, (string) ($fournisseur[$dbKey] ?? '')))));
};
$typesCoches = $aSaisie ? array_map('strval', (array) ($old['types'] ?? [])) : ($estModif ? Fournisseur::typesDe($fournisseur) : []);
$activitesCochees = $coches('activites', 'activites');
$devisesCochees = $coches('devises_proposees', 'devises_proposees');
$incotermsCoches = $coches('incoterms_pratiques', 'incoterms_pratiques');
$paysDefaut = $estModif ? '' : ($defauts['pays'] ?? '');
$pays = $val('pays', $paysDefaut);
if ($aSaisie && array_key_exists('tel_indicatif', $old)) {
    $tphIndicatif = (string) $old['tel_indicatif'];
    $tphNumero = (string) ($old['tel_numero'] ?? '');
} elseif ($estModif) {
    [$tphIndicatif, $tphNumero] = Telephone::decouper($fournisseur['telephone'] ?? '');
} else {
    $tphIndicatif = Telephone::indicatifPourPays($pays);
    $tphNumero = '';
}
$tphId = 'tel';
$statut = $aSaisie ? ($old['statut'] ?? 'actif') : ($estModif ? ((int) $fournisseur['is_active'] === 1 ? 'actif' : 'inactif') : 'actif');
$devise = $val('devise', $estModif ? '' : ($defauts['devise'] ?? ''));
$erreur = fn(string $k) => $erreurs[$k] ?? null;
$ouvrirNotes = $estModif && array_filter(array_map(fn($c) => $fournisseur[$c] ?? null, array_keys(Fournisseur::CRITERES_NOTE)), fn($v) => $v !== null && $v !== '');
?>
<a href="<?= View::e($estModif ? '/index.php?r=fournisseurs/' . (int) $fournisseur['id'] : $retour) ?>" style="font-size:13px;color:#666">&larr; <?= $estModif ? 'Retour à la fiche' : 'Retour aux fournisseurs' ?></a>
<h1 style="margin-top:8px"><?= $estModif ? 'Modifier le fournisseur' : 'Nouveau fournisseur' ?></h1>
<div class="subtitle"><?= $estModif ? View::e($fournisseur['nom']) . ' — ' . View::e($fournisseur['code'] ?? '') : 'Seule la raison sociale est obligatoire : vous pourrez compléter la fiche progressivement.' ?></div>

<?php if (!empty($erreurs['_general'])): ?><div class="alert alert-erreur"><?= View::e($erreurs['_general']) ?></div><?php endif; ?>
<?php if (!empty($erreurs) && count(array_diff_key($erreurs, ['_general' => 1])) > 0): ?>
  <div class="alert alert-erreur">Merci de corriger les champs signalés — votre saisie est conservée.</div>
<?php endif; ?>
<?php if (!$schemaPret): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e">Migration V23 à lancer pour enregistrer les types, spécialités, conditions commerciales et le responsable. Les champs de base restent enregistrés.</div>
<?php endif; ?>

<?php if (!empty($doublons)): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e">
    <strong>Un fournisseur similaire existe peut-être déjà.</strong>
    <ul style="margin:6px 0 10px 18px">
      <?php foreach ($doublons as $d): ?>
        <li><a href="/index.php?r=fournisseurs/<?= (int) $d['id'] ?>" target="_blank"><?= View::e($d['nom']) ?></a> (<?= View::e($d['code'] ?? '') ?>)<?= $d['email'] ? ' — ' . View::e($d['email']) : '' ?><?= $d['telephone'] ? ' — ' . View::e($d['telephone']) : '' ?></li>
      <?php endforeach; ?>
    </ul>
    Aucune fusion automatique : vérifiez, puis modifiez la saisie ou confirmez.
    <div style="margin-top:10px"><button type="submit" form="fournisseurForm" name="confirmer_doublon" value="1" class="btn btn-sm"><?= $estModif ? 'Enregistrer malgré tout' : 'Créer quand même' ?></button></div>
  </div>
<?php endif; ?>

<form method="post" action="<?= View::e($actionUrl) ?>" id="fournisseurForm" novalidate<?= $estModif ? '' : ' data-brouillon="fournisseur"' ?>>
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <div class="detail-grid">
    <div>
      <div class="card">
        <h2>Identification</h2>
        <div class="form-row">
          <div class="form-group">
            <label for="nom">Raison sociale *</label>
            <input type="text" id="nom" name="nom" required value="<?= View::e($val('nom')) ?>" <?= $erreur('nom') ? 'style="border-color:#b42318"' : '' ?>>
            <?php if ($erreur('nom')): ?><div style="color:#b42318;font-size:12px;margin-top:4px"><?= View::e($erreur('nom')) ?></div><?php endif; ?>
          </div>
          <div class="form-group"><label for="nom_commercial">Nom commercial</label><input type="text" id="nom_commercial" name="nom_commercial" value="<?= View::e($val('nom_commercial')) ?>"></div>
        </div>
        <div class="form-group">
          <label>Référence</label>
          <input type="text" value="<?= $estModif ? View::e($fournisseur['code'] ?? '') : 'Générée à l’enregistrement' ?>" disabled>
        </div>
        <div class="form-group">
          <label>Type de partenaire (plusieurs choix possibles)</label>
          <div style="display:flex;gap:6px 16px;flex-wrap:wrap">
            <?php foreach (Fournisseur::TYPES as $code => $lib): ?>
              <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="types[]" value="<?= $code ?>" <?= in_array($code, $typesCoches, true) ? 'checked' : '' ?>> <?= View::e($lib) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label for="specialites">Spécialités (séparées par des virgules)</label>
          <input type="text" id="specialites" name="specialites" value="<?= View::e($val('specialites', $val('categories_produits'))) ?>" placeholder="Ex. pièces mécaniques, équipements, transport routier">
        </div>
        <div class="form-group">
          <label>Activités couvertes</label>
          <div style="display:flex;gap:6px 16px;flex-wrap:wrap">
            <?php foreach (Demande::ACTIVITES as $a): ?>
              <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="activites[]" value="<?= View::e($a) ?>" <?= in_array($a, $activitesCochees, true) ? 'checked' : '' ?>> <?= View::e($a) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="pays">Pays</label>
            <input type="text" id="pays" name="pays" list="listePays" value="<?= View::e($pays) ?>" autocomplete="off">
            <datalist id="listePays"><?php foreach (Telephone::pays() as $p): ?><option value="<?= View::e($p) ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="form-group"><label for="ville">Ville</label><input type="text" id="ville" name="ville" value="<?= View::e($val('ville')) ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="adresse">Adresse</label><input type="text" id="adresse" name="adresse" value="<?= View::e($val('adresse')) ?>"></div>
          <div class="form-group"><label for="code_postal">Code postal</label><input type="text" id="code_postal" name="code_postal" value="<?= View::e($val('code_postal')) ?>"></div>
        </div>
        <div class="form-group"><label for="site_web">Site internet</label><input type="text" id="site_web" name="site_web" value="<?= View::e($val('site_web')) ?>" placeholder="www.exemple.com"></div>
        <div class="form-row">
          <div class="form-group"><label for="siret" id="labelSiret">Numéro d’immatriculation (facultatif)</label><input type="text" id="siret" name="siret" value="<?= View::e($val('siret')) ?>"></div>
          <div class="form-group"><label for="tva" id="labelTva">Identifiant fiscal / TVA (facultatif)</label><input type="text" id="tva" name="tva" value="<?= View::e($val('tva')) ?>"></div>
        </div>
      </div>

      <div class="card">
        <h2>Contact principal</h2>
        <div class="form-row">
          <div class="form-group"><label for="contact_prenom">Prénom</label><input type="text" id="contact_prenom" name="contact_prenom" value="<?= View::e($val('contact_prenom')) ?>"></div>
          <div class="form-group"><label for="contact_nom">Nom</label><input type="text" id="contact_nom" name="contact_nom" value="<?= View::e($val('contact_nom')) ?>"></div>
        </div>
        <div class="form-group"><label for="fonction_contact">Fonction</label><input type="text" id="fonction_contact" name="fonction_contact" value="<?= View::e($val('fonction_contact')) ?>"></div>
        <div class="form-row">
          <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= View::e($val('email')) ?>" <?= $erreur('email') ? 'style="border-color:#b42318"' : '' ?>>
            <?php if ($erreur('email')): ?><div style="color:#b42318;font-size:12px;margin-top:4px"><?= View::e($erreur('email')) ?></div><?php endif; ?>
          </div>
          <div class="form-group"><label>Téléphone</label><?php include __DIR__ . '/../clients/_telephone.php'; ?></div>
        </div>
        <div style="font-size:12px;color:#888">D’autres interlocuteurs et adresses (entrepôt, usine…) peuvent être ajoutés depuis la fiche, onglet « Contacts ».</div>
      </div>

      <div class="card">
        <h2>Conditions commerciales</h2>
        <div class="form-group">
          <label>Devises proposées</label>
          <div style="display:flex;gap:6px 16px;flex-wrap:wrap">
            <?php foreach (Fournisseur::DEVISES as $dv): ?>
              <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="devises_proposees[]" value="<?= $dv ?>" <?= in_array($dv, $devisesCochees, true) ? 'checked' : '' ?>> <?= $dv ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="devise">Devise préférentielle</label>
            <select id="devise" name="devise"><option value="">— Aucune —</option>
              <?php foreach (Fournisseur::DEVISES as $dv): ?><option <?= $devise === $dv ? 'selected' : '' ?>><?= $dv ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="conditions_paiement">Conditions de paiement</label>
            <select id="conditions_paiement" name="conditions_paiement"><option value="">— À convenir —</option>
              <?php foreach (Client::CONDITIONS_PAIEMENT as $code => $lib): ?><option value="<?= $code ?>" <?= $val('conditions_paiement') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label for="delai_indicatif">Délai indicatif de fourniture ou d’intervention</label><input type="text" id="delai_indicatif" name="delai_indicatif" value="<?= View::e($val('delai_indicatif')) ?>" placeholder="Ex. 3 à 4 semaines"></div>
          <div class="form-group"><label for="quantite_min">Minimum de commande (avec unité)</label><input type="text" id="quantite_min" name="quantite_min" value="<?= View::e($val('quantite_min')) ?>" placeholder="Ex. 500 kg, 1 conteneur 20 pieds"></div>
        </div>
        <div class="form-group"><label for="pays_desservis">Zones desservies</label><input type="text" id="pays_desservis" name="pays_desservis" value="<?= View::e($val('pays_desservis')) ?>" placeholder="Pays ou régions, séparés par des virgules"></div>
        <div class="form-group">
          <label>Incoterms pratiqués</label>
          <div style="display:flex;gap:6px 14px;flex-wrap:wrap">
            <?php foreach (array_keys(Demande::INCOTERMS) as $code): ?>
              <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="incoterms_pratiques[]" value="<?= $code ?>" <?= in_array($code, $incotermsCoches, true) ? 'checked' : '' ?>> <?= $code ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group"><label for="conditions_livraison">Conditions de livraison</label><input type="text" id="conditions_livraison" name="conditions_livraison" value="<?= View::e($val('conditions_livraison')) ?>" placeholder="Si pertinent : franco à partir de…, enlèvement sur site…"></div>
        <div class="form-group"><label for="marques">Marques représentées</label><input type="text" id="marques" name="marques" value="<?= View::e($val('marques')) ?>"></div>
        <div style="font-size:12px;color:#888">Ces informations sont indicatives : elles ne remplacent jamais les conditions précises d’une offre ou d’une commande.</div>
      </div>

      <details class="card" <?= ($ouvrirNotes || ($aSaisie && !empty(array_filter(array_map(fn($c) => $old[$c] ?? '', array_keys(Fournisseur::CRITERES_NOTE)))))) ? 'open' : '' ?>>
        <summary style="cursor:pointer;font-weight:600">Appréciations chiffrées par critère (facultatif)</summary>
        <div style="margin-top:12px">
          <div style="font-size:12px;color:#888;margin-bottom:8px">0 à 5 par critère ; la note globale affichée est la moyenne des seuls critères renseignés — rien n’est inventé.</div>
          <div class="form-row" style="flex-wrap:wrap">
            <?php foreach (Fournisseur::CRITERES_NOTE as $champ => $label): ?>
              <div class="form-group" style="min-width:150px"><label for="<?= $champ ?>"><?= View::e($label) ?></label>
                <select id="<?= $champ ?>" name="<?= $champ ?>"><option value="">—</option>
                  <?php for ($n = 0; $n <= 5; $n++): ?><option value="<?= $n ?>" <?= $val($champ) !== '' && (int) $val($champ) === $n ? 'selected' : '' ?>><?= $n ?></option><?php endfor; ?>
                </select></div>
            <?php endforeach; ?>
          </div>
        </div>
      </details>
    </div>

    <div>
      <div class="card">
        <h2>Gestion</h2>
        <div class="form-group">
          <label for="filiale_id">Filiale *</label>
          <?php if ($estModif): ?>
            <input type="text" value="<?= View::e(array_values(array_filter($filiales, fn($f) => (int) $f['id'] === (int) $fournisseur['filiale_id']))[0]['nom'] ?? '—') ?>" disabled>
            <div style="font-size:12px;color:#888;margin-top:4px">Le rattachement ne change pas après la création (il protège l’historique des dossiers).</div>
          <?php elseif (count($filiales) > 1): ?>
            <select id="filiale_id" name="filiale_id" required>
              <option value="">— Choisir —</option>
              <?php foreach ($filiales as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (string) $val('filiale_id') === (string) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($erreur('filiale_id')): ?><div style="color:#b42318;font-size:12px;margin-top:4px"><?= View::e($erreur('filiale_id')) ?></div><?php endif; ?>
          <?php elseif (count($filiales) === 1): ?>
            <input type="hidden" name="filiale_id" value="<?= (int) $filiales[0]['id'] ?>">
            <input type="text" value="<?= View::e($filiales[0]['nom']) ?>" disabled>
          <?php else: ?>
            <div class="alert alert-erreur">Aucune filiale ne vous est assignée.</div>
          <?php endif; ?>
        </div>
        <?php if ($schemaPret): ?>
        <div class="form-group">
          <label for="responsable_id">Responsable interne</label>
          <select id="responsable_id" name="responsable_id">
            <option value="">— Aucun —</option>
            <?php foreach ($utilisateurs as $u): if ((int) ($u['is_active'] ?? $u['actif'] ?? 1) !== 1 && (string) $val('responsable_id') !== (string) $u['id']) { continue; } ?>
              <option value="<?= (int) $u['id'] ?>" <?= (string) $val('responsable_id') === (string) $u['id'] ? 'selected' : '' ?>><?= View::e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="origine_contact">Origine du contact</label>
          <select id="origine_contact" name="origine_contact"><option value="">— Non précisée —</option>
            <?php foreach (Fournisseur::ORIGINES as $code => $lib): ?><option value="<?= $code ?>" <?= $val('origine_contact') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label for="statut">Statut</label>
          <select id="statut" name="statut">
            <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
            <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
          </select>
          <div style="font-size:12px;color:#888;margin-top:4px">Un fournisseur inactif reste consultable avec tout son historique.</div>
        </div>
        <div class="form-group">
          <label>Qualification</label>
          <?php $qual = $estModif ? Fournisseur::qualificationDe($fournisseur) : 'a_qualifier'; ?>
          <div><span class="badge <?= Fournisseur::QUALIFICATION_BADGES[$qual] ?>"><?= View::e(Fournisseur::QUALIFICATIONS[$qual]) ?></span></div>
          <div style="font-size:12px;color:#888;margin-top:4px"><?= $estModif ? 'Elle se modifie depuis la fiche, onglet « Évaluation », afin de conserver les critères et la décision.' : 'Tout nouveau fournisseur est « À qualifier » ; la qualification se fait depuis sa fiche.' ?></div>
        </div>
        <?php if ($schemaPret && !empty($clients)): ?>
        <div class="form-group">
          <label for="client_id">Même entreprise que le client (facultatif)</label>
          <select id="client_id" name="client_id"><option value="">— Aucun lien —</option>
            <?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $val('client_id') === (string) $c['id'] ? 'selected' : '' ?>><?= View::e($c['nom']) ?> (<?= View::e($c['code'] ?? '') ?>)</option><?php endforeach; ?>
          </select>
          <div style="font-size:12px;color:#888;margin-top:4px">Simple lien : les deux fiches restent séparées, rien n’est fusionné.</div>
        </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Notes internes</h2>
        <textarea name="notes" rows="5" placeholder="Visibles uniquement par l’équipe."><?= View::e($val('notes')) ?></textarea>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="submit" class="btn"><?= $estModif ? 'Enregistrer les modifications' : 'Créer le fournisseur' ?></button>
        <a class="btn btn-secondary" href="<?= View::e($estModif ? '/index.php?r=fournisseurs/' . (int) $fournisseur['id'] : $retour) ?>">Annuler</a>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  var indicatifs = <?= json_encode(Telephone::PAYS_INDICATIFS, JSON_UNESCAPED_UNICODE) ?>;
  var libelles = {
    'Cameroun': ['N° RCCM', 'N° d’identifiant unique (NIU)'],
    'France': ['SIREN / SIRET', 'N° de TVA intracommunautaire'],
    'Sénégal': ['NINEA / RCCM', 'Identifiant fiscal'],
    "Côte d'Ivoire": ['N° RCCM', 'Numéro de compte contribuable'],
    'Gabon': ['N° RCCM', 'N° d’identification fiscale'],
    'Maroc': ['N° RC / ICE', 'Identifiant fiscal (IF)'],
    'Chine': ['Code de crédit social unifié', 'N° de TVA / identifiant fiscal'],
    'Allemagne': ['N° du registre du commerce (HRB)', 'USt-IdNr. (TVA)']
  };
  var pays = document.getElementById('pays');
  var ind = document.getElementById('tel_ind');
  var touche = <?= $estModif || $aSaisie ? 'true' : 'false' ?>;
  ind.addEventListener('change', function () { touche = true; });
  function maj() {
    var l = libelles[pays.value.trim()] || ['Numéro d’immatriculation', 'Identifiant fiscal / TVA'];
    document.getElementById('labelSiret').textContent = l[0] + ' (facultatif)';
    document.getElementById('labelTva').textContent = l[1] + ' (facultatif)';
    if (!touche && indicatifs[pays.value.trim()]) { ind.value = indicatifs[pays.value.trim()]; }
  }
  pays.addEventListener('input', maj);
  pays.addEventListener('change', maj);
  maj();
})();
</script>
