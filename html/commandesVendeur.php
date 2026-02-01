<?php
session_start();

require_once('loginBdd.php');

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

// Filtres
$filtreStatut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filtreRecherche = isset($_GET['recherche']) ? trim($_GET['recherche']) : '';

// Récupérer toutes les commandes contenant des produits du vendeur
$requeteSQL = "
    SELECT DISTINCT
        c.num_commande,
        c.date_commande,
        c.statut_commande,
        c.montant_total_ttc,
        c.total_nb_prod,
        cl.prenom || ' ' || SUBSTRING(cl.nom, 1, 1) || '.' as client_nom,
        (
            SELECT STRING_AGG(p2.nom_produit, ', ')
            FROM _lignecommande lc2
            JOIN _produit p2 ON lc2.id_produit = p2.id_produit
            WHERE lc2.num_commande = c.num_commande AND p2.id_vendeur = :id1
        ) as produits_vendeur,
        (
            SELECT COALESCE(SUM(lc3.total_ligne_commande_ttc), 0)
            FROM _lignecommande lc3
            JOIN _produit p3 ON lc3.id_produit = p3.id_produit
            WHERE lc3.num_commande = c.num_commande AND p3.id_vendeur = :id2
        ) as montant_vendeur,
        (
            SELECT COALESCE(SUM(lc4.quantite_prod), 0)
            FROM _lignecommande lc4
            JOIN _produit p4 ON lc4.id_produit = p4.id_produit
            WHERE lc4.num_commande = c.num_commande AND p4.id_vendeur = :id3
        ) as quantite_vendeur
    FROM _commande c
    JOIN _client cl ON c.id_client = cl.id_client
    WHERE c.num_commande IN (
        SELECT DISTINCT lc.num_commande
        FROM _lignecommande lc
        JOIN _produit p ON lc.id_produit = p.id_produit
        WHERE p.id_vendeur = :id4
    )
";

$params = [
    'id1' => $identifiantVendeur,
    'id2' => $identifiantVendeur,
    'id3' => $identifiantVendeur,
    'id4' => $identifiantVendeur
];

// Filtre par statut
if (!empty($filtreStatut)) {
    $requeteSQL .= " AND c.statut_commande = :statut";
    $params['statut'] = $filtreStatut;
}

// Filtre par recherche (numéro de commande ou nom client)
if (!empty($filtreRecherche)) {
    $requeteSQL .= " AND (CAST(c.num_commande AS TEXT) LIKE :recherche OR LOWER(cl.prenom || ' ' || cl.nom) LIKE LOWER(:recherche2))";
    $params['recherche'] = '%' . $filtreRecherche . '%';
    $params['recherche2'] = '%' . $filtreRecherche . '%';
}

$requeteSQL .= " ORDER BY c.date_commande DESC";

try {
    $requetePreparee = $connexionPDO->prepare($requeteSQL);
    $requetePreparee->execute($params);
    $listeCommandes = $requetePreparee->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messageErreur = "Erreur SQL : " . $e->getMessage();
    $listeCommandes = [];
}

// Statistiques
$statsCommandes = [
    'total' => 0,
    'en_attente' => 0,
    'livraison' => 0,
    'livree' => 0
];

