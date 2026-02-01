<?php
session_start();
require_once("loginBdd.php");

// Vérifier si c'est une requête AJAX
$is_ajax_request = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id_compte']) && !isset($_SESSION['id_client'])) {
    if ($is_ajax_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Vous devez être connecté pour signaler un avis']);
        exit();
    }
    header("Location: connexion.php");
    exit();
}

$id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : $_SESSION['id_compte'];

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_avis']) && isset($_POST['id_produit'])) {
        $id_avis = intval($_POST['id_avis']);
        $id_produit = intval($_POST['id_produit']);
        $raison = isset($_POST['raison']) ? trim($_POST['raison']) : '';

        // Vérifier que la raison est fournie
        if (empty($raison)) {
            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Veuillez indiquer une raison pour le signalement']);
                exit();
            }
            $_SESSION['avis_message'] = "Veuillez indiquer une raison pour le signalement.";
            header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
            exit();
        }

        // Vérifier que l'avis existe
        $stmtCheck = $pdo->prepare("SELECT id_avis, signale FROM sae._avis WHERE id_avis = :id_avis");
        $stmtCheck->execute([':id_avis' => $id_avis]);
        $avis = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$avis) {
            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Avis introuvable']);
                exit();
            }
            $_SESSION['avis_message'] = "Avis introuvable.";
            header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
            exit();
        }

        // Vérifier si l'avis a déjà été signalé
        if ($avis['signale'] === true || $avis['signale'] === 't') {
            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Cet avis a déjà été signalé et est en cours d\'examen']);
                exit();
            }
            $_SESSION['avis_message'] = "Cet avis a déjà été signalé et est en cours d'examen.";
            header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
            exit();
        }

        // Initialiser le tableau de signalements en session
        if (!isset($_SESSION['signaled_avis'])) {
            $_SESSION['signaled_avis'] = [];
        }

        // Vérifier si l'utilisateur a déjà signalé cet avis
        if (in_array($id_avis, $_SESSION['signaled_avis'])) {
            if ($is_ajax_request) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Vous avez déjà signalé cet avis']);
                exit();
            }
            $_SESSION['avis_message'] = "Vous avez déjà signalé cet avis.";
            header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
            exit();
        }

        // Marquer l'avis comme signalé et stocker la raison
        $stmtUpdate = $pdo->prepare("
            UPDATE sae._avis 
            SET signale = TRUE, 
                raison_signalement = :raison
            WHERE id_avis = :id_avis
        ");
        $stmtUpdate->execute([':id_avis' => $id_avis, ':raison' => $raison]);
        
        // Marquer en session que cet utilisateur a signalé cet avis
        $_SESSION['signaled_avis'][] = $id_avis;

        if ($is_ajax_request) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Avis signalé avec succès'
            ]);
            exit();
        }

        $_SESSION['avis_message'] = "L'avis a été signalé avec succès. Nos équipes vont l'examiner.";
        header("Location: descriptionProduitClient.php?id_produit=" . $id_produit . "#avis_client");
        exit();
    }

} catch (PDOException $e) {
    if ($is_ajax_request) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Erreur lors du signalement: ' . $e->getMessage()]);
        exit();
    }
    $_SESSION['avis_message'] = "Erreur lors du signalement : " . $e->getMessage();
    if (isset($id_produit)) {
        header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
    } else {
        header("Location: catalogue.php");
    }
    exit();
}
?>
