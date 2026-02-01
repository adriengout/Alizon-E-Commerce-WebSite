<?php
session_start();
require_once 'verif.php';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $_SESSION['form_data'] = array_merge($_SESSION['form_data'] ?? [], $_POST);

    $toutEstValide = true;

    switch($step) {
        case 1:
            if (!validerEmail($_POST['email'], $mailsExistant)['valide'] ||
                !validerLogin($_POST['login'], $loginsExistant)['valide'] ||
                !validerMotDePasse($_POST['motdepasse'])['valide']) {
                $toutEstValide = false;
            }
            break;

        case 2:
            if (!validerNomEntreprise($_POST['nom_entreprise'])['valide'] ||
                !validerSiret($_POST['siret'])['valide'] ||
                !validerTelephone($_POST['tel'])['valide']) {
                $toutEstValide = false;
            }
            break;

        case 3:
            if (!validerNomAdresse($_POST['AdresseSiegeNomRue'])['valide'] ||
                !validerNumAdresse($_POST['AdresseSiegeNumRue'])['valide'] ||
                !validerCodePostal($_POST['codePostalSiege'])['valide'] ||
                !validerVille($_POST['villeSiege'])['valide']) {
                $toutEstValide = false;
            }

            if ($toutEstValide) {
                header("Location: creationCompteBdd.php");
                exit();
            }
            break;
    }

    if ($toutEstValide) {
        header("Location: creationCompteVendeur.php?step=" . ($step + 1));
        exit;
    } else {
        header("Location: creationCompteVendeur.php?step=" . $step);
        exit;
    }
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
                        <span class="progress-step <?php echo $step === 2 ? 'active' : ''; ?>">Entreprise</span>
                        <span class="progress-line"></span>
                        <span class="progress-step <?php echo $step === 3 ? 'active' : ''; ?>">Siege</span>
                    </div>
            </header>   
            <?php
            
            if ($step === 1){
            ?>
                <form action="creationCompteVendeur.php?step=1" method="post">

                <label for="login">Identifiant : *</label>
                <br>
                <input type="text" id="login" name="login"
                       value="<?php echo htmlspecialchars($data['login'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <label for="motdepasse">Mot de passe : *</label>
                <br>
                <input type="password" id="motdepasse" name="motdepasse" required />
                <span class="erreur-message"></span>
                <br />

                <label for="email">Adresse mail : *</label>
                <br>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <input type="button" value="Précédent" onclick="window.location.href='index.php';" />
                <input type="submit" value="Suivant" class="soumettre" />
            </form>
        
            <?php }   ?>
            <?php if ($step === 2){ ?>
                <form action="creationCompteVendeur.php?step=2" method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom_entreprise">Nom entreprise : *</label>
                        <br>
                        <input type="text" id="nom_entreprise" name="nom_entreprise"
                               value="<?php echo htmlspecialchars($data['nom_entreprise'] ?? ''); ?>"
                               required />
                        <span class="erreur-message"></span>
                        <br />
                    </div>
                    <div class="form-group">
                        <label for="siret">Siret : *</label>
                        <br>
                        <input type="text" id="siret" name="siret"
                               value="<?php echo htmlspecialchars($data['siret'] ?? ''); ?>"
                               required />
                        <span class="erreur-message"></span>
                        <br />
                    </div>
                </div>

                <label for="tel">Téléphone : *</label>
                <br>
                <input type="tel" id="tel" name="tel"
                       value="<?php echo htmlspecialchars($data['tel'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <label for="description_vendeur">Description du vendeur : *</label>
                <br>
                <textarea id="description_vendeur" name="description_vendeur"
                        rows="5" cols="50" maxlength="500" required><?php echo htmlspecialchars($data['description_vendeur'] ?? ''); ?></textarea>
                <span class="erreur-message"></span>
                <br />

                <input type="button" value="Précédent" onclick="window.location.href='creationCompteVendeur.php?step=<?php echo $step - 1; ?>';"/>
                <input type="submit" value="Suivant" class="soumettre" />
            </form>
            <?php }
            
            if ($step === 3){ ?>
                <form action="creationCompteVendeur.php?step=3" method="post">

                <label for="AdresseSiegeNomRue">Nom rue : *</label>
                <br>
                <input type="text" id="AdresseSiegeNomRue" name="AdresseSiegeNomRue"
                       value="<?php echo htmlspecialchars($data['AdresseSiegeNomRue'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <label for="AdresseSiegeNumRue">Num Rue : *</label>
                <br>
                <input type="text" id="AdresseSiegeNumRue" name="AdresseSiegeNumRue"
                       value="<?php echo htmlspecialchars($data['AdresseSiegeNumRue'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <label for="complementAdresseSiege">Complement Adresse : </label>
                <br>
                <input type="text" id="complementAdresseSiege" name="complementAdresseSiege"
                       value="<?php echo htmlspecialchars($data['complementAdresseSiege'] ?? ''); ?>" />
                <span class="erreur-message"></span>
                <br />

                <label for="codePostalSiege">Code Postal : *</label>
                <br>
                <input type="text" id="codePostalSiege" name="codePostalSiege"
                       value="<?php echo htmlspecialchars($data['codePostalSiege'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <label for="villeSiege">Ville : *</label>
                <br>
                <input type="text" id="villeSiege" name="villeSiege"
                       value="<?php echo htmlspecialchars($data['villeSiege'] ?? ''); ?>"
                       required />
                <span class="erreur-message"></span>
                <br />

                <input type="button" value="Précédent" onclick="window.location.href='creationCompteVendeur.php?step=<?php echo $step - 1; ?>';"/>
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