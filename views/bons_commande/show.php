<?php
use App\Core\View;
use App\Models\BonCommandeFournisseur as Bon;

/** Fiche d'un bon de commande fournisseur. Variables : $bon, $lignes, $fournisseur, $accordClient (null = sans dossier), $peutGerer. */
$fmt = fn($m) => number_format((float) $m, 2, ',', ' ');
$base = '/index.php?r=bons-commande/' . (int) $bon['id'];
$st = $bon['statut'];
$corps = "Bonjour" . ($bon['destinataire_nom'] !== '' ? ' ' . $bon['destinataire_nom'] : '') . ",\n\n"
    . "Veuillez trouver ci-joint notre bon de commande " . $bon['reference'] . " du " . date('d/m/Y', strtotime($bon['date_emission']))
    . " (" . $fmt($bon['montant_total']) . ' ' . $bon['devise'] . ").\n"
    . ($bon['date_livraison_souhaitee'] ? 'Livraison souhaitée le ' . date('d/m/Y', strtotime($bon['date_livraison_souhaitee'])) . ".\n" : '')
    . "Merci de nous confirmer la réception du bon et la date de livraison prévue.\n\nCordialement";
$mailto = $bon['destinataire_email'] !== ''
    ? 'mailto:' . rawurlencode($bon['destinataire_email']) . '?subject=' . rawurlencode('Bon de commande ' . $bon['reference']) . '&body=' . rawurlencode($corps)
    : '';
?>
<a href="/index.php?r=fournisseurs/<?= (int) $bon['fournisseur_id'] ?>&onglet=commandes" style="font-size:13px;color:#666">&larr; Retour au fournisseur</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-top:8px">
  <div>
    <h1 style="margin-bottom:4px"><?= View::e($bon['reference']) ?></h1>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span class="badge <?= Bon::BADGES[$st] ?>"><?= View::e(Bon::STATUTS[$st]) ?></span>
      <span style="color:#666;font-size:14px">Bon de commande fournisseur · <?= View::e($bon['fournisseur_nom']) ?></span>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
    <a href="<?= $base ?>/imprimable" target="_blank" rel="noopener" class="btn btn-secondary">Imprimer / PDF</a>
    <?php if ($mailto !== '' && $st !== 'annule'): ?><a href="<?= $mailto ?>" class="btn btn-secondary" title="Ouvre votre messagerie — rien n'est enregistré comme envoyé">E-mail préparé</a><?php endif; ?>
    <?php if ($peutGerer && $st === 'brouillon'): ?><a href="<?= $base ?>/modifier" class="btn btn-secondary">Modifier</a><?php endif; ?>
  </div>
</div>

<?php if ($accordClient === false && in_array($st, ['brouillon'], true)): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e;margin-top:12px">Le client n’a pas encore accepté la cotation du dossier <?= View::e($bon['dossier_reference'] ?? '') ?> : ce bon ne peut pas être marqué « envoyé » pour le moment. La sélection d’une offre fournisseur ne vaut pas accord du client.</div>
<?php endif; ?>
<?php if ($st === 'annule'): ?>
  <div class="alert" style="background:#fee2e2;color:#991b1b;margin-top:12px">Bon annulé le <?= date('d/m/Y', strtotime((string) $bon['annule_le'])) ?><?= !empty($bon['annule_motif']) ? ' — ' . View::e($bon['annule_motif']) : '' ?>. Il reste consultable.</div>
<?php endif; ?>

<?php if ($peutGerer && $st !== 'annule'): ?>
<div class="card" style="margin-top:14px">
  <h2>Suite à donner</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
    <?php if ($st === 'brouillon'): ?>
      <form method="post" action="<?= $base ?>/statut"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="statut" value="envoye">
        <button type="submit" class="btn">Marquer comme envoyé</button></form>
      <span style="font-size:12px;color:#888;align-self:center">À faire une fois le bon transmis au fournisseur (Suivora360 n’envoie rien automatiquement).</span>
    <?php elseif ($st === 'envoye'): ?>
      <form method="post" action="<?= $base ?>/statut"><input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="statut" value="confirme">
        <button type="submit" class="btn">Le fournisseur a confirmé</button></form>
    <?php endif; ?>
    <details>
      <summary class="btn btn-secondary" style="list-style:none;cursor:pointer">Annuler le bon</summary>
      <form method="post" action="<?= $base ?>/statut" style="margin-top:8px">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="statut" value="annule">
        <div class="form-group"><label for="motif">Motif de l’annulation *</label><input type="text" id="motif" name="motif" maxlength="255" required></div>
        <button type="submit" class="btn btn-sm btn-secondary">Confirmer l’annulation</button>
      </form>
    </details>
  </div>
</div>
<?php endif; ?>

