<?php session_start();
include('loginBdd.php');
require_once 'verif.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if (isset($_SESSION['form_data']['nom'])){
    $nom = $_SESSION['form_data']['nom'];
    $prenom = $_SESSION['form_data']['prenom'];
    $dateNaissance = $_SESSION['form_data']['dateNaissance'];
    $identifiant = $_SESSION['form_data']['login'];
    $mdp = $_SESSION['form_data']['motdepasse'];
    $mail = $_SESSION['form_data']['email'];
    $tel = $_SESSION['form_data']['tel'];

    $nomFichierImgProfil = 'defaultImg';
    $cheminFichierImgProfil = 'media/profils/';
    $extImgProfil = '.jpg';
    $altImgProfil = 'image de profil';

    $codePostal = $_SESSION['form_data']['codePostalFacturation'];
    $ville = $_SESSION['form_data']['villeFacturation'];
    $complement_adresse = $_SESSION['form_data']['complementAdresseFatc'];
    $num_rue = $_SESSION['form_data']['AdresseFactNumrue'];
    $nom_rue = $_SESSION['form_data']['AdresseFactNomRue'];

    try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbh->beginTransaction();

    $stmt = $dbh->prepare('
        INSERT INTO sae._image (nom_fichier, chemin, extension, alt)
        VALUES (:nom_fichier, :chemin, :extension, :alt)
        RETURNING id_image
    ');
    $stmt->execute([
        ':nom_fichier' => $nomFichierImgProfil,
        ':chemin'      => $cheminFichierImgProfil,
        ':extension'   => $extImgProfil,
        ':alt'         => $altImgProfil
    ]);
    $id_image = $stmt->fetchColumn();

    $stmt = $dbh->prepare('
        INSERT INTO sae._compte (login, mot_de_passe, mail, tel, id_image)
        VALUES (:login, :mdp, :mail, :tel, :id_image)
        RETURNING id_compte
    ');
    $stmt->execute([
        ':login'    => $identifiant,
        ':mdp'      => hasherMotDePasse($mdp),
        ':mail'     => $mail,
        ':tel'      => $tel,
        ':id_image' => $id_image
    ]);
    $id_compte = $stmt->fetchColumn();

    $stmt = $dbh->prepare('
        INSERT INTO sae._client (id_client, nom, prenom, date_naissance, compe_bloquer)
        VALUES (:id_client, :nom, :prenom, :date_naissance, FALSE)
    ');
    $stmt->execute([
        ':id_client'      => $id_compte,
        ':nom'            => $nom,
        ':prenom'         => $prenom,
        ':date_naissance' => $dateNaissance
    ]);

    $stmt = $dbh->prepare("
        INSERT INTO sae._adresse (ville, complement_adresse, code_postal, num_rue, nom_rue, id_client, id_vendeur, type)
        VALUES (:ville, :complement_adresse, :code_postal, :num_rue, :nom_rue, :id_client, null, 'Domicile')
    ");
    $stmt->execute([
        ':ville' => $ville,
        ':complement_adresse'=> $complement_adresse,
        ':code_postal' => $codePostal,
        ':num_rue' => $num_rue,
        ':nom_rue' => $nom_rue,
        ':id_client' => $id_compte
    ]);

    $dbh->commit();
    } catch (PDOException $e) {
        if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
        echo "X Erreur : " . $e->getMessage();
    }

}
else{
    $commission_cobrec = 0.5;
    $identifiant = $_SESSION['form_data']['login'];
    $mdp = $_SESSION['form_data']['motdepasse'];
    $mail = $_SESSION['form_data']['email'];

    $tel = $_SESSION['form_data']['tel'];
    $nomEntreprise = $_SESSION['form_data']['nom_entreprise'];
    $siret = str_replace(" ", "", $_SESSION['form_data']['siret']);
    $description_vendeur = $_SESSION['form_data']['description_vendeur'];

    $nomFichierImgProfil = 'defaultImg';
    $cheminFichierImgProfil = '/media/profils/';
    $extImgProfil = '.jpg';
    $altImgProfil = 'image de l\' entreprise';

    $codePostal = $_SESSION['form_data']['codePostalSiege'];
    $ville = $_SESSION['form_data']['villeSiege'];
    $complement_adresse = $_SESSION['form_data']['complementAdresseSiege'];
    $num_rue = $_SESSION['form_data']['AdresseSiegeNumRue'];
    $nom_rue = $_SESSION['form_data']['AdresseSiegeNomRue'];

    try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dbh->beginTransaction();

    $stmt = $dbh->prepare('
        INSERT INTO sae._image (nom_fichier, chemin, extension, alt)
        VALUES (:nom_fichier, :chemin, :extension, :alt)
        RETURNING id_image
    ');
    $stmt->execute([
        ':nom_fichier' => $nomFichierImgProfil,
        ':chemin'      => $cheminFichierImgProfil,
        ':extension'   => $extImgProfil,
        ':alt'         => $altImgProfil
    ]);
    $id_image = $stmt->fetchColumn();

    $stmt = $dbh->prepare('
        INSERT INTO sae._compte (login, mot_de_passe, mail, tel, id_image)
        VALUES (:login, :mdp, :mail, :tel, :id_image)
        RETURNING id_compte
    ');
    $stmt->execute([
        ':login'    => $identifiant,
        ':mdp'      => hasherMotDePasse($mdp),
        ':mail'     => $mail,
        ':tel'      => $tel,
        ':id_image' => $id_image
    ]);
    $id_compte = $stmt->fetchColumn();

    $stmt = $dbh->prepare('
        INSERT INTO sae._vendeur (id_vendeur, nom_entreprise, siret, description_vendeur, comission_cobrec)
        VALUES (:id_vendeur, :nom_entreprise, :siret, :description_vendeur, :commission_cobrec)
    ');
    $stmt->execute([
        ':id_vendeur'     => $id_compte,
        ':nom_entreprise' => $nomEntreprise,
        ':siret'          => $siret,
        ':description_vendeur' => $description_vendeur,
        ':commission_cobrec' => $commission_cobrec
    ]);

    $stmt = $dbh->prepare('
        INSERT INTO sae._adresse (ville, complement_adresse, code_postal, num_rue, nom_rue, id_client, id_vendeur, type)
        VALUES (:ville, :complement_adresse, :code_postal, :num_rue, :nom_rue, null, :id_compte, :type)
    ');
    $stmt->execute([
        ':ville'      => $ville,
        ':complement_adresse'            => $complement_adresse,
        ':code_postal'         => $codePostal,
        ':num_rue' => $num_rue,
        ':nom_rue' => $nom_rue,
        ':id_compte' => $id_compte,
        ':type' => 'siege'
    ]);

    $dbh->commit();

} catch (PDOException $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    echo "X Erreur : " . $e->getMessage();
}
}

/*
try {
        $mailSend = new PHPMailer(true);
        $mailSend->isSMTP();
        $mailSend->Host       = 'smtp.gmail.com';

        $mailSend->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mailSend->SMTPAuth   = true;
        $mailSend->Username   = 'alizonlbc@gmail.com';
        $mailSend->Password   = 'dbmo ihyr vjcy bzmo';
        $mailSend->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mailSend->Port       = 465;

        $mailSend->setFrom('alizonlbc@gmail.com', 'Bienvenue chez Alizon');
        $mailSend->addAddress($mail);

        $mailSend->isHTML(true);
        $mailSend->CharSet = 'UTF-8';
        $mailSend->Subject = "Bienvenue sur Alizon ! Votre compte est créé";

        $mailSend->Body = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                .header { text-align: center; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; margin-bottom: 20px; }
                .btn { display: inline-block; background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
                .footer { margin-top: 30px; font-size: 0.8em; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Bienvenue chez Alizon !</h2>
                </div>

                <p>Bonjour,</p>

                <p>Félicitations ! Votre compte client a été créé avec succès.</p>

                <p>Vous pouvez dès à présent vous connecter pour profiter de tous nos services, suivre vos commandes et gérer vos informations personnelles.</p>

                <p style="text-align: center; margin: 30px 0;">
                    <a href='http://localhost:8888/connexion.php' class="btn" style="color: white;">Se connecter à mon compte</a>
                </p>

                <div class="footer">
                    <p>Merci de votre confiance,<br>L'équipe Alizon</p>
                </div>
            </div>
        </body>
        </html>
        HTML;

        $mailSend->send();

    } catch (Exception $e) {
    }
*/

unset($_SESSION['form_data']);
unset($_SESSION['erreurs']);
header("Location: connexion.php");
exit();
?>