$requeteStats = $connexionPDO->prepare("
    SELECT c.statut_commande, COUNT(DISTINCT c.num_commande) as nb
    FROM _commande c
    WHERE c.num_commande IN (
        SELECT DISTINCT lc.num_commande
        FROM _lignecommande lc
        JOIN _produit p ON lc.id_produit = p.id_produit
        WHERE p.id_vendeur = :id
    )
    GROUP BY c.statut_commande
");
$requeteStats->execute(['id' => $identifiantVendeur]);
$resultStats = $requeteStats->fetchAll(PDO::FETCH_ASSOC);

foreach ($resultStats as $stat) {
    $statsCommandes['total'] += $stat['nb'];
    $st = strtolower($stat['statut_commande']);
    if (strpos($st, 'attente') !== false) {
        $statsCommandes['en_attente'] = $stat['nb'];
    } elseif (strpos($st, 'livraison') !== false) {
        $statsCommandes['livraison'] = $stat['nb'];
    } elseif (strpos($st, 'livrée') !== false || strpos($st, 'livree') !== false) {
        $statsCommandes['livree'] = $stat['nb'];
    }
}

function getClasseStatut($statut) {
    $st = strtolower($statut);
    if (strpos($st, 'livraison') !== false) {
        return 'statut-expedie';
    } elseif (strpos($st, 'livrée') !== false || strpos($st, 'livree') !== false) {
        return 'statut-livre';
    } elseif (strpos($st, 'attente') !== false) {
        return 'statut-attente';
    }
    return 'statut-attente';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - Espace Vendeur</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="commandesVendeurStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="conteneur">

        <?php if (isset($messageErreur)): ?>
            <div class="message-erreur">
                <?php echo htmlspecialchars($messageErreur); ?>
            </div>
        <?php endif; ?>

        <div class="entete-page">
            <h1>Mes Commandes</h1>
            <a href="accueilVendeur.php" class="bouton-retour">Retour au tableau de bord</a>
        </div>

        <!-- Statistiques -->
        <div class="ligne-statistiques">
            <div class="carte-stat">
                <div class="stat-valeur"><?php echo $statsCommandes['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="carte-stat stat-attente">
                <div class="stat-valeur"><?php echo $statsCommandes['en_attente']; ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="carte-stat stat-expedie">
                <div class="stat-valeur"><?php echo $statsCommandes['livraison']; ?></div>
                <div class="stat-label">Livraison</div>
            </div>
            <div class="carte-stat stat-livre">
                <div class="stat-valeur"><?php echo $statsCommandes['livree']; ?></div>
                <div class="stat-label">Livrées</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="zone-filtres">
            <form method="GET" class="formulaire-filtres">
                <div class="groupe-filtre">
                    <label for="recherche">Rechercher :</label>
                    <input type="text" id="recherche" name="recherche" placeholder="N° commande ou client..." value="<?php echo htmlspecialchars($filtreRecherche); ?>">
                </div>
                <div class="groupe-filtre">
                    <label for="statut">Statut :</label>
                    <select id="statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="En attente de paiement" <?php echo $filtreStatut === 'En attente de paiement' ? 'selected' : ''; ?>>En attente de paiement</option>
                        <option value="Livraison" <?php echo $filtreStatut === 'Livraison' ? 'selected' : ''; ?>>Livraison</option>
                        <option value="Livrée" <?php echo $filtreStatut === 'Livrée' ? 'selected' : ''; ?>>Livrée</option>
                    </select>
                </div>
                <button type="submit" class="bouton-filtrer">Filtrer</button>
                <a href="commandesVendeur.php" class="bouton-effacer">Effacer</a>
            </form>
        </div>

        <!-- Liste des commandes -->
        <div class="liste-commandes">
            <?php if (count($listeCommandes) > 0): ?>
                <?php foreach ($listeCommandes as $commande): ?>
                    <div class="carte-commande">
                        <div class="entete-commande">
                            <div class="info-commande">
                                <span class="numero-commande">#<?php echo $commande['num_commande']; ?></span>
                                <span class="date-commande">
                                    <?php
                                        $date = new DateTime($commande['date_commande']);
                                        echo $date->format('d/m/Y à H:i');
                                    ?>
                                </span>
                            </div>
                            <span class="statut-commande <?php echo getClasseStatut($commande['statut_commande']); ?>">
                                <?php echo htmlspecialchars($commande['statut_commande']); ?>
                            </span>
                        </div>

                        <div class="corps-commande">
                            <div class="detail-ligne">
                                <span class="detail-label">Client :</span>
                                <span class="detail-valeur"><?php echo htmlspecialchars($commande['client_nom']); ?></span>
                            </div>
                            <div class="detail-ligne">
                                <span class="detail-label">Produits :</span>
                                <span class="detail-valeur produits-liste"><?php echo htmlspecialchars($commande['produits_vendeur']); ?></span>
                            </div>
                            <div class="detail-ligne">
                                <span class="detail-label">Quantité :</span>
                                <span class="detail-valeur"><?php echo $commande['quantite_vendeur']; ?> article(s)</span>
                            </div>
                        </div>

                        <div class="pied-commande">
                            <div class="montant-commande">
                                <span class="montant-label">Votre part :</span>
                                <span class="montant-valeur"><?php echo number_format($commande['montant_vendeur'], 2, ',', ' '); ?> €</span>
                            </div>
                            <div class="actions-commande">
                                <a href="factureVendeur.php?num_commande=<?php echo $commande['num_commande']; ?>" class="bouton-facture">Télécharger facture</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="aucune-commande">
                    <p>Aucune commande trouvée.</p>
                    <?php if (!empty($filtreStatut) || !empty($filtreRecherche)): ?>
                        <a href="commandesVendeur.php">Voir toutes les commandes</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
