<?php
session_start();

require_once('loginBdd.php');

try {
    $connexionPDO = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);
    $connexionPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connexionPDO->exec("SET search_path TO sae, public");
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

//Vérifier que l'utilisateur est bien un vendeur
if (!isset($_SESSION['is_vendeur']) || $_SESSION['is_vendeur'] !== true) {
    header('Location: index.php');
    exit();
}

$identifiantVendeur = $_SESSION['id_compte'];



$requeteSQL = "
    SELECT 
        p.id_produit, 
        p.nom_produit, 
        p.prix_ht, 
        p.description_prod,
        s.quantite_dispo, 
        s.seuil_alerte,
        i.nom_fichier, 
        i.chemin,
        i.extension
    FROM _produit p
    JOIN _stock s ON p.id_produit = s.id_produit
    LEFT JOIN _image i ON p.id_image = i.id_image
    WHERE p.id_vendeur = :idVendeur
    ORDER BY p.nom_produit ASC
";

$requetePreparee = $connexionPDO->prepare($requeteSQL);
$requetePreparee->execute(['idVendeur' => $identifiantVendeur]);
$listeProduits = $requetePreparee->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="stockVendeurStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <title>Mon Stock - Espace Vendeur</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
</head>
<body>
    <?php include 'header.php'; ?>
    <main class="conteneur-principal">
        <div class="entete-principal">
            <h2>Mon Catalogue</h2>
            <a href="creerProduit.php" class="bouton-creer">+ Créer un produit</a>
        </div>

        <div class="filter-controls-container">
            <div class="filter-row">
                <span>Rechercher :</span>
                <input type="text" id="vendeur-recherche" placeholder="Nom du produit...">
            </div>
            
            <div class="filter-row">
                <span>Prix HT :</span>
                <input type="number" id="prix-min" placeholder="Min">
                <span>-</span>
                <input type="number" id="prix-max" placeholder="Max">
            </div>

            <div class="filter-row">
                <span>Stock Min :</span>
                <input type="number" id="qMin" placeholder="0" style="width:60px;">
            </div>

            <div class="filter-row">
                <span>Tri :</span>
                <select id="tri">
                    <option value="">Alphabétique</option>
                    <option value="prix_asc">Prix croissant</option>
                    <option value="prix_desc">Prix décroissant</option>
                    <option value="stock">Stock critique</option>
                </select>
            </div>
            <button onclick="toutEffacer()" class="clear-btn">Effacer tout </button>
        </div>

        <div class="grille-produits" id="stock-grid">
            </div>
    </main>
    <script src="filtresStock.js"></script>
    <script src="scriptLibrary/confirmDialog.js"></script>
    
    <script>
    // Initialisation de la boite de dialogue
    const dialog = new ConfirmDialog({
        lang: 'french',
        message: 'Voulez-vous vraiment supprimer ce produit ?',
        subtitle: 'Cette action est irréversible.',
        okText: 'Supprimer',
        cancelText: 'Annuler'
    });

    // Délégation d'événement : on écoute sur le conteneur parent "stock-grid"
    // car les formulaires sont créés dynamiquement par le JS
    const grilleProduits = document.getElementById('stock-grid');

    if (grilleProduits) {
        grilleProduits.addEventListener('submit', function(event) {
            // On vérifie si l'élément qui a déclenché le submit a la classe de nos formulaires
            if (event.target && event.target.classList.contains('form-supprimer-produit')) {
                event.preventDefault(); // On bloque l'envoi immédiat
                const formActuel = event.target; // Le formulaire spécifique qu'on veut envoyer

                dialog.show().then(result => {
                    if (result.ok) {
                        formActuel.submit(); // On envoie le formulaire manuellement si confirmé
                    }
                });
            }
        });
    }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
