<?php
session_start();
include('loginBdd.php');
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Erreur BDD : ' . $e->getMessage());
}

$email = "";
$step = "email";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["email"])) {
        $email = trim($_POST["email"]);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $step = "password";
            $_SESSION['email_login'] = $email;
            $_SESSION['email'] = $email;
        } else {
            $error = "Adresse email invalide.";
        }
    }

     elseif (isset($_POST["password"])) {
        $password = $_POST["password"];
        echo "<p>valide redirige vers acceuil</p>";
        exit;
    }

    elseif (isset($_POST["forgot"])) {
        $step = "reset";

        if(isset($_SESSION['email_login'])) {

            $email_dest = $_SESSION['email_login'];

            $token = bin2hex(random_bytes(32));

            $req = $dbh->prepare("UPDATE sae._compte SET token_reset = ?, token_expiration = NOW() + INTERVAL '1 hour' WHERE mail = ?");
            $req->execute(array($token, $email_dest));

            $lien = "http://10.253.5.111/reset_password.php?token=" . $token;

            echo "<p style='background:#ffffcc;padding:15px;border:1px solid #ccc;margin:20px;'>
                    <strong>Mode développement :</strong> L'envoi d'email est désactivé.<br>
                    Lien de réinitialisation : <a href='$lien'>$lien</a>
                  </p>";
            /*
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->SMTPAuth   = true;
                $mail->Username   = 'alizonlbc@gmail.com';
                $mail->Password   = 'dbmo ihyr vjcy bzmo';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('alizonlbc@gmail.com', 'Alizon Securite');
                $mail->addAddress($email_dest);

                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';

                $mail->Subject = "Réinitialisation de votre mot de passe - Alizon";

                $mail->Body = <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; }
                        .container { width: 80%; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px; background-color: #f9f9f9; }
                        .footer { margin-top: 20px; font-size: 0.8em; color: #777777; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2>Bonjour,</h2>
                        <p>Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte sur <strong>Alizon</strong>.</p>

                        <p>Si vous êtes à l'origine de cette demande, veuillez cliquer sur le lien ci-dessous pour créer un nouveau mot de passe :</p>

                        <p style="margin: 20px 0; font-size: 1.1em;">
                            <a href="$lien" style="color: #0000EE; text-decoration: underline;">Réinitialiser mon mot de passe</a>
                        </p>

                        <hr>

                        <p class="footer">
                            <strong>Attention :</strong> Ce lien est valide pour une durée limitée.<br>
                            Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet email. Votre mot de passe actuel restera inchangé.
                        </p>
                    </div>
                </body>
                </html>
                HTML;

                $mail->send();

            } catch (Exception $e) {
                echo "<h1 style='color:red'>ÉCHEC DE L'ENVOI</h1>";
                echo "<strong>Erreur Mailer :</strong> " . $mail->ErrorInfo;
                exit;
            }
            */

        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="modifStyleCss2.css">
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <title>Alizon - Connexion</title>
</head>
<body>

    <section>
        <header>
            <img src="media/universel/logo-header.png" alt="Logo Alizon" class="logo">
            <h1>Se connecter</h1>
        </header>

        <?php if ($step === "email"): ?>
            <form method="post">
                <label for="email">Adresse Mail : *</label>
                <input type="email" id="email" name="email" required>
                <?php if (isset($_GET['error']) && $_GET['error'] == 1){?>
                    <span class="erreur-message">Email ou Mot de passe incorrect</span>
                <?php }?>

                <input type="button" value="Précédent" onclick="window.location.href='index.php';" />
                <input type="submit" value="Continuer" class="soumettre" />
            </form>

            <hr class="separateur-compte">
            <p class="lien-autre-compte">
                Pas encore de compte ?
                <a href="creationCompteClient.php">Créer un compte</a>
            </p>

         <?php elseif ($step === "password"):
            $_SESSION['email'] = $_SESSION['email_login'] ?? "";
            ?>

            <form action="connexionVerifRole.php" method="post">
                <label for="password">Mot de passe : *</label>
                <input type="password" id="password" name="password" required>

                <input type="button" value="Précédent" onclick="window.location.href='connexion.php';" />
                <input type="submit" value="Se connecter" class="soumettre" />
            </form>

            <form method="post" style="margin-top: 15px;">
                <input type="hidden" name="forgot" value="1">
                <p class="lien-autre-compte" style="margin: 0;">
                    <a href="#" onclick="this.closest('form').submit(); return false;">
                        Mot de passe oublié ? Cliquez ici pour le récupérer
                    </a>
                </p>
            </form>

        <?php elseif ($step === "reset"): ?>
            <p style="text-align: center; margin-bottom: 20px;">
                Un mail pour réinitialiser votre mot de passe vient de vous être envoyé à l'adresse renseignée sur votre compte.
            </p>
            <form method="get">
                <input type="submit" value="Recommencer la connexion" class="soumettre" style="width: 100%;" />
            </form>
        <?php endif; ?>

        <footer>
            © 2025 Alizon - Tous droits réservés<br>
            <a href="#">Mentions légales</a> |
            <a href="#">Politique de confidentialité</a> |
            <a href="#">Cookies</a>
        </footer>
    </section>
</body>

</html>
