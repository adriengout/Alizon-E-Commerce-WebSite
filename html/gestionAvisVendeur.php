<?php
session_start();
require_once("loginBdd.php");

if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

$id_vendeur = $_SESSION['id_compte'];
$message = '';

if (isset($_SESSION['avis_message'])) {
    $message = $_SESSION['avis_message'];
    unset($_SESSION['avis_message']);
}

$total_ventes = 0;
$total_revenus = 0;
$moyenne_avis = 0;
$total_produits = 0;
$total_avis = 0;
$total_avis_non_lus = 0;

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //Ventes
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT lc.num_commande) as total_ventes 
        FROM sae._lignecommande lc 
        INNER JOIN sae._produit p ON lc.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id");
    $stmt->execute(['id' => $id_vendeur]);
    $total_ventes = $stmt->fetch(PDO::FETCH_ASSOC)['total_ventes'] ?? 0;

    //Revenus (commandes payées = en livraison ou livrées)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(lc.total_ligne_commande_ttc), 0) as total_revenus
        FROM sae._lignecommande lc
        JOIN sae._produit p ON lc.id_produit = p.id_produit
        JOIN sae._commande c ON lc.num_commande = c.num_commande
        WHERE p.id_vendeur = :id AND c.statut_commande IN ('Livraison', 'Livrée')");
    $stmt->execute(['id' => $id_vendeur]);
    $total_revenus = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenus'] ?? 0;

    //Avis Moyenne
    $stmt = $pdo->prepare("
        SELECT COALESCE(ROUND(AVG(a.note), 1), 0) as moyenne_avis 
        FROM sae._avis a 
        INNER JOIN sae._produit p ON a.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id");
    $stmt->execute(['id' => $id_vendeur]);
    $moyenne_avis = $stmt->fetch(PDO::FETCH_ASSOC)['moyenne_avis'] ?? 0;

    //Produits Actifs
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_produits 
        FROM sae._produit 
        WHERE id_vendeur = :id");
    $stmt->execute(['id' => $id_vendeur]);
    $total_produits = $stmt->fetch(PDO::FETCH_ASSOC)['total_produits'] ?? 0;

    //Total Avis
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_avis 
        FROM sae._avis a 
        INNER JOIN sae._produit p ON a.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id");
    $stmt->execute(['id' => $id_vendeur]);
    $total_avis = $stmt->fetch(PDO::FETCH_ASSOC)['total_avis'] ?? 0;

    //Total Avis Non Lus
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_non_lus 
        FROM sae._avis a 
        INNER JOIN sae._produit p ON a.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id AND (a.lu_par_vendeur = FALSE OR a.lu_par_vendeur IS NULL)");
    $stmt->execute(['id' => $id_vendeur]);
    $total_avis_non_lus = $stmt->fetch(PDO::FETCH_ASSOC)['total_non_lus'] ?? 0;

    // Marquer tous les avis non lus comme lus lors de la consultation de la page
    $stmt = $pdo->prepare("
        UPDATE sae._avis 
        SET lu_par_vendeur = TRUE 
        WHERE id_produit IN (
            SELECT id_produit 
            FROM sae._produit 
            WHERE id_vendeur = :id_vendeur
        ) 
        AND (lu_par_vendeur = FALSE OR lu_par_vendeur IS NULL)
    ");
    $stmt->execute([':id_vendeur' => $id_vendeur]);

    // Récupérer tous les avis sur les produits du vendeur
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
            A.raison_signalement,
            A.epingle,
            R.reponse_vendeur,
            R.date_reponse,
            P.id_produit,
            P.nom_produit,
            C.nom AS client_nom,
            C.prenom AS client_prenom,
            CPT.login AS client_login,
            I_CLIENT.chemin AS profil_chemin,
            I_CLIENT.nom_fichier AS profil_nom_fichier,
            I_CLIENT.extension AS profil_extension,
            I_AVIS.chemin AS avis_chemin,
            I_AVIS.nom_fichier AS avis_nom_fichier,
            I_AVIS.extension AS avis_extension
        FROM sae._avis A
        JOIN sae._produit P ON A.id_produit = P.id_produit
        JOIN sae._client C ON A.id_client = C.id_client
        JOIN sae._compte CPT ON C.id_client = CPT.id_compte
        LEFT JOIN sae._image I_CLIENT ON CPT.id_image = I_CLIENT.id_image
        LEFT JOIN sae._image I_AVIS ON A.id_image = I_AVIS.id_image
        LEFT JOIN sae._reponse R ON A.id_avis = R.id_avis
        WHERE P.id_vendeur = :id_vendeur
        ORDER BY A.epingle DESC, A.signale DESC, A.date_avis DESC
    ");
    $stmt->execute([':id_vendeur' => $id_vendeur]);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <title>Gestion des Avis - Espace Vendeur</title>
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="gestionAvisVendeurStyle.css">
</head>
<body>
    <?php include "header.php"; ?>

    <main class="main-container">
        <div class="page-header">
            <h1>Gestion des Avis</h1>
            <p>Consultez et répondez aux avis laissés sur vos produits</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Ventes Totales</div>
                    <div class="stat-value"><?php echo $total_ventes; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Chiffre d'affaires</div>
                    <div class="stat-value"><?php echo number_format($total_revenus, 2, ',', ' '); ?>€</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Note Moyenne</div>
                    <div class="stat-value"><?php echo $moyenne_avis; ?> / 5</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Produits Actifs</div>
                    <div class="stat-value"><?php echo $total_produits; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="stat-label">Total Avis</div>
                    <div class="stat-value">
                        <?php echo $total_avis; ?>
                        <?php if ($total_avis_non_lus > 0): ?>
                            <span class="badge-count"><?php echo $total_avis_non_lus; ?> nouveau<?php echo $total_avis_non_lus > 1 ? 'x' : ''; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="avis-container">
            <?php if (count($avis) > 0): ?>
                <?php foreach ($avis as $av): 
                    $profil_img_src = $av['profil_chemin'] . $av['profil_nom_fichier'] . $av['profil_extension'];
                    $avis_img_src = $av['avis_chemin'] . $av['avis_nom_fichier'] . $av['avis_extension'];
                    $est_signale = ($av['signale'] === true || $av['signale'] === 't');
                    $est_epingle = ($av['epingle'] === true || $av['epingle'] === 't');
                ?>
                <div class="avis-card <?= $est_signale ? 'avis-signale' : '' ?><?= $est_epingle ? ' avis-epingle' : '' ?>">
                    <?php if ($est_epingle): ?>
                        <span class="badge-epingle">Épinglé</span>
                    <?php endif; ?>
                    <?php if ($est_signale): ?>
                        <div class="alerte-signalement">
                            <strong>Avis signalé</strong>
                            <?php if (!empty($av['raison_signalement'])): ?>
                                <br><small>Raison : <?= htmlspecialchars($av['raison_signalement']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="avis-header">
                        <div class="avis-user-info">
                            <img src="<?= htmlspecialchars($profil_img_src) ?>" alt="Profil" class="user-avatar">
                            <div>
                                <strong><?= htmlspecialchars($av['client_prenom'] . ' ' . $av['client_nom'][0] . '.') ?></strong>
                                <p class="avis-date">Le <?= date('d/m/Y', strtotime($av['date_avis'])) ?></p>
                            </div>
                        </div>
                        <div class="avis-product-tag">
                            <a href="modifierProduit.php?id=<?= $av['id_produit'] ?>">
                                <?= htmlspecialchars($av['nom_produit']) ?>
                            </a>
                        </div>
                    </div>

                    <div class="avis-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?= $i <= $av['note'] ? 'filled' : '' ?>">★</span>
                        <?php endfor; ?>
                        <strong class="avis-title"><?= htmlspecialchars(html_entity_decode($av['titre_avis'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></strong>
                    </div>

                    <div class="avis-content">
                        <p><?= nl2br(htmlspecialchars(html_entity_decode($av['commentaire'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></p>
                        <?php if ($av['avis_nom_fichier'] !== 'default_image'): ?>
                            <img src="<?= htmlspecialchars($avis_img_src) ?>" alt="Image de l'avis" class="avis-image">
                        <?php endif; ?>
                    </div>

                    <div class="avis-stats">
                        <span class="stat-utile"><?= $av['votes_utiles'] ?> utiles</span>
                        <span class="stat-inutile"><?= $av['votes_inutiles'] ?> inutiles</span>
                    </div>

                    <div class="avis-actions" style="margin-top: 15px;">
                        <form method="post" action="epingler_avis.php" class="epingler-form" data-avis-id="<?= $av['id_avis'] ?>" style="display:inline-block;">
                            <input type="hidden" name="id_avis" value="<?= htmlspecialchars($av['id_avis']) ?>">
                            <input type="hidden" name="id_produit" value="<?= htmlspecialchars($av['id_produit']) ?>">
                            <input type="hidden" name="page_source" value="gestionAvisVendeur.php">
                            <button type="submit" class="btn-epingler" style="background: <?= $est_epingle ? '#ffd700' : '#6c757d' ?>; color: <?= $est_epingle ? '#000' : '#fff' ?>; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold;">
                                <?= $est_epingle ? 'Désépingler' : 'Épingler' ?>
                            </button>
                        </form>
                    </div>

                    <?php if ($est_signale): ?>
                        <!-- Actions de modération pour les avis signalés -->
                        <div class="actions-moderation">
                            <form action="moderer_avis.php" method="post" style="display: inline;">
                                <input type="hidden" name="id_avis" value="<?= $av['id_avis'] ?>">
                                <input type="hidden" name="action" value="supprimer">
                                <button type="submit" class="btn-supprimer-avis" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet avis ? Le client sera notifié par email.');">
                                    Supprimer l'avis
                                </button>
                            </form>
                            <form action="moderer_avis.php" method="post" style="display: inline;">
                                <input type="hidden" name="id_avis" value="<?= $av['id_avis'] ?>">
                                <input type="hidden" name="action" value="valider">
                                <button type="submit" class="btn-valider-avis" onclick="return confirm('Valider cet avis (il ne sera plus marqué comme signalé) ?');">
                                    Valider l'avis
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($av['reponse_vendeur'])): ?>
                        <!-- Réponse existante -->
                        <div class="reponse-vendeur">
                            <div class="reponse-header">
                                <strong>Votre réponse</strong>
                                <span class="reponse-date">Le <?= date('d/m/Y', strtotime($av['date_reponse'])) ?></span>
                            </div>
                            <p class="reponse-text"><?= nl2br(htmlspecialchars($av['reponse_vendeur'])) ?></p>
                            <button class="btn-modifier-reponse" onclick="toggleEditForm(<?= $av['id_avis'] ?>)">Modifier</button>
                        </div>

                        <!-- Formulaire de modification (caché par défaut) -->
                        <div id="edit-form-<?= $av['id_avis'] ?>" class="edit-reponse-form" style="display: none;">
                            <form action="repondre_avis_vendeur.php" method="post">
                                <input type="hidden" name="id_avis" value="<?= $av['id_avis'] ?>">
                                <textarea name="reponse" rows="4" required placeholder="Modifier votre réponse..."><?= htmlspecialchars($av['reponse_vendeur']) ?></textarea>
                                <div class="form-actions">
                                    <button type="submit" class="btn-submit">Enregistrer</button>
                                    <button type="button" class="btn-cancel" onclick="toggleEditForm(<?= $av['id_avis'] ?>)">Annuler</button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Formulaire de réponse -->
                        <div class="reponse-form">
                            <form action="repondre_avis_vendeur.php" method="post">
                                <input type="hidden" name="id_avis" value="<?= $av['id_avis'] ?>">
                                <textarea name="reponse" rows="4" required placeholder="Répondez à cet avis..."></textarea>
                                <button type="submit" class="btn-submit">Publier ma réponse</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Aucun avis sur vos produits pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function toggleEditForm(idAvis) {
            var form = document.getElementById('edit-form-' + idAvis);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>

    <?php include "footer.php"; ?>
</body>
</html>
