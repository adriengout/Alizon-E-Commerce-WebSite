<?php
session_start();

require_once("loginBdd.php");




try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
$id_compte = $_SESSION['id_compte'] ?? 1; 

//supprimer les notifs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    //Supprimer une notif
    if ($_POST['action'] === 'delete_one' && isset($_POST['id_notif'])) {
        $stmtDel = $pdo->prepare("DELETE FROM sae._notification WHERE id_notif = :id_notif AND id_compte = :id_compte");
        $stmtDel->execute([
            'id_notif' => $_POST['id_notif'],
            'id_compte' => $id_compte
        ]);
    }
    
    // supprimer toutes les notifs
    elseif ($_POST['action'] === 'delete_all') {
        $stmtDelAll = $pdo->prepare("DELETE FROM sae._notification WHERE id_compte = :id_compte");
        $stmtDelAll->execute(['id_compte' => $id_compte]);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// recuperer les notifs
$stmt = $pdo->prepare("SELECT * FROM sae._notification 
                       WHERE id_compte = :id_compte
                       ORDER BY date_creation DESC, id_notif DESC"
                    );

$stmt->execute(['id_compte' => $id_compte]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="notifications.css">
    <title>Mes Notifications | Alizon</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
</head>
<body>
    <?php include 'header.php'; ?>
    <main>
        <section class="notifications-section">
            <div class="section-header">
                <h2>Mes Notifications</h2>
                
                <?php if (!empty($notifications)) : ?>
                    <form method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer toutes vos notifications ?');">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn-delete-all">Tout supprimer</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)) : ?>
                <p style="text-align: center; color: #666; margin-top: 30px;">Aucune notification pour le moment.</p>
                <div style="height: 350px;"></div>
            <?php else : ?>
                <?php foreach ($notifications as $notification) : 
                    $isUnread = !$notification['lue']; 
                ?>
                    <div class="notification-card <?= $isUnread ? 'unread' : '' ?>">
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="delete_one">
                            <input type="hidden" name="id_notif" value="<?= $notification['id_notif'] ?>">
                            <button type="submit" class="btn-delete-one" title="Supprimer cette notification">&times;</button>
                        </form>

                        <div class="notif-header">
                            <span class="notif-type"><?= htmlspecialchars($notification['type_notification']) ?></span>
                            <span class="notif-date"><?= date('d/m/Y', strtotime($notification['date_creation'])) ?></span>
                        </div>
                        <h3 class="notif-title"><?= htmlspecialchars($notification['titre']) ?></h3>
                        <p class="notif-message"><?= htmlspecialchars($notification['message']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>
    </main>
    <?php include "footer.php" ?>
</body>
</html>
