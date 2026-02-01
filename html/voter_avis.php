<?php
session_start();
require_once("loginBdd.php");

if (!isset($_SESSION['id_compte']) && !isset($_SESSION['id_client'])) {
    header("Location: connexion.php");
    exit();
}

$id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : $_SESSION['id_compte'];

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la table _vote_avis si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sae._vote_avis (
            id_client INTEGER NOT NULL,
            id_avis INTEGER NOT NULL,
            type_vote VARCHAR(10) NOT NULL CHECK (type_vote IN ('utile', 'inutile')),
            date_vote TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_client, id_avis),
            FOREIGN KEY (id_avis) REFERENCES sae._avis(id_avis) ON DELETE CASCADE
        )
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_avis']) && isset($_POST['action'])) {
        $id_avis = intval($_POST['id_avis']);
        $action = ($_POST['action'] === 'utile') ? 'utile' : 'inutile';

        // Vérifier si l'utilisateur a déjà voté sur cet avis
        $stmtCheck = $pdo->prepare("SELECT type_vote FROM sae._vote_avis WHERE id_client = :id_client AND id_avis = :id_avis");
        $stmtCheck->execute([':id_client' => $id_client, ':id_avis' => $id_avis]);
        $existingVote = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingVote) {
            $prev_vote = $existingVote['type_vote'];
            
            if ($prev_vote === $action) {
                // Retirer le vote (l'utilisateur clique sur le même bouton)
                $pdo->prepare("DELETE FROM sae._vote_avis WHERE id_client = :id_client AND id_avis = :id_avis")
                    ->execute([':id_client' => $id_client, ':id_avis' => $id_avis]);
                
                if ($action === 'utile') {
                    $pdo->prepare("UPDATE sae._avis SET votes_utiles = GREATEST(votes_utiles - 1, 0) WHERE id_avis = :id_avis")
                        ->execute([':id_avis' => $id_avis]);
                } else {
                    $pdo->prepare("UPDATE sae._avis SET votes_inutiles = GREATEST(votes_inutiles - 1, 0) WHERE id_avis = :id_avis")
                        ->execute([':id_avis' => $id_avis]);
                }
            } else {
                // Changer le vote (utile -> inutile ou inutile -> utile)
                $pdo->prepare("UPDATE sae._vote_avis SET type_vote = :type_vote, date_vote = CURRENT_TIMESTAMP 
                              WHERE id_client = :id_client AND id_avis = :id_avis")
                    ->execute([':type_vote' => $action, ':id_client' => $id_client, ':id_avis' => $id_avis]);
                
                if ($prev_vote === 'utile') {
                    $pdo->prepare("UPDATE sae._avis SET votes_utiles = GREATEST(votes_utiles - 1, 0), 
                                  votes_inutiles = votes_inutiles + 1 WHERE id_avis = :id_avis")
                        ->execute([':id_avis' => $id_avis]);
                } else {
                    $pdo->prepare("UPDATE sae._avis SET votes_inutiles = GREATEST(votes_inutiles - 1, 0), 
                                  votes_utiles = votes_utiles + 1 WHERE id_avis = :id_avis")
                        ->execute([':id_avis' => $id_avis]);
                }
            }
        } else {
            // Nouveau vote
            $pdo->prepare("INSERT INTO sae._vote_avis (id_client, id_avis, type_vote) VALUES (:id_client, :id_avis, :type_vote)")
                ->execute([':id_client' => $id_client, ':id_avis' => $id_avis, ':type_vote' => $action]);
            
            if ($action === 'utile') {
                $pdo->prepare("UPDATE sae._avis SET votes_utiles = votes_utiles + 1 WHERE id_avis = :id_avis")
                    ->execute([':id_avis' => $id_avis]);
            } else {
                $pdo->prepare("UPDATE sae._avis SET votes_inutiles = votes_inutiles + 1 WHERE id_avis = :id_avis")
                    ->execute([':id_avis' => $id_avis]);
            }
        }
        
        // Redirection vers la page d'origine
        if (isset($_SERVER['HTTP_REFERER'])) {
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            header("Location: catalogue.php");
        }
        exit();
    }
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>