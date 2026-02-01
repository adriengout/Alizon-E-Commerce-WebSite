<?php
session_start();
require_once("loginBdd.php");
$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE,  PDO::ERRMODE_EXCEPTION);


if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

// Vérifier que l'utilisateur est bien un vendeur
if (!isset($_SESSION['is_vendeur']) || $_SESSION['is_vendeur'] !== true) {
    header('Location: index.php');
    exit();
}

$id_vendeur = $_SESSION['id_compte'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id_produit = $_POST['produit_concerne'];
    $nom_promotion = $_POST['nom'];
    $description = $_POST['description'];
    $date_fin = $_POST['date_fin'];
    $code = $_POST['code'];
    $taux_reduction = isset($_POST['valeur']) ? floatval($_POST['valeur']) : 0;
    $montant_min = isset($_POST['montant_min']) ? floatval($_POST['montant_min']) : 0;
    $afficher_accueil = isset($_POST['afficher_accueil']) ? 1 : 0;
    $envoyer_email = isset($_POST['envoyer_email']) ? 1 : 0;
    
    $sqlRequest = "SELECT extension from sae._image where nom_fichier = 'produit{$id_produit}_1'";
    $stmt = $pdo->prepare($sqlRequest);
    $stmt->execute();
    $extension = $stmt->fetchColumn();
    
    $insert_promo = "INSERT INTO sae._promotion 
                                (id_produit, nom_promotion, descrip_promotion, date_debut, date_fin, banniere_promo)
                                 VALUES (:id_produit, :nom_promotion, :descrip_promotion, CURRENT_DATE, :date_fin, :banniere_promo)";

    $stmt = $pdo->prepare($insert_promo);
    $stmt->execute([
        'id_produit' => $id_produit,
        'nom_promotion' => $nom_promotion,
        'descrip_promotion' => $description,
        'date_fin' => $date_fin,
        'banniere_promo' => "produit{$id_produit}_1{$extension}"
    ]);
    
    if ($taux_reduction > 0) {
        $insert_remise = "INSERT INTO sae._remise 
                          (taux_remise, id_produit)
                          VALUES (:taux_remise, :id_produit)";
        
        $stmt_remise = $pdo->prepare($insert_remise);
        $stmt_remise->execute([
            'taux_remise' => $taux_reduction/100,
            'id_produit' => $id_produit
        ]);
    }
}

header('Location: promotionsVendeur.php');
exit();
?>
