<?php
session_start();

require_once("loginBdd.php");

$flashAvisMessage = '';
if (isset($_SESSION['avis_message'])) {
    $flashAvisMessage = $_SESSION['avis_message'];
    unset($_SESSION['avis_message']);

    $_SESSION['toast_message'] = $flashAvisMessage;
    if (stripos($flashAvisMessage, 'succès') !== false || stripos($flashAvisMessage, 'ajouté') !== false || stripos($flashAvisMessage, 'modifié') !== false) {
        $_SESSION['toast_type'] = 'ajoute';
    } else {
        $_SESSION['toast_type'] = 'failed';
    }
}

$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_GET['id_produit'])) {
    $id_produit = $_GET['id_produit'];
}else{
    echo "ERROR : avec id produit";
}

$sql_details = "
    SELECT
        P.nom_produit, P.description_prod, P.prix_ht, P.dep_origine, P.ville_origine, P.pays_origine, P.nb_ventes,
        I.nom_fichier AS image_nom, I.chemin AS image_chemin, I.extension AS image_ext, I.alt AS image_alt,
        C.nom_categ as nom_categ,
        V.id_vendeur, V.nom_entreprise, V.description_vendeur,
        S.quantite_dispo,
        T.taux_tva,
        R.taux_remise,
        PR.nom_promotion,
        PR.date_fin,
        R.id_remise,
        COALESCE(avg_a.moyenne, 0) AS moyenne,
        COALESCE(count_a.nb_avis, 0) AS nbAvis
    FROM
        sae._produit P
    JOIN sae._image I ON P.id_image = I.id_image
    JOIN sae._categorieProduit C ON P.id_categ = C.id_categ
    JOIN sae._vendeur V ON P.id_vendeur = V.id_vendeur
    LEFT JOIN sae._stock S ON P.id_produit = S.id_produit AND P.id_vendeur = V.id_vendeur
    LEFT JOIN sae._tva T ON P.id_produit = T.id_produit
    LEFT JOIN sae._remise R ON P.id_produit = R.id_produit
    LEFT JOIN sae._promotion PR ON P.id_produit = PR.id_produit
    LEFT JOIN (SELECT id_produit, AVG(note) AS moyenne FROM sae._avis GROUP BY id_produit) avg_a
           ON avg_a.id_produit = p.id_produit
    LEFT JOIN (SELECT id_produit, COUNT(*) AS nb_avis FROM sae._avis GROUP BY id_produit) count_a
           ON count_a.id_produit = p.id_produit
    WHERE
        P.id_produit = :id_produit;
";

$stmt = $pdo->prepare($sql_details);
$stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
$stmt->execute();
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
        SELECT
            A.id_avis,
            A.note,
            A.titre_avis,
            A.commentaire,
            A.date_avis,
            A.votes_utiles,
            A.votes_inutiles,
            A.signale,
            A.epingle,
            A.avis_verif,
            R.reponse_vendeur,
            R.date_reponse,

            C.nom AS client_nom,
            C.prenom AS client_prenom,
            CPT.login AS client_login,

            I_CLIENT.chemin AS profil_chemin,
            I_CLIENT.nom_fichier AS profil_nom_fichier,
            I_CLIENT.extension AS profil_extension,

            I_AVIS.chemin AS avis_chemin,
            I_AVIS.nom_fichier AS avis_nom_fichier,
            I_AVIS.extension AS avis_extension

        FROM
            sae._avis A

        JOIN
            sae._client C ON A.id_client = C.id_client
        JOIN
            sae._compte CPT ON C.id_client = CPT.id_compte
        LEFT JOIN
            sae._image I_CLIENT ON CPT.id_image = I_CLIENT.id_image

        LEFT JOIN
            sae._image I_AVIS ON A.id_image = I_AVIS.id_image

        LEFT JOIN
            sae._reponse R ON A.id_avis = R.id_avis

        WHERE
            A.id_produit = :id_produit

        ORDER BY
            A.epingle DESC NULLS LAST,
            A.date_avis DESC;");

$stmt->bindParam(':id_produit', $id_produit, PDO::PARAM_INT);
$stmt->execute();
$avisProd = $stmt->fetchAll(PDO::FETCH_ASSOC);

