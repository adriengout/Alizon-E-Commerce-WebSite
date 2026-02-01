<?php
session_start();
require_once("loginBdd.php");

// 1. Vérification de sécurité (Session et Vendeur)
if (!isset($_SESSION['id_compte']) || !isset($_SESSION['is_vendeur']) || $_SESSION['is_vendeur'] !== true) {
    header('Location: connexion.php');
    exit();
}

$id_vendeur = $_SESSION['id_compte'];

// 2. Vérification de l'ID passé en paramètre
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_produit = intval($_GET['id']);

    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 3. Sécurité supplémentaire : On vérifie que cette promo appartient bien à CE vendeur
        // On fait une jointure avec la table produit pour vérifier l'id_vendeur
        $checkSql = "SELECT prom.id_promotion 
                     FROM sae._promotion prom 
                     JOIN sae._produit prod ON prom.id_produit = prod.id_produit 
                     WHERE prom.id_produit = :id_produit AND prod.id_vendeur = :id_vendeur";
        
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute([
            'id_produit' => $id_produit,
            'id_vendeur' => $id_vendeur
        ]);

        if ($stmtCheck->rowCount() > 0) {
            // 4. Suppression de la promotion
            $deleteSql = "DELETE FROM sae._promotion WHERE id_produit = :id_produit";
            $stmtDel = $pdo->prepare($deleteSql);
            $stmtDel->execute(['id_produit' => $id_produit]);

            $deleteSql = "DELETE FROM sae._remise WHERE id_produit = :id_produit";
            $stmtDel = $pdo->prepare($deleteSql);
            $stmtDel->execute(['id_produit' => $id_produit]);


            // Redirection avec un message de succès
            header('Location: promotionsVendeur.php');
            exit();
        } else {
            // La promo n'existe pas ou n'appartient pas au vendeur
            header('Location: promotionsVendeur.php');
            exit();
        }

    } catch (PDOException $e) {
        die("Erreur lors de la suppression : " . $e->getMessage());
    }
} else {
    // Pas d'ID fourni
    header('Location: promotionVendeur.php');
    exit();
}