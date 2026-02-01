<?php
session_start();
include('loginBdd.php');
require_once 'verif.php';
$id = $_SESSION['id_compte'];
$erreurs = [];
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['send'])) {
            
            
            if (!password_verify($_POST['actMdp'], getMotDePasseParId($dbh,$id))) {
                 $erreurs['actMdp'] = 'Le mot de passe ne correspond avec celui enregistrer';
            }
            // Valider identifiant, mot de passe, email
            $newMdp =$_POST['newMdp'];
            $confMdp = $_POST['confMdp'];
            if ($newMdp !== $confMdp){
                $erreurs['confMdp'] = 'Le mot de passe n\'est pas le même';
            }
            
            $resultMdp = validerMotDePasse($_POST['newMdp']);
            if (!$resultMdp['valide']) {
                $erreurs['newMdp'] = $resultMdp['erreur'];
            }
   


            if(empty($erreurs)){
                $_SESSION['data'] = array_merge($_POST);


                try {
                $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
                $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $dbh->prepare('UPDATE sae._compte SET mot_de_passe = :mdp WHERE id_compte = :id');
                $stmt->execute([':id' => $id, 'mdp' => hasherMotDePasse($newMdp)]);
                }
                catch (PDOException $e) {
                    echo "Error: " . $e->getMessage();

                }
                if(estClient($dbh,$id)){
                header("Location: modifProfil.php");
                exit();
                }
                else if(!estClient($dbh,$id)){
                    header("Location: modifProfil.php?vendeur=1");
                    exit();
                }
             }
             else {
            // Stocker les erreurs en session pour les afficher
                $_SESSION['erreurs'] = $erreurs;
            
                
        
            }



    
    
}
// Récupérer les erreurs et les données
$erreurs = $_SESSION['erreurs'] ?? [];
unset($_SESSION['erreurs']); // Nettoyer après affichag

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="modifStyleCss2.css">
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <title>Document</title>
</head>
<body>
    
    <section>
        <header>
            <img src="media/universel/logo-header.png" alt="Logo Alizon" class="logo">
            <h1>Changement de mot de passe</h1>
        </header>
        <form action="changementMdp.php?send=1" method="post" enctype="multipart/form-data">
                    
                    <label for="actMdp">Mot de passe Actuel : *</label>
                    <br>
                    <input type="password" id="actMdp" name="actMdp" class="<?php echo isset($erreurs['actMdp']) ? 'input-erreur' : ''; ?>" required />
                    <span class="erreur-message"><?php echo isset($erreurs['actMdp']) ? htmlspecialchars($erreurs['actMdp']) : ''; ?></span>
                    <br />

                    <label for="newMdp">Nouveau Mot de passe : *</label>
                    <br>
                    <input type="password" id="newMdp" name="newMdp" class="<?php echo isset($erreurs['newMdp']) ? 'input-erreur' : ''; ?>" required />
                    <span class="erreur-message"><?php echo isset($erreurs['newMdp']) ? htmlspecialchars($erreurs['newMdp']) : ''; ?></span>
                    <br />

                    <label for="confMdp">Confirmer le Mot de passe : *</label>
                    <br>
                    <input type="password" id="confMdp" name="confMdp" class="<?php echo isset($erreurs['confMdp']) ? 'input-erreur' : ''; ?>" required />
                    <span class="erreur-message"><?php echo isset($erreurs['confMdp']) ? htmlspecialchars($erreurs['confMdp']) : ''; ?></span>
                    <br />

                    
                     
                    <?php if(estClient($dbh, $_SESSION['id_compte'])){ ?>
                        <input type="button" value="Annuler" onclick="window.location.href='modifProfil.php';" />
                    <?php }else{ ?>
                        <input type="button" value="Annuler" onclick="window.location.href='modifProfil.php?vendeur=1';" />
                    <?php } ?> 
                    

                    <input type="submit" value="Valider" class="soumettre" />
    </form>
    <footer>
            © 2025 Alizon - Tous droits réservés<br>
            <a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a> | <a href="#">Cookies</a>
        </footer>
    </section>
    <script src="validation.js"></script>
</body>
</html>
