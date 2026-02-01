<?php
session_start();

require_once("loginBdd.php");



try {
    $connexionPDO = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);
    $connexionPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connexionPDO->exec("SET search_path TO sae, public");
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

// Vérifier que l'utilisateur est bien un vendeur
if (!isset($_SESSION['is_vendeur']) || $_SESSION['is_vendeur'] !== true) {
    header('Location: index.php');
    exit();
}

$identifiantVendeur = $_SESSION['id_compte'];

$totalVentes = 0;
$totalRevenus = 0;
$moyenneAvis = 0;
$totalProduits = 0;
$commandesRecentes = [];
$produitsRecents = [];
$avisRecents = [];

try {
    //Ventes
    $requetePreparee = $connexionPDO->prepare("
        SELECT COUNT(DISTINCT lc.num_commande) as total_ventes 
        FROM _lignecommande lc 
        INNER JOIN _produit p ON lc.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id");
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $totalVentes = $requetePreparee->fetch(PDO::FETCH_ASSOC)['total_ventes'] ?? 0;

    //Revenus (commandes payées = en livraison ou livrées)
    $requetePreparee = $connexionPDO->prepare("
        SELECT COALESCE(SUM(lc.total_ligne_commande_ttc), 0) as total_revenus
        FROM sae._lignecommande lc
        JOIN sae._produit p ON lc.id_produit = p.id_produit
        JOIN sae._commande c ON lc.num_commande = c.num_commande
        WHERE p.id_vendeur = :id AND c.statut_commande IN ('Livraison', 'Livrée')");
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $totalRevenus = $requetePreparee->fetch(PDO::FETCH_ASSOC)['total_revenus'] ?? 0;

    //Avis Moyenne
    $requetePreparee = $connexionPDO->prepare("
        SELECT COALESCE(ROUND(AVG(a.note), 1), 0) as moyenne_avis 
        FROM _avis a 
        INNER JOIN _produit p ON a.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id");
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $moyenneAvis = $requetePreparee->fetch(PDO::FETCH_ASSOC)['moyenne_avis'] ?? 0;

    //Produits Actifs
    $requetePreparee = $connexionPDO->prepare("
        SELECT COUNT(*) as total_produits 
        FROM _produit 
        WHERE id_vendeur = :id");
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $totalProduits = $requetePreparee->fetch(PDO::FETCH_ASSOC)['total_produits'] ?? 0;

    //commandes Récentes
    $requetecommande = "
        SELECT DISTINCT c.num_commande, c.statut_commande, c.date_commande,
        (SELECT p.nom_produit FROM _lignecommande lc JOIN _produit p ON lc.id_produit = p.id_produit WHERE lc.num_commande = c.num_commande AND p.id_vendeur = :id LIMIT 1) as nom_produit
        FROM _commande c
        WHERE c.num_commande IN (SELECT DISTINCT lc.num_commande FROM _lignecommande lc JOIN _produit p ON lc.id_produit = p.id_produit WHERE p.id_vendeur = :id)
        ORDER BY c.date_commande DESC LIMIT 10"; 
    $requetePreparee = $connexionPDO->prepare($requetecommande);
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $commandesRecentes = $requetePreparee->fetchAll(PDO::FETCH_ASSOC);

    //Produits Récents
    $requeteProduit = "
        SELECT p.id_produit, p.nom_produit, i.chemin, i.nom_fichier, i.extension 
        FROM _produit p 
        LEFT JOIN _image i ON p.id_image = i.id_image 
        WHERE p.id_vendeur = :id 
        ORDER BY p.date_prod DESC LIMIT 3";
    $requetePreparee = $connexionPDO->prepare($requeteProduit);
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $produitsRecents = $requetePreparee->fetchAll(PDO::FETCH_ASSOC);

    //Avis Récents
    $requeteAvis = "
        SELECT a.note, a.commentaire, a.date_avis, 
               cl.prenom || ' ' || SUBSTRING(cl.nom, 1, 1) || '.' as client_nom,
               p.nom_produit
        FROM _avis a 
        INNER JOIN _produit p ON a.id_produit = p.id_produit 
        INNER JOIN _client cl ON a.id_client = cl.id_client 
        INNER JOIN _compte cpt ON cl.id_client = cpt.id_compte
        WHERE p.id_vendeur = :id 
        ORDER BY a.date_avis DESC LIMIT 5";
    $requetePreparee = $connexionPDO->prepare($requeteAvis);
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $avisRecents = $requetePreparee->fetchAll(PDO::FETCH_ASSOC);

    //Avis Signalés
    $requeteAvisSignales = "
        SELECT COUNT(*) as nb_avis_signales
        FROM _avis a 
        INNER JOIN _produit p ON a.id_produit = p.id_produit 
        WHERE p.id_vendeur = :id AND a.signale = TRUE";
    $requetePreparee = $connexionPDO->prepare($requeteAvisSignales);
    $requetePreparee->execute(['id' => $identifiantVendeur]);
    $nbAvisSignales = $requetePreparee->fetch(PDO::FETCH_ASSOC)['nb_avis_signales'] ?? 0;

} catch (PDOException $e) {
    $messageErreur = "Erreur SQL : " . $e->getMessage();
    echo "<div style='background:red;color:white;padding:10px;margin:10px;'>" . htmlspecialchars($messageErreur) . "</div>";
}

    // Debug : afficher les compteurs (commenté)
// echo "<div style='background:yellow;padding:10px;margin:10px;'>Produits: " . count($produitsRecents) . " | Avis: " . count($avisRecents) . " | ID Vendeur: $identifiantVendeur</div>";

function afficherEtoiles($note) {
    $html = '';
    $note = round($note);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $note) $html .= '<span class="etoile-pleine">&#9733;</span>';
        else $html .= '<span class="etoile-vide">&#9734;</span>';
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <title>Tableau de Bord - Espace Vendeur</title>
    <link rel="stylesheet" href="accueilVendeurStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
</head>
<body>
    
    <?php include 'header.php'; ?>

    <div class="conteneur">
    
        <?php if ($nbAvisSignales > 0): ?>
            <div class="alerte-avis-signales">
                <div class="alerte-contenu">
                    <div class="alerte-icone">!</div>
                    <div class="alerte-texte">
                        <strong>Attention !</strong> Vous avez <strong><?php echo $nbAvisSignales; ?></strong> avis signalé<?php echo $nbAvisSignales > 1 ? 's' : ''; ?> en attente de modération.
                    </div>
                    <a href="gestionAvisVendeur.php" class="btn-voir-avis-signales">Gérer les avis signalés</a>
                </div>
            </div>
        <?php endif; ?>
    
        <?php if (isset($messageErreur)): ?>
            <div style="background:#ff4444;color:white;padding:15px;margin:20px 0;border-radius:5px;">
                <?php echo htmlspecialchars($messageErreur); ?>
            </div>
        <?php endif; ?>
    
        <div class="ligne-statistiques">
            <div class="carte-statistique">
                <div class="info-statistique">
                    <div class="etiquette-statistique">Ventes Totales</div>
                    <div class="valeur-statistique"><?php echo $totalVentes; ?></div>
                </div>
            </div>
            
            <div class="carte-statistique">
                <div class="info-statistique">
                    <div class="etiquette-statistique">Chiffre d'affaires</div>
                    <div class="valeur-statistique"><?php echo number_format($totalRevenus, 2, ',', ' '); ?>€</div>
                </div>
            </div>
            
            <div class="carte-statistique mise-en-avant">
                <div class="info-statistique">
                    <div class="etiquette-statistique">Note Moyenne</div>
                    <div class="valeur-statistique"><?php echo $moyenneAvis; ?> / 5</div>
                </div>
            </div>
            
            <div class="carte-statistique">
                <div class="info-statistique">
                    <div class="etiquette-statistique">Produits Actifs</div>
                    <div class="valeur-statistique"><?php echo $totalProduits; ?></div>
                </div>
            </div>
        </div>

        <div class="grille-tableau-bord">

            <div class="bloc-section section-commandes">
                <div class="entete-section">
                    <h3 class="titre-section">Commandes Récentes</h3>
                    <a href="commandesVendeur.php" class="lien-voir-tout">Tout voir</a>
                </div>
                
                <?php if (count($commandesRecentes) > 0): ?>
                    <?php foreach ($commandesRecentes as $commande): ?>
                        <?php
                        $st = strtolower($commande['statut_commande']);
                        $classe = 'statut-attente';

                        if (strpos($st, 'livraison') !== false) {
                            $classe = 'statut-expedie';
                        } elseif (strpos($st, 'livrée') !== false || strpos($st, 'livree') !== false) {
                            $classe = 'statut-livre';
                        } elseif (strpos($st, 'attente') !== false || strpos($st, 'paiement') !== false) {
                            $classe = 'statut-attente';
                        }
                        ?>
                        <div class="element-commande">
                            <div class="groupe-info-commande">
                                <span class="numero-commande">#<?php echo $commande['num_commande']; ?></span>
                                <span class="produit-commande"><?php echo htmlspecialchars($commande['nom_produit'] ?: 'Produit inconnu'); ?></span>
                            </div>
                            <span class="statut-commande <?php echo $classe; ?>">
                                <?php echo htmlspecialchars($commande['statut_commande']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-secondary);text-align:center;padding:20px;">Aucune commande récente.</p>
                <?php endif; ?>
            </div>

            <div class="bloc-section section-produits">
                <div class="entete-section">
                    <h3 class="titre-section">Nouveaux Produits</h3>
                </div>
                <?php if (count($produitsRecents) > 0): ?>
                    <?php foreach ($produitsRecents as $produit): ?>
                        <div class="element-produit">
                            <div class="image-produit">
                                <?php 
                                    $img = 'media/default.png';
                                    if (!empty($produit['chemin']) && !empty($produit['nom_fichier'])) {
                                        $img = htmlspecialchars($produit['chemin'] . $produit['nom_fichier'] . $produit['extension']);
                                    }
                                ?>
                                <img src="<?php echo $img; ?>" alt="Produit">
                            </div>
                            <div class="nom-produit"><?php echo htmlspecialchars($produit['nom_produit']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-secondary);">Aucun produit récent.</p>
                <?php endif; ?>
            </div>

            <div class="bloc-section section-avis">
                <div class="entete-section">
                    <h3 class="titre-section">Derniers Avis <?php if ($nbAvisSignales > 0): ?><span class="badge-signalement"><?php echo $nbAvisSignales; ?></span><?php endif; ?></h3>
                    <a href="gestionAvisVendeur.php" class="lien-voir-tout">Gérer les avis</a>
                </div>
                <?php if (count($avisRecents) > 0): ?>
                    <?php foreach ($avisRecents as $avis): ?>
                        
                        <div class="element-avis">
                            <div class="entete-avis">
                                <span class="client-avis"><?php echo htmlspecialchars($avis['client_nom']); ?></span>
                                <span class="note-avis"><?php echo afficherEtoiles($avis['note']); ?></span>
                            </div>
                            <div class="produit-avis" style="font-size:0.9em;color:var(--text-secondary);margin:5px 0;">
                                <?php echo htmlspecialchars($avis['nom_produit']); ?>
                            </div>
                            <div class="texte-avis">
                                "<?php echo htmlspecialchars(substr($avis['commentaire'], 0, 80)) . (strlen($avis['commentaire']) > 80 ? '...' : ''); ?>"
                            </div>
                            <div class="date-avis" style="font-size:0.85em;color:var(--text-secondary);margin-top:8px;">
                                <?php 
                                    $date = new DateTime($avis['date_avis']);
                                    echo $date->format('d/m/Y à H:i'); 
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:var(--text-secondary);">Aucun avis récent.</p>
                <?php endif; ?>
            </div>

        </div> </div>

    <?php include 'footer.php'; ?>

    <script src="acceuilVendeur.js"></script>
</body>
</html>
