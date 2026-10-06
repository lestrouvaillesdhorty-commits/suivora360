<?php use App\Core\View; use App\Models\Offre; use App\Models\Client; use App\Models\Dossier; ?>
<?php
// [modifié 06/10, étape 2 du découpage Dossiers] Le lien de retour isolé
// et le h1/subtitle propres à cette page sont remplacés par la coquille
// commune de la fiche Dossier (_dossier_header.php) + les sous-onglets de
// "Achats et offres" (_achats_subtabs.php, "Comparaison" actif) — cette
// page garde sa propre route (/dossiers/{id}/comparateur, accès direct
// depuis le menu, décision du 29/09) mais s'affiche désormais comme un
// sous-onglet intégré plutôt qu'une page isolée.
$onglet = 'achats';
$sousOngletAchats = 'comparaison';
$nbOffres = count($offres);
?>
<?php include __DIR__ . '/../folders/_dossier_header.php'; ?>
<?php include __DIR__ . '/../folders/_achats_subtabs.php'; ?>

<?php
function afficheOuNonRenseigne($valeur, string $suffixe = ''): string
{
    if ($valeur === null || $valeur === '' || (is_numeric($valeur) && (float) $valeur == 0.0 && $suffixe !== '')) {
        return '<span style="color:#9ca3af;font-style:italic">Non renseigné</span>';
    }
    return View::e((string) $valeur) . $suffixe;
}
$typeDossier = $dossier['type_dossier'] ?? 'autre';
$libelleFournisseur = Dossier::libelleFournisseur($typeDossier);
?>

<?php if (count($offres) < 2): ?>
  <div class="card"><div class="alert alert-erreur" style="margin:0">Ce dossier a <?= count($offres) ?> offre(s) reçue(s) exploitable(s). Une comparaison fiable nécessite au moins deux offres — enregistrez d'autres offres reçues avant de comparer, sinon vous risqueriez de choisir sans point de comparaison réel.</div></div>
<?php endif; ?>

