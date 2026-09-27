<?php use App\Core\View; use App\Models\Offre; use App\Models\Client; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Comparateur d'offres</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<?php
function afficheOuNonRenseigne($valeur, string $suffixe = ''): string
{
    if ($valeur === null || $valeur === '' || (is_numeric($valeur) && (float) $valeur == 0.0 && $suffixe !== '')) {
        return '<span style="color:#9ca3af;font-style:italic">Non renseigné</span>';
    }
    return View::e((string) $valeur) . $suffixe;
}
?>

<?php if (count($offres) < 2): ?>
  <div class="card"><div class="alert alert-erreur" style="margin:0">Ce dossier a <?= count($offres) ?> offre(s) reçue(s) exploitable(s). Une comparaison fiable nécessite au moins deux offres — enregistrez d'autres offres reçues avant de comparer, sinon vous risqueriez de choisir sans point de comparaison réel.</div></div>
<?php endif; ?>

<?php if (empty($offres)): ?>
  <div class="card"><div class="empty-state">Aucune offre reçue pour ce dossier pour le moment. Envoyez des consultations aux fournisseurs depuis le dossier, puis enregistrez leurs offres ici.</div></div>
<?php else: ?>

  <?php if ($decisionExistante && $peutValider): ?>
  <div class="card" style="background:#fffbeb;border:1px solid #fde68a">
    <details>
      <summary style="cursor:pointer;font-weight:600;color:#92400e">Revenir sur la décision déjà prise (réservé à Propriétaire/Achats)</summary>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/revenir" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <div class="form-group">
          <label>Motif (obligatoire)</label>
          <textarea name="motif_revenir" rows="2" required placeholder="ex: le fournisseur retenu ne peut finalement pas honorer le délai annoncé"></textarea>
        </div>
        <button type="submit" class="btn btn-secondary">Annuler la décision et recomparer</button>
      </form>
    </details>
  </div>
  <?php endif; ?>

  <div class="card" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:13px;color:#1e3a8a">
    Le meilleur résultat (prix marchandises et coût rendu les plus bas) est surligné <strong>par devise</strong> — les montants dans des devises différentes ne sont pas encore convertis automatiquement (multi-devises prévu plus tard), donc comparez d'abord les offres dans la même devise.
  </div>

  <div style="overflow-x:auto">
  <table>
    <thead>
      <tr>
        <th>Fournisseur</th>
        <th>Réf. offre</th>
        <th>Conformité</th>
        <th>Devise</th>
        <th>Prix marchandises</th>
        <th>Coût rendu estimé</th>
        <th>Incoterm</th>
        <th>Délai</th>
        <th>Validité</th>
        <th>Note fournisseur</th>
        <th>Statut</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($offres as $o):
      $devise = $o['devise'] ?: '—';
      $coutRendu = Offre::coutRendu($o);
      $estMeilleurPrix = isset($meilleurPrixParDevise[$devise]) && (float) $o['montant_total'] === $meilleurPrixParDevise[$devise];
      $estMeilleurCoutRendu = isset($meilleurCoutRenduParDevise[$devise]) && $coutRendu === $meilleurCoutRenduParDevise[$devise];
      $noteFournisseur = \App\Models\Fournisseur::noteGlobale($o);
    ?>
      <tr style="<?= $o['statut'] === 'retenue' ? 'background:#f0fdf4' : '' ?>">
        <td><strong><?= View::e($o['fournisseur_nom']) ?></strong></td>
        <td><?= View::e($o['reference']) ?></td>
        <td><?= $o['conformite_technique'] ? View::e(Offre::CONFORMITE[$o['conformite_technique']] ?? $o['conformite_technique']) : afficheOuNonRenseigne(null) ?></td>
        <td><?= View::e($devise) ?></td>
        <td style="<?= $estMeilleurPrix ? 'background:#dcfce7;font-weight:600' : '' ?>"><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?> <?= View::e($devise) ?></td>
        <td style="<?= $estMeilleurCoutRendu ? 'background:#dcfce7;font-weight:600' : '' ?>"><?= number_format($coutRendu, 2, ',', ' ') ?> <?= View::e($devise) ?></td>
        <td><?= afficheOuNonRenseigne($o['incoterm_negocie']) ?></td>
        <td><?= afficheOuNonRenseigne($o['delai_livraison']) ?></td>
        <td><?= $o['validite_offre'] ? date('d/m/Y', strtotime($o['validite_offre'])) : afficheOuNonRenseigne(null) ?></td>
        <td><?= $noteFournisseur !== null ? number_format($noteFournisseur, 1) . '/5' : afficheOuNonRenseigne(null) ?></td>
        <td>
          <span class="badge <?= $o['statut'] === 'retenue' ? 'badge-green' : ($o['statut'] === 'rejetee' ? 'badge-red' : 'badge-blue') ?>">
            <?= Offre::STATUTS[$o['statut']] ?? $o['statut'] ?>
          </span>
        </td>
        <td>
          <?php if ($o['statut'] !== 'retenue' && !$decisionExistante && $peutValider): ?>
          <details>
            <summary style="cursor:pointer" class="btn btn-sm">Retenir…</summary>
            <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir" style="margin-top:8px;min-width:220px">
              <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
              <input type="hidden" name="offre_id" value="<?= $o['id'] ?>">
              <label style="font-size:12px">Motif de la décision (obligatoire)</label>
              <textarea name="motif_decision" rows="2" required placeholder="ex: meilleur coût rendu, délai compatible"></textarea>
              <button type="submit" class="btn btn-sm" style="margin-top:6px">Confirmer — retenir cette offre</button>
            </form>
          </details>
          <?php elseif ($o['statut'] === 'retenue'): ?>
            <span style="font-size:12px;color:#065f46;font-weight:600">✓ Retenue</span>
            <?php if (!empty($o['motif_decision'])): ?>
              <div style="font-size:11px;color:#666;margin-top:4px;max-width:220px"><?= View::e($o['motif_decision']) ?></div>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td colspan="12" style="background:#fafbfc">
          <details>
            <summary style="cursor:pointer;font-size:12px;color:#666;padding:4px 0">Détail (coût rendu, logistique, articles, conditions)</summary>
            <div style="padding:8px 0">
              <div class="form-row" style="font-size:12px">
                <div><span class="label">Transport</span><br><?= afficheOuNonRenseigne($o['transport_montant']) ?></div>
                <div><span class="label">Assurance</span><br><?= afficheOuNonRenseigne($o['assurance_montant']) ?></div>
                <div><span class="label">Emballage</span><br><?= afficheOuNonRenseigne($o['emballage_montant']) ?></div>
                <div><span class="label">Douane</span><br><?= afficheOuNonRenseigne($o['douane_montant']) ?></div>
                <div><span class="label">Dédouanement</span><br><?= afficheOuNonRenseigne($o['dedouanement_montant']) ?></div>
                <div><span class="label">Autres frais</span><br><?= afficheOuNonRenseigne($o['autres_frais_montant']) ?></div>
              </div>
              <div class="form-row" style="font-size:12px;margin-top:8px">
                <div><span class="label">Pays d'origine</span><br><?= afficheOuNonRenseigne($o['pays_origine']) ?></div>
                <div><span class="label">Lieu de départ</span><br><?= afficheOuNonRenseigne($o['lieu_depart']) ?></div>
                <div><span class="label">Qté minimale</span><br><?= afficheOuNonRenseigne($o['quantite_min']) ?></div>
                <div><span class="label">Disponibilité</span><br><?= afficheOuNonRenseigne($o['disponibilite']) ?></div>
                <div><span class="label">Poids / colis / volume</span><br><?= afficheOuNonRenseigne($o['poids_kg'], ' kg') ?> — <?= afficheOuNonRenseigne($o['nombre_colis']) ?> colis — <?= afficheOuNonRenseigne($o['volume_m3'], ' m³') ?></div>
                <div><span class="label">Conditions de paiement</span><br><?= $o['conditions_paiement'] ? View::e(Client::CONDITIONS_PAIEMENT[$o['conditions_paiement']] ?? $o['conditions_paiement']) : afficheOuNonRenseigne(null) ?></div>
              </div>
              <div style="font-size:12px;margin-top:8px"><span class="label">Garantie</span><br><?= afficheOuNonRenseigne($o['garantie']) ?></div>

              <?php if (!empty($itemsByOffre[$o['id']])): ?>
              <table style="margin-top:10px;box-shadow:none">
                <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Prix unitaire</th><th>Montant</th></tr></thead>
                <tbody>
                <?php foreach ($itemsByOffre[$o['id']] as $item): ?>
                  <tr>
                    <td><?= View::e($item['designation']) ?></td>
                    <td><?= View::e((string) $item['quantite']) ?></td>
                    <td><?= View::e($item['unite']) ?></td>
                    <td><?= $item['prix_unitaire'] !== null ? number_format((float) $item['prix_unitaire'], 2, ',', ' ') : '—' ?></td>
                    <td><?= $item['montant'] !== null ? number_format((float) $item['montant'], 2, ',', ' ') : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
              <?php endif; ?>
            </div>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>