$avisUtilisateur = null;
if (isset($_SESSION['id_compte']) || isset($_SESSION['id_client'])) {
    $id_client_connecte = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : $_SESSION['id_compte'];
    $stmtUserAvis = $pdo->prepare("SELECT * FROM sae._avis WHERE id_produit = :id_produit AND id_client = :id_client LIMIT 1");
    $stmtUserAvis->execute([':id_produit' => $id_produit, ':id_client' => $id_client_connecte]);
    $avisUtilisateur = $stmtUserAvis->fetch(PDO::FETCH_ASSOC);
}

if (!isset($_SESSION['signaled_avis'])) {
    $_SESSION['signaled_avis'] = [];
}

function ensureDefaultImage(PDO $pdo) {

    $stmt = $pdo->prepare("SELECT id_image FROM sae._image WHERE nom_fichier = :nom LIMIT 1");
    $stmt->execute([':nom' => 'default_image']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['id_image'])) return $row['id_image'];

    $defaultChemin = 'media/universel/';
    $defaultExt = '.png';
    $defaultNom = 'default_image';
    $stmtIns = $pdo->prepare("INSERT INTO sae._image (nom_fichier, chemin, extension, alt) VALUES (:nom, :chemin, :ext, :alt) RETURNING id_image");
    $stmtIns->execute([
        ':nom' => $defaultNom,
        ':chemin' => $defaultChemin,
        ':ext' => $defaultExt,
        ':alt' => 'Image par défaut'
    ]);
    $r = $stmtIns->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['id_image'])) return $r['id_image'];

    $stmtAny = $pdo->query("SELECT id_image FROM sae._image LIMIT 1");
    $any = $stmtAny->fetch(PDO::FETCH_ASSOC);
    if ($any && !empty($any['id_image'])) return $any['id_image'];

    return null;
}

if (!$produit) {
    die("ERROR : Produit introuvable.");
}

$quantiteDansPanier = 0;