<?php if (empty($offres)): ?>
  <div class="card"><div class="empty-state">Aucune offre reçue pour ce dossier pour le moment. Envoyez des consultations aux <?= View::e(mb_strtolower($libelleFournisseur)) ?>s depuis le dossier, puis enregistrez leurs offres ici.</div></div>
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

  <?php
    // Tableau transposé (décision Marie Laure, 29/09) : les critères en
    // lignes, chaque offre en colonne — pour comparer toutes les offres sur
    // un même critère en lisant horizontalement.
    $labelCol = 220;
  ?>
  <div style="overflow-x:auto">
  <table style="table-layout:fixed">
    <colgroup>
      <col style="width:<?= $labelCol ?>px">
      <?php foreach ($offres as $o): ?><col style="width:200px"><?php endforeach; ?>
    </colgroup>
    <thead>
      <tr>
        <th style="position:sticky;left:0;background:#fafbfd;z-index:1">Critère</th>
        <?php foreach ($offres as $o): ?>
          <th style="<?= $o['statut'] === 'retenue' ? 'background:#f0fdf4' : '' ?>">
            <div style="text-transform:none;font-size:13.5px;font-weight:700;color:#111"><?= View::e($o['fournisseur_nom']) ?></div>
            <div style="text-transform:none;font-weight:400;color:#888">Réf. <?= View::e($o['reference']) ?></div>
            <?php if (!empty($o['created_by'])): ?>
              <div style="text-transform:none;font-weight:400;color:#888">Saisie par <?= View::e(\App\Models\Utilisateur::nameOf((int) $o['created_by'])) ?></div>
            <?php endif; ?>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <tr>
        <th style="position:sticky;left:0;background:#fff"><?= View::e($libelleFournisseur) ?> — statut</th>
        <?php foreach ($offres as $o): ?>
          <td style="<?= $o['statut'] === 'retenue' ? 'background:#f0fdf4' : '' ?>">
            <span class="badge <?= $o['statut'] === 'retenue' ? 'badge-green' : ($o['statut'] === 'rejetee' ? 'badge-red' : 'badge-blue') ?>">
              <?= Offre::STATUTS[$o['statut']] ?? $o['statut'] ?>
            </span>
          </td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Conformité</th>
        <?php foreach ($offres as $o): ?>
          <td><?= $o['conformite_technique'] ? View::e(Offre::CONFORMITE[$o['conformite_technique']] ?? $o['conformite_technique']) : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Devise</th>
        <?php foreach ($offres as $o): ?>
          <td><?= View::e($o['devise'] ?: '—') ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Prix marchandises</th>
        <?php foreach ($offres as $o):
          $devise = $o['devise'] ?: '—';
          $estMeilleurPrix = isset($meilleurPrixParDevise[$devise]) && (float) $o['montant_total'] === $meilleurPrixParDevise[$devise];
        ?>
          <td style="<?= $estMeilleurPrix ? 'background:#dcfce7;font-weight:600' : '' ?>"><?= number_format((float) $o['montant_total'], 2, ',', ' ') ?> <?= View::e($devise) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Coût rendu estimé</th>
        <?php foreach ($offres as $o):
          $devise = $o['devise'] ?: '—';
          $coutRendu = Offre::coutRendu($o);
          $estMeilleurCoutRendu = isset($meilleurCoutRenduParDevise[$devise]) && $coutRendu === $meilleurCoutRenduParDevise[$devise];
        ?>
          <td style="<?= $estMeilleurCoutRendu ? 'background:#dcfce7;font-weight:600' : '' ?>"><?= number_format($coutRendu, 2, ',', ' ') ?> <?= View::e($devise) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Incoterm</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['incoterm_negocie']) ?></td>
        <?php endforeach; ?>
      </tr>

      <?php if ($typeDossier === 'transport_logistique'): ?>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Mode de transport</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['mode_transport'] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php elseif ($typeDossier === 'prestation_entreprise'): ?>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Périmètre de mission proposé</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['perimetre_mission'] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endif; ?>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Délai</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['delai_livraison']) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Validité de l'offre</th>
        <?php foreach ($offres as $o): ?>
          <td><?= $o['validite_offre'] ? date('d/m/Y', strtotime($o['validite_offre'])) : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Note <?= View::e(mb_strtolower($libelleFournisseur)) ?></th>
        <?php foreach ($offres as $o):
          $noteFournisseur = \App\Models\Fournisseur::noteGlobale($o);
        ?>
          <td><?= $noteFournisseur !== null ? number_format($noteFournisseur, 1) . '/5' : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>

      <tr><td colspan="<?= count($offres) + 1 ?>" style="background:#fafbfc;padding:6px 14px;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.04em">Coût rendu — détail des frais complémentaires</td></tr>

      <?php foreach ([
        'transport_montant' => 'Transport',
        'assurance_montant' => 'Assurance',
        'emballage_montant' => 'Emballage',
        'douane_montant' => 'Douane / droits estimés',
        'dedouanement_montant' => 'Dédouanement',
        'autres_frais_montant' => 'Autres frais',
      ] as $champ => $label): ?>
      <tr>
        <th style="position:sticky;left:0;background:#fff"><?= $label ?></th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o[$champ] ?? null) ?></td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>

      <tr><td colspan="<?= count($offres) + 1 ?>" style="background:#fafbfc;padding:6px 14px;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.04em">Logistique et conditions</td></tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Pays d'origine</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['pays_origine']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Lieu de départ</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['lieu_depart']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Quantité minimale</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['quantite_min']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Disponibilité</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['disponibilite']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Poids / colis / volume</th>
        <?php foreach ($offres as $o): ?>
          <td style="font-size:12px"><?= afficheOuNonRenseigne($o['poids_kg'], ' kg') ?> — <?= afficheOuNonRenseigne($o['nombre_colis']) ?> colis — <?= afficheOuNonRenseigne($o['volume_m3'], ' m³') ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Conditions de paiement</th>
        <?php foreach ($offres as $o): ?>
          <td><?= $o['conditions_paiement'] ? View::e(Client::CONDITIONS_PAIEMENT[$o['conditions_paiement']] ?? $o['conditions_paiement']) : afficheOuNonRenseigne(null) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Garantie</th>
        <?php foreach ($offres as $o): ?>
          <td><?= afficheOuNonRenseigne($o['garantie']) ?></td>
        <?php endforeach; ?>
      </tr>
      <tr>
        <th style="position:sticky;left:0;background:#fff">Articles</th>
        <?php foreach ($offres as $o): ?>
          <td>
            <?php if (!empty($itemsByOffre[$o['id']])): ?>
              <details>
                <summary style="cursor:pointer;font-size:12px;color:#4f46e5">Voir le détail (<?= count($itemsByOffre[$o['id']]) ?>)</summary>
                <table style="margin-top:8px;box-shadow:none;font-size:11.5px">
                  <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Px unit.</th><th>Montant</th></tr></thead>
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
              </details>
            <?php else: ?>
              <span style="color:#9ca3af;font-style:italic">Aucun</span>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>

      <tr>
        <th style="position:sticky;left:0;background:#fff">Décision</th>
        <?php foreach ($offres as $o): ?>
          <td>
            <?php if ($o['statut'] !== 'retenue' && !$decisionExistante && $peutValider): ?>
            <details>
              <summary style="cursor:pointer" class="btn btn-sm">Retenir…</summary>
              <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/comparateur/retenir" style="margin-top:8px;min-width:200px">
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
                <div style="font-size:11px;color:#666;margin-top:4px"><?= View::e($o['motif_decision']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:#9ca3af;font-style:italic">—</span>
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    </tbody>
  </table>
  </div>
<?php endif; ?>
