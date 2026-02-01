<?php
session_start();
require_once 'verif.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

$toutEstValide = true;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $_SESSION['form_data'] = array_merge($_SESSION['form_data'] ?? [], $_POST);

    switch($step) {
        case 1:
            if(isset($_POST['email'])){
                $checkEmail = validerEmail($_POST['email'], $mailsExistant);
                $checkLogin = validerLogin($_POST['login'], $loginsExistant);
                $checkMdp   = validerMotDePasse($_POST['motdepasse']);
                if (!$checkEmail['valide'] && !$checkLogin['valide'] && !$checkMdp['valide']) {
                    $$toutEstValide = false;
                }
            }
            break;

        case 2:
            if (isset($_POST['nom']) && isset($_POST['tel'])){
                if (!validerNomPrenom($_POST['nom'])['valide'] ||
                    !validerNomPrenom($_POST['prenom'])['valide'] ||
                    !validerTelephone($_POST['tel'])['valide'] ||
                    !validerDateNaissance($_POST['dateNaissance'])['valide']) {
                    $toutEstValide = false;
                }
            break;
            }
            
        case 3:
            if (isset($_POST['AdresseFactNomRue'])) {
                $checkNomRue = validerNomAdresse($_POST['AdresseFactNomRue']);
                $checkNumRue = validerNumAdresse($_POST['AdresseFactNumrue']);
                $checkCp = validerCodePostal($_POST['codePostalFacturation']);
                $checkVille  = validerVille($_POST['villeFacturation']);
                $complement  = $_POST['complementAdresseFatc'] ?? ''; 
                $checkCompl  = validerComplAdresse($complement);

                if ($checkNomRue['valide'] && $checkNumRue['valide'] && $checkCp['valide'] && $checkVille['valide'] && $checkCompl['valide']) {
                    $toutEstValide = true;
                }
            }
            break;
    }
    
    if ($toutEstValide) {
        header("Location: creationCompteClient.php?step=" . ($step + 1));
        exit;
    } else {
        header("Location: creationCompteClient.php?step=" . $step);
        exit;
    }
}

if ($step === 4 && $toutEstValide){
    print_r($_SESSION['form_data']);
    header("Location: creationCompteBdd.php");
    exit();
}


$data = $_SESSION['form_data'] ?? [];
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
                    <h1>Creer un compte</h1>
                    <div class="progress-indicator">
                        <span class="progress-step <?php echo $step === 1 ? 'active' : ''; ?>">Connection</span>
                        <span class="progress-line"></span>
                        <span class="progress-step <?php echo $step === 2 ? 'active' : ''; ?>">Perso</span>
                        <span class="progress-line"></span>
                        <span class="progress-step <?php echo $step === 3 ? 'active' : ''; ?>">Adresse</span>
                    </div>
            </header>   
            <?php
            
            if ($step === 1){ ?>
                <form action="creationCompteClient.php?step=1" method="post">
                
                <label for="login">Identifiant : *</label><br>
                <input type="text" id="login" name="login" 
                    value="<?php echo htmlspecialchars($data['login'] ?? ''); ?>" required />
                <span class="erreur-message"></span>
                <br />

                <label for="motdepasse">Mot de passe : *</label><br>
                <input type="password" id="motdepasse" name="motdepasse" required />
                <span class="erreur-message"></span>
                <br />

                <label for="email">Adresse mail : *</label><br>
                <input type="email" id="email" name="email" 
                    value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>" required />
                <span class="erreur-message"></span>
                <br />
                
                <input type="button" value="Précédent" onclick="window.location.href='index.php';" />
                <input type="submit" value="Suivant" class="soumettre" />
            </form>
            <a href="creationCompteVendeur.php" class="link-button">Créer un compte vendeur</a>
            <?php } 
            if ($step === 2){ ?>
                <form action="creationCompteClient.php?step=2" method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom : *</label><br>
                        <input type="text" id="nom" name="nom" 
                            value="<?php echo htmlspecialchars($data['nom'] ?? ''); ?>" required />
                        <span class="erreur-message"></span>
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom : *</label><br>
                        <input type="text" id="prenom" name="prenom" 
                            value="<?php echo htmlspecialchars($data['prenom'] ?? ''); ?>" required />
                        <span class="erreur-message"></span>
                    </div>
                </div>
                
                <label for="dateNaissance">Date de naissance : *</label><br>
                <input type="date" id="dateNaissance" name="dateNaissance" 
                    value="<?php echo htmlspecialchars($data['dateNaissance'] ?? ''); ?>" required />
                <span class="erreur-message"></span>
                <br />

                <label for="tel">Téléphone : *</label><br>
                <input type="tel" id="tel" name="tel" 
                    value="<?php echo htmlspecialchars($data['tel'] ?? ''); ?>" required />
                <span class="erreur-message"></span>
                <br />

                <input type="button" value="Précédent" onclick="window.location.href='creationCompteClient.php?step=<?php echo $step - 1; ?>';"/>
                <input type="submit" value="Suivant" class="soumettre" />
            </form>
            <?php } 
            
            if ($step === 3){ ?>
                <form action="creationCompteClient.php?step=3" method="post">
                
                <label for="AdresseFactNomRue">Nom rue : *</label>
                <br>
                <input type="text" id="AdresseFactNomRue" name="AdresseFactNomRue" 
                       value="<?php echo htmlspecialchars($data['AdresseFactNomRue'] ?? ''); ?>" required />
                <span class="erreur-message"></span>
                <br />
                
                <label for="AdresseFactNumrue">Num Rue : *</label>
                <br>
                <input type="text" id="AdresseFactNumRue" name="AdresseFactNumrue" 
                       value="<?php echo htmlspecialchars($data['AdresseFactNumrue'] ?? ''); ?>" required />
                <span class="erreur-message"></span>
                <br>
                <label for="complementAdresseFatc">Complement Adresse : </label>
                <br>
                <input type="text" id="complementAdresseFatc" name="complementAdresseFatc" 
                       value="<?php echo htmlspecialchars($data['complementAdresseFatc'] ?? ''); ?>" />
                <span class="erreur-message"></span>
                <br />
                

                <label for="codePostalFacturation">Code Postal : *</label>                <br>
                <input type="text" id="codePostalFacturation" name="codePostalFacturation" 
                       value="<?php echo htmlspecialchars($data['codePostalFacturation'] ?? ''); ?>" required/>
                <span class="erreur-message"></span>
                <br />

                <label for="villeFacturation">Ville : *</label>
                 <input type="text" id="villeFacturation" name="villeFacturation" 
                       value="<?php echo htmlspecialchars($data['villeFacturation'] ?? ''); ?>" required/>
                <span class="erreur-message"></span>
                <br />

                <input type="button" value="Précédent" onclick="window.location.href='creationCompteClient.php?step=<?php echo $step - 1; ?>';"/>
                <input type="submit" value="Suivant" class="soumettre" />
            </form>
            <?php } ?>
            <footer>
                © 2025 Alizon - Tous droits réservés<br>
                <a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a> | <a href="#">Cookies</a>
            </footer>
        </section>
        <script src="validation.js"></script>
    </body>
</html>