if (isset($_SESSION['id_compte'])) {
    $stmtPanier = $pdo->prepare("
        SELECT lc.quantite_prod
        FROM sae._ligneCommande lc
        JOIN sae._commande c ON lc.num_commande = c.num_commande
        WHERE c.id_client = :id_client AND c.statut_commande = 'En attente de paiement' AND lc.id_produit = :id_produit
    ");
    $stmtPanier->execute(['id_client' => $_SESSION['id_compte'], 'id_produit' => $id_produit]);
    $qte = $stmtPanier->fetchColumn();
    if ($qte !== false) {
        $quantiteDansPanier = intval($qte);
    }
} elseif (isset($_SESSION['tmp_panier'][$id_produit])) {
    $quantiteDansPanier = intval($_SESSION['tmp_panier'][$id_produit]['qtt_panier']);
}

$stockRestant = intval($produit['quantite_dispo']) - $quantiteDansPanier;

$extensions_valides = ['.jpg', '.jpeg', '.png', '.webp'];
$chemin_url_base = 'media/produits/';
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="descriptionProduitClientStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <title>Document</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
</head>
<body>
    <?php include "header.php";?>
    <main>
        <a href="catalogue.php">← Reprendre mes achats</a>
        <p>Accueil ><?php echo $produit['nom_categ'] . ">" . $produit['nom_produit'];?></p>

        <div class="main">
            <section class="images">
                <img src=<?php echo "media/produits/produit{$id_produit}_1{$produit['image_ext']}"?> alt="grande image produit">
                <div class="img_secondaires">
                    <?php
                    for ($i = 2; $i <= 4; $i++) {
                        $image_trouvee = false;
                        foreach ($extensions_valides as $ext) {
                            $nom_fichier = "produit{$id_produit}_{$i}{$ext}";
                            $chemin_physique_complet = $chemin_url_base . $nom_fichier;
                            if (file_exists($chemin_physique_complet)) {
                                $chemin_url_complet = $chemin_url_base . $nom_fichier;
                                ?>
                                <img src="<?php echo htmlspecialchars($chemin_url_complet); ?>" alt="Vignette produit secondaire <?php echo $i; ?>">
                                <?php
                                $image_trouvee = true;
                                break;
                            }
                        }
                    }
                    ?>
                </div>
            </section>

            <section class="infos_produit">
                <h2><?php echo $produit['nom_produit'];?></h2>

                <div class="avis">
                    <?php
                    $moyenne_reelle = $produit['moyenne'];
                    $etoiles_pleines = floor($moyenne_reelle);
                    ?>
                    <div class="etoiles"><?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $etoiles_pleines) {
                                ?>
                                <img src="media/universel/etoile_pleine.png" alt="*">
                                <?php
                            } else {
                                ?>
                                <img src="media/universel/etoile_vide.png" alt=" ">
                                <?php
                            }
                        }
                    ?></div>
                    <?php
                        $compteurAvis = $produit['nbavis'];
                    ?>
                    <a href=""><?php echo $compteurAvis; ?></a>
                </div>

                <p><?php echo $produit['description_prod'];?></p>

                <?php if (!empty($produit['nom_promotion'])): ?>
                    <div class="promotion-details" >
                        <p class="promo-nom"> Promotion : <?php echo htmlspecialchars($produit['nom_promotion']); ?></p>
                        <p class="promo-valeur">Réduction : -<?php echo htmlspecialchars($produit['taux_remise']*100); ?>%</p>
                        <p class="promo-date">Promotion jusqu'au : <?php echo htmlspecialchars($produit['date_fin']); ?> </p>
                    </div>
                <?php endif; ?>

                <?php
                $prix_ht = floatval($produit['prix_ht']);

                $raw_tva = !empty($produit['taux_tva']) ? floatval($produit['taux_tva']) : 20.0;
                $taux_tva_calc = ($raw_tva > 1) ? $raw_tva / 100 : $raw_tva;

                $prix_ttc_base = $prix_ht * (1 + $taux_tva_calc);

                $taux_remise = !empty($produit['taux_remise']) ? floatval($produit['taux_remise']) : 0;

                $prix_final = $prix_ttc_base;
                $has_promo = false;

                if ($taux_remise > 0) {
                    $montant_reduction = $prix_ttc_base * $taux_remise;
                    $prix_final = $prix_ttc_base - $montant_reduction;
                    $has_promo = true;
                }
                ?>

                <?php if ($has_promo): ?>
                    <h2 style="color: #e74c3c;">
                        <?php echo number_format($prix_final, 2, ',', ' '); ?> €
                        <span style="font-size: 0.7em; color: gray; text-decoration: line-through; margin-left: 10px;">
                            <?php echo number_format($prix_ttc_base, 2, ',', ' '); ?> €
                        </span>
                    </h2>
                <?php else: ?>
                    <h2><?php echo number_format($prix_final, 2, ',', ' '); ?> €</h2>
                <?php endif; ?>


                <form action="ajouterPanier.php" method="post">

                    <input type="hidden" name="id_produit" value="<?php echo htmlspecialchars($id_produit); ?>">
                    <input type="hidden" name="redirect_url" value="<?= $_SERVER['REQUEST_URI'] ?>">
                    <div class="actions">
                        <div class="actions_haut">
                            <?php if ($stockRestant > 0): ?>
                                <select name="quantite" id="quantite_select">
                                    <?php for($i=1; $i <= $stockRestant; $i++): ?>
                                        <option value="<?php echo $i?>"><?php echo $i?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>

                            <a href="">♥ Favoris</a>
                        </div>

                        <div class="actions_bas">
                            <?php if ($produit['quantite_dispo'] <= 0): ?>
                                <button type="button" class="btn-panier" disabled style="opacity: 0.6; cursor: not-allowed;">Rupture de stock</button>
                            <?php elseif ($stockRestant <= 0): ?>
                                <button type="button" class="btn-panier" disabled style="opacity: 0.6; cursor: not-allowed;">Stock max atteint</button>
                            <?php else: ?>
                                <button type="submit" class="btn-panier">Ajouter au panier</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </section>
        </div>
        <section id="avis_client" class="avis_client">
            <h3>Avis Clients (<?php echo $produit['nbavis']; ?>)</h3>

            <?php if (isset($_SESSION['id_compte']) || isset($_SESSION['id_client'])): ?>
                <?php if ($avisUtilisateur): ?>
                    <div class="form-nouvel-avis">
                        <h4>Votre avis sur ce produit</h4>
                        <p style="margin-bottom: 15px; color: #555;">Vous avez déjà laissé un avis pour ce produit. Vous pouvez le modifier ou le supprimer.</p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="modifier_avis.php?id_avis=<?= $avisUtilisateur['id_avis'] ?>&id_produit=<?= $id_produit ?>" class="btn-modifier-avis">Modifier mon avis</a>
                            <form method="post" action="supprimer_avis.php" id="form-supprimer-avis" style="display: inline-block; margin: 0;">
                                <input type="hidden" name="id_avis" value="<?= $avisUtilisateur['id_avis'] ?>">
                                <input type="hidden" name="id_produit" value="<?= $id_produit ?>">
                                <button type="button" class="btn-supprimer-avis" id="btn-open-modal-supprimer">Supprimer mon avis</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-nouvel-avis">
                        <h4>Laisser un avis</h4>
                            <?php if(!empty($flashAvisMessage)): ?>
                                <p class="error"><?= htmlspecialchars($flashAvisMessage) ?></p>
                            <?php elseif(!empty($message)): ?>
                                <p class="error"><?= htmlspecialchars($message) ?></p>
                            <?php endif; ?>
                            <form action="ajouter_avis.php?id_produit=<?php echo htmlspecialchars($id_produit); ?>" method="post" enctype="multipart/form-data">
                            <div class="rating">
                                <input type="radio" name="note" id="star5" value="5" required><label for="star5" title="5 étoiles"></label>
                                <input type="radio" name="note" id="star4" value="4"><label for="star4" title="4 étoiles"></label>
                                <input type="radio" name="note" id="star3" value="3"><label for="star3" title="3 étoiles"></label>
                                <input type="radio" name="note" id="star2" value="2"><label for="star2" title="2 étoiles"></label>
                                <input type="radio" name="note" id="star1" value="1"><label for="star1" title="1 étoile"></label>
                            </div>

                            <div>
                                <label for="titre_avis">Titre</label>
                                <input type="text" name="titre" id="titre_avis" required placeholder="Titre de votre avis">
                            </div>

                            <div>
                                <label for="commentaire_avis">Commentaire</label>
                                <textarea name="commentaire" id="commentaire_avis" required placeholder="Votre expérience..."></textarea>
                            </div>

                            <div>
                                <label for="image_avis">Photo (optionnel)</label>
                                <input type="file" name="image_avis" id="image_avis" accept="image/*">
                            </div>

                            <div>
                                <button type="submit">Publier l'avis</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>Vous devez être <a href="connexion.php">connecté</a> pour laisser un avis.</p>
            <?php endif; ?>

            <?php
            if (!empty($avisProd)):
                foreach($avisProd as $avis):
                    $profil_img_src = $avis['profil_chemin'] . $avis['profil_nom_fichier'] . $avis['profil_extension'];
                    $avis_img_src = $avis['avis_chemin'] . $avis['avis_nom_fichier'] . $avis['avis_extension'];
            ?>

            <div class="avis-item<?= $avis['epingle'] ? ' avis-epingle' : '' ?>">
                <div class="avis-header">
                    <div class="avis-profil-info">
                        <img src="<?= htmlspecialchars($profil_img_src) ?>" alt="Profil de <?= htmlspecialchars($avis['client_prenom']) ?>" class="avis-profil-img">
                        <div class="avis-meta">
                            <strong><?= htmlspecialchars($avis['client_prenom'] . ' ' . $avis['client_nom'][0] . '.') ?></strong>
                            <p>Connecté: <?= htmlspecialchars($avis['client_login']) ?></p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?php if ($avis['epingle']): ?>
                            <span class="badge-epingle" style="background: #ffd700; color: #000; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold;"> Épinglé</span>
                        <?php endif; ?>
                        <span class="avis-date">Posté le <?= date('d/m/Y', strtotime($avis['date_avis'])) ?></span>
                    </div>
                </div>

                <div class="avis-note">
                    <?php
                    $etoiles_pleines = $avis['note'];?>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++):
                            $etoile_src = ($i <= $etoiles_pleines) ? "media/universel/etoile_pleine.png" : "media/universel/etoile_vide.png";
                        ?>
                            <img src="<?= $etoile_src ?>" alt="Étoile <?= $i <= $etoiles_pleines ? 'pleine' : 'vide' ?>">
                        <?php endfor; ?>
                    </div>
                    <strong><?= htmlspecialchars(html_entity_decode($avis['titre_avis'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT, 'UTF-8') ?></strong>
                </div>

                <div class="avis-body">
                    <p><?= nl2br(htmlspecialchars(html_entity_decode($avis['commentaire'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_COMPAT, 'UTF-8')) ?></p>

                    <?php if ($avis['avis_nom_fichier'] !== 'default_image'): ?>
                        <img src="<?= htmlspecialchars($avis_img_src) ?>" alt="Image jointe à l'avis" class="avis-image-jointe">
                    <?php endif; ?>
                </div>

                <div class="avis-footer">
                    <span>Avis vérifié: <?= $avis['avis_verif'] ? 'Oui' : 'Non' ?></span>
                    <form method="post" action="voter_avis.php" class="vote-form" data-avis-id="<?= $avis['id_avis'] ?>" style="display:inline-block;">
                        <input type="hidden" name="id_avis" value="<?= htmlspecialchars($avis['id_avis']) ?>">
                        <input type="hidden" name="action" value="utile">
                        <button type="submit" class="btn-vote btn-vote-utile" data-vote-type="utile">
                             Utile (<span class="vote-count-utile"><?= htmlspecialchars($avis['votes_utiles']) ?></span>)
                        </button>
                    </form>

                    <form method="post" action="voter_avis.php" class="vote-form" data-avis-id="<?= $avis['id_avis'] ?>" style="display:inline-block;">
                        <input type="hidden" name="id_avis" value="<?= htmlspecialchars($avis['id_avis']) ?>">
                        <input type="hidden" name="action" value="inutile">
                        <button type="submit" class="btn-vote btn-vote-inutile" data-vote-type="inutile">
                             Inutile (<span class="vote-count-inutile"><?= htmlspecialchars($avis['votes_inutiles']) ?></span>)
                        </button>
                    </form>

                    <?php if (isset($_SESSION['id_compte']) || isset($_SESSION['id_client'])): ?>
                    <?php
                    $deja_signale = $avis['signale'] || (isset($_SESSION['signaled_avis']) && in_array($avis['id_avis'], $_SESSION['signaled_avis']));
                    ?>
                    <button type="button"
                            class="btn-signaler"
                            onclick="openSignalementModal(<?= $avis['id_avis'] ?>, <?= $id_produit ?>)"
                            <?= $deja_signale ? 'disabled style="opacity: 0.6; cursor: not-allowed;"' : '' ?>>
                        <?= $deja_signale ? 'Déjà signalé' : 'Signaler' ?>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($avis['reponse_vendeur'])): ?>
                    <div class="reponse-vendeur-client">
                        <div class="reponse-vendeur-header">
                            <strong>Réponse du vendeur</strong>
                            <span class="reponse-vendeur-date">Le <?= date('d/m/Y', strtotime($avis['date_reponse'])) ?></span>
                        </div>
                        <p class="reponse-vendeur-text"><?= nl2br(htmlspecialchars($avis['reponse_vendeur'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php endforeach;
            else: ?>
                <p>Aucun avis n'a encore été posté pour ce produit.</p>
            <?php endif; ?>
        </section>
    </main>

    <div id="modal-signalement" class="modal-confirm" style="display: none;">
        <div class="modal-confirm-overlay" onclick="closeSignalementModal()"></div>
        <div class="modal-confirm-content">
            <form method="post" action="signaler_avis.php" id="form-signalement">
                <div class="modal-confirm-header">
                    <h3>Signaler cet avis</h3>
                </div>
                <div class="modal-confirm-body">
                    <p style="margin-bottom: 15px;">Pourquoi signalez-vous cet avis ?</p>
                    <input type="hidden" name="id_avis" id="modal-id-avis" value="">
                    <input type="hidden" name="id_produit" id="modal-id-produit" value="">
                    <textarea name="raison" id="raison-signalement" rows="4" placeholder="Exemple : Contenu offensant, Fausse information, Spam, etc." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; resize: vertical;" required></textarea>
                </div>
                <div class="modal-confirm-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeSignalementModal()">Annuler</button>
                    <button type="submit" class="btn-modal-confirm">Signaler</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-confirm-delete" class="modal-confirm" style="display: none;">
        <div class="modal-confirm-overlay"></div>
        <div class="modal-confirm-content">
            <div class="modal-confirm-header">
                <h3>Confirmer la suppression</h3>
            </div>
            <div class="modal-confirm-body">
                <p>Êtes-vous sûr de vouloir supprimer définitivement votre avis ?</p>
                <p style="font-size: 0.9em; color: #666; margin-top: 10px;">Cette action est irréversible.</p>
            </div>
            <div class="modal-confirm-footer">
                <button type="button" class="btn-modal-cancel" id="btn-cancel-delete">Annuler</button>
                <button type="button" class="btn-modal-confirm" id="btn-confirm-delete">Supprimer</button>
            </div>
        </div>
    </div>

    <script src="descriptionProduitClientScript.js"></script>
    <?php include "footer.php";?>
</body>
</html>
