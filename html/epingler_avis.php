<?php
session_start();
require_once("loginBdd.php");

// Vérifier que l'utilisateur est connecté en tant que vendeur
if (!isset($_SESSION['id_compte'])) {
    $_SESSION['avis_message'] = 'Vous devez être connecté en tant que vendeur';
    header('Location: connexion.php');
    exit();
}

$id_vendeur = $_SESSION['id_compte'];

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_avis']) && isset($_POST['id_produit'])) {
        $id_avis = intval($_POST['id_avis']);
        $id_produit = intval($_POST['id_produit']);
        $page_source = isset($_POST['page_source']) ? $_POST['page_source'] : 'avisVendeur.php';

        // Vérifier que l'avis appartient à un produit du vendeur
        $stmtCheck = $pdo->prepare("
            SELECT a.id_avis, a.epingle, p.id_vendeur 
            FROM sae._avis a
            JOIN sae._produit p ON a.id_produit = p.id_produit
            WHERE a.id_avis = :id_avis AND p.id_produit = :id_produit
        ");
        $stmtCheck->execute([':id_avis' => $id_avis, ':id_produit' => $id_produit]);
        $avis = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$avis) {
            $_SESSION['avis_message'] = 'Avis introuvable';
            header('Location: ' . $page_source);
            exit();
        }

        // Vérifier que l'utilisateur est bien le vendeur du produit
        if ($avis['id_vendeur'] != $id_vendeur) {
            $_SESSION['avis_message'] = 'Vous n\'êtes pas autorisé à épingler cet avis';
            header('Location: ' . $page_source);
            exit();
        }

        // Toggle l'épinglage
        $nouveau_statut = !$avis['epingle'];
        
        $stmtUpdate = $pdo->prepare("
            UPDATE sae._avis 
            SET epingle = :epingle
            WHERE id_avis = :id_avis
        ");
        $stmtUpdate->execute([':epingle' => $nouveau_statut ? 'true' : 'false', ':id_avis' => $id_avis]);

        $_SESSION['avis_message'] = $nouveau_statut ? 'Avis épinglé avec succès' : 'Avis désépinglé avec succès';
        header('Location: ' . $page_source . '#avis-' . $id_avis);
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['avis_message'] = 'Erreur: ' . $e->getMessage();
    $page_source = isset($_POST['page_source']) ? $_POST['page_source'] : 'avisVendeur.php';
    header('Location: ' . $page_source);
    exit();
}
?>