<div class="detail-grid" style="margin-top:14px">
  <div>
    <div class="card">
      <h2>Lignes commandées</h2>
      <table class="responsive-cards">
        <thead><tr><th>Désignation</th><th class="num">Quantité</th><th>Unité</th><th class="num">Prix unitaire</th><th class="num">Montant</th></tr></thead>
        <tbody>
        <?php foreach ($lignes as $l): ?>
          <tr>
            <td data-label="Désignation"><?= View::e($l['designation']) ?></td>
            <td data-label="Quantité" class="num"><?= $l['quantite'] !== null ? View::e(rtrim(rtrim(number_format((float) $l['quantite'], 2, ',', ' '), '0'), ',')) : '—' ?></td>
            <td data-label="Unité"><?= View::e($l['unite']) ?: '—' ?></td>
            <td data-label="Prix unitaire" class="num"><?= $l['prix_unitaire'] !== null ? $fmt($l['prix_unitaire']) : '—' ?></td>
            <td data-label="Montant" class="num"><?= $l['montant'] !== null ? $fmt($l['montant']) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4" style="text-align:right;font-weight:700">Total (<?= View::e($bon['devise']) ?>)</td><td class="num" style="font-weight:700"><?= $fmt($bon['montant_total']) ?></td></tr></tfoot>
      </table>
      <?php if (!empty($bon['notes'])): ?><div style="margin-top:12px"><strong style="font-size:13px">Instructions pour le fournisseur</strong><div style="white-space:pre-wrap;font-size:14px"><?= View::e($bon['notes']) ?></div></div><?php endif; ?>
    </div>
  </div>
  <div>
    <div class="card">
      <h2>Informations</h2>
      <div class="info-row"><span class="label">Fournisseur</span><span><a href="/index.php?r=fournisseurs/<?= (int) $bon['fournisseur_id'] ?>"><?= View::e($bon['fournisseur_nom']) ?></a></span></div>
      <div class="info-row"><span class="label">Adresse (au jour du bon)</span><span><?= View::e($bon['fournisseur_adresse']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Destinataire</span><span><?= View::e(trim($bon['destinataire_nom'] . ' ' . ($bon['destinataire_email'] !== '' ? '<' . $bon['destinataire_email'] . '>' : ''))) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Date du bon</span><span><?= date('d/m/Y', strtotime($bon['date_emission'])) ?></span></div>
      <div class="info-row"><span class="label">Livraison souhaitée</span><span><?= $bon['date_livraison_souhaitee'] ? date('d/m/Y', strtotime($bon['date_livraison_souhaitee'])) : '—' ?></span></div>
      <div class="info-row"><span class="label">Incoterm</span><span><?= View::e($bon['incoterm']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Paiement</span><span><?= View::e($bon['conditions_paiement']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Lieu de livraison</span><span><?= View::e($bon['lieu_livraison']) ?: '—' ?></span></div>
    </div>
    <div class="card">
      <h2>Liens et historique</h2>
      <div class="info-row"><span class="label">Dossier</span><span><?php if (!empty($bon['dossier_id'])): ?><a href="/index.php?r=dossiers/<?= (int) $bon['dossier_id'] ?>"><?= View::e($bon['dossier_reference']) ?></a><?php else: ?>—<?php endif; ?></span></div>
      <div class="info-row"><span class="label">Offre d’origine</span><span><?php if (!empty($bon['offre_id']) && !empty($bon['dossier_id'])): ?><a href="/index.php?r=dossiers/<?= (int) $bon['dossier_id'] ?>&onglet=achats&sous=offres"><?= View::e($bon['reference_offre']) ?></a><?php else: ?>Bon établi librement<?php endif; ?></span></div>
      <div class="info-row"><span class="label">Accord client</span><span><?php if ($accordClient === null): ?>Sans objet (aucun dossier)<?php elseif ($accordClient): ?><span class="badge badge-green">Cotation acceptée</span><?php else: ?><span class="badge badge-orange">Pas encore accepté</span><?php endif; ?></span></div>
      <div class="info-row"><span class="label">Créé</span><span><?= date('d/m/Y H:i', strtotime($bon['created_at'])) ?><?= !empty($bon['createur_nom']) ? ' · ' . View::e($bon['createur_nom']) : '' ?></span></div>
      <?php if (!empty($bon['envoye_le'])): ?><div class="info-row"><span class="label">Marqué envoyé</span><span><?= date('d/m/Y H:i', strtotime($bon['envoye_le'])) ?></span></div><?php endif; ?>
      <?php if (!empty($bon['confirme_le'])): ?><div class="info-row"><span class="label">Confirmé</span><span><?= date('d/m/Y H:i', strtotime($bon['confirme_le'])) ?></span></div><?php endif; ?>
      <?php if (!empty($bon['notes_internes'])): ?><div style="margin-top:10px;font-size:13px"><strong>Notes internes</strong><div style="white-space:pre-wrap"><?= View::e($bon['notes_internes']) ?></div></div><?php endif; ?>
    </div>
    <div class="card" style="background:#fafafa;font-size:13px;color:#666">Réception de la marchandise et factures du fournisseur : <strong>à connecter</strong>. Ce bon n’est pas une facture et n’enregistre aucun paiement.</div>
  </div>
</div>
