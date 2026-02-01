<?php
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Fonction pour envoyer un email via PHPMailer
 * 
 * @param string $destinataire Email du destinataire
 * @param string $sujet Sujet de l'email
 * @param string $corpsHTML Corps HTML de l'email
 * @return bool True si envoyé avec succès, False sinon
 */
function envoyerEmail($destinataire, $sujet, $corpsHTML) {
    try {
        $mailSend = new PHPMailer(true);
        
        // Configuration Serveur SMTP
        $mailSend->isSMTP();
        $mailSend->Host = 'smtp.gmail.com';
        $mailSend->Timeout = 10; // Timeout de 10 secondes max
        $mailSend->SMTPKeepAlive = false;

        // Options SSL
        $mailSend->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mailSend->SMTPAuth = true;
        $mailSend->Username = 'alizonlbc@gmail.com';
        $mailSend->Password = 'dbmo ihyr vjcy bzmo';
        $mailSend->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mailSend->Port = 465;

        // Expéditeur et Destinataire
        $mailSend->setFrom('alizonlbc@gmail.com', 'Alizon - Service Client');
        $mailSend->addAddress($destinataire);

        // Contenu du Mail
        $mailSend->isHTML(true);
        $mailSend->CharSet = 'UTF-8';
        $mailSend->Subject = $sujet;
        $mailSend->Body = $corpsHTML;

        $mailSend->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email : " . $mailSend->ErrorInfo);
        return false;
    }
}

/**
 * Génère le HTML pour l'email de notification de suppression d'avis
 * 
 * @param string $prenom Prénom du client
 * @param string $nom_produit Nom du produit concerné
 * @param string $raison Raison de la suppression (optionnelle)
 * @return string HTML de l'email
 */
function genererEmailSuppressionAvis($prenom, $nom_produit, $raison = null) {
    $raison_html = '';
    if ($raison) {
        $raison_html = "<p style='background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
            <strong>Raison de la suppression :</strong><br>
            " . htmlspecialchars($raison) . "
        </p>";
    }

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; padding: 30px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .header { text-align: center; border-bottom: 3px solid #dc3545; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { color: #dc3545; margin: 0; font-size: 24px; }
            .content { margin: 20px 0; }
            .product-name { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin: 15px 0; text-align: center; font-weight: bold; }
            .info-box { background-color: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
            .btn { display: inline-block; background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Suppression de votre avis</h1>
            </div>
            
            <div class="content">
                <p>Bonjour <strong>{$prenom}</strong>,</p>
                
                <p>Nous vous informons que votre avis concernant le produit suivant a été supprimé :</p>
                
                <div class="product-name">
                    {$nom_produit}
                </div>
                
                {$raison_html}
                
                <div class="info-box">
                    <strong>Vous pouvez laisser un nouvel avis</strong><br>
                    Nous vous encourageons à partager à nouveau votre expérience en respectant nos conditions d'utilisation. 
                    Vos avis sont importants pour notre communauté !
                </div>
                
                <p>Si vous pensez qu'il s'agit d'une erreur ou si vous avez des questions, n'hésitez pas à nous contacter.</p>
                
                <p style="margin-top: 30px;">Cordialement,<br><strong>L'équipe Alizon</strong></p>
            </div>
            
            <div class="footer">
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                <p>© 2026 Alizon - Tous droits réservés</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}
?>
