<?php
session_start();
include('loginBdd.php');

$pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("SET search_path TO sae");


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'vider_panier') {
        if (isset($_SESSION['id_compte'])) {
            $id_client = $_SESSION['id_compte'];
            
            $stmt = $pdo->prepare("SELECT num_commande FROM _commande WHERE id_client = :id_client AND statut_commande = 'En attente de paiement'");
            $stmt->execute(['id_client' => $id_client]);
            $num_commande = $stmt->fetchColumn();
            
            if ($num_commande) {
                $stmt = $pdo->prepare("DELETE FROM _ligneCommande WHERE num_commande = :num_commande");
                $stmt->execute(['num_commande' => $num_commande]);
                
                $stmt = $pdo->prepare("UPDATE _commande SET montant_total_ht = 0, montant_total_ttc = 0, total_nb_prod = 0 WHERE num_commande = :num_commande");
                $stmt->execute(['num_commande' => $num_commande]);
            }
        } else {
            // Utilisateur non connecté : vider le panier temporaire
            unset($_SESSION['tmp_panier']);
        }
        
    } elseif ($action === 'supp_ligne') {
        if (isset($_SESSION['id_compte'])) {
            $id_client = $_SESSION['id_compte'];
            $id_produit = $_POST['id_produit'];
            
            $stmt = $pdo->prepare("SELECT num_commande FROM _commande WHERE id_client = :id_client AND statut_commande = 'En attente de paiement'");
            $stmt->execute(['id_client' => $id_client]);
            $num_commande = $stmt->fetchColumn();
            
            if ($num_commande) {
                $stmt = $pdo->prepare("DELETE FROM _ligneCommande WHERE num_commande = :num_commande AND id_produit = :id_produit");
                $stmt->execute(['num_commande' => $num_commande, 'id_produit' => $id_produit]);
                
                $stmt = $pdo->prepare("
                    UPDATE _commande 
                    SET montant_total_ht = (SELECT COALESCE(SUM(total_ligne_commande_ht), 0) FROM _ligneCommande WHERE num_commande = :num_commande),
                        montant_total_ttc = (SELECT COALESCE(SUM(total_ligne_commande_ttc), 0) FROM _ligneCommande WHERE num_commande = :num_commande),
                        total_nb_prod = (SELECT COALESCE(SUM(quantite_prod), 0) FROM _ligneCommande WHERE num_commande = :num_commande)
                    WHERE num_commande = :num_commande
                ");
                $stmt->execute(['num_commande' => $num_commande]);
            }
        }else{
            unset($_SESSION['tmp_panier'][$id_produit]);
        }
        
    } elseif ($action === 'supp_temp_panier') {
        if (isset($_POST['id_produit'])) {
            $id_produit = $_POST['id_produit'];
            if (isset($_SESSION['tmp_panier'][$id_produit])) {
                unset($_SESSION['tmp_panier'][$id_produit]);

            }
        }
    }
    
}

$_SESSION['toast_message'] = "✖ Supprimé du panier";
$_SESSION['toast_type'] = "suppression";

header('Location: panier.php');
exit;
?>
