# Spécification du Protocole DÉLIVRAPTOR

Ce document décrit le protocole du serveur **Délivraptor (C)** qui est utilisé par le client **Alizon (PHP)**.

---

## 1. Lancement du serveur

Le serveur est lancé en ligne de commande avec **3 arguments obligatoires**.

```bash
./delivraptor -p <port> -c <capacité> -a <fichier_auth>
```

### Options

| Option | Description                                                |
| ------ | ---------------------------------------------------------- |
| `-p`   | Port TCP d’écoute                                          |
| `-c`   | Capacité maximale de la **zone restreinte** (étapes 1 à 4) |
| `-a`   | Fichier d’authentification (`login hash_md5`)              |
| `-h`   | Affiche l’aide et quitte                                   |

### Comportement au démarrage

* Refus de démarrage si un paramètre est manquant
* Chargement des utilisateurs en mémoire
* Connexion PostgreSQL établie au lancement
* Initialisation du socket TCP
* Journalisation dans `delivraptor.log`

---

## 2. Connexion réseau

* Protocole : **TCP**
* Modèle : **1 client par socket**
* Authentification persistante pendant toute la durée de la connexion
* Plusieurs clients successifs supportés (boucle `accept()`)

---

## 3. Format des messages

### Requête (Client → Serveur)

```text
COMMANDE [param1] [param2]
```

* Commandes **case-sensitive** (sauf `QUIT`)
* Une commande par ligne

### Réponse (Serveur → Client)

```text
MESSAGE\n
```


---

## 4. Authentification

### Commande : `LOGIN`

```text
LOGIN <login> <hash_md5>
```

### Description

* Compare les identifiants avec le fichier chargé au démarrage
* Une seule session par connexion

### Réponses possibles

* `Utilisateur Connecté : <login>`
* `Login incorrect`

### Remarques

* L’état `connecte = true` est conservé tant que la socket est ouverte
* Toutes les commandes métier exigent une authentification préalable

---

## 5. Génération de bordereau

### Commande : `GENERER_BORDEREAU`

```text
GENERER_BORDEREAU <numero_commande>
```

### Pré-requis

* Client authentifié
* Capacité non atteinte dans la zone restreinte (étapes 1 à 4)

### Traitement serveur

1. Vérifie l’authentification
2. Vérifie la capacité globale :

```sql
SELECT COUNT(*) FROM sae._livraison WHERE etape >= 1 AND etape <= 4
```

3. Vérifie l’unicité de la commande
4. Génère un bordereau :

```text
BORD-XXXX-NNNN-CCCC
```

5. Insère la livraison avec :

   * `etape = 1`
   * `statut = 'Preparation de la commande'`

### Réponses possibles

* `OK BORD-XXXX-NNNN-CCCC`
* `Erreur: bordereau deja existant : <id>`
* `Capacité maximale atteinte : <capacité>`
* `erreur: connexion requise`

---

## 6. Suivi de colis

### Commande : `SUIVRE_COLIS`

```text
SUIVRE_COLIS <id_bordereau>
```

### Description

Retourne **uniquement le numéro d’étape** du colis.

### Réponses possibles

* `<etape>`
* `Bordereau inconnu`
* `erreur: connexion requise`

---

## 7. Commandes étendues

Ces commandes permettent d’accéder aux informations détaillées d’une livraison.

Toutes exigent une authentification préalable.

---

### `GET_DATE_EXPED`

```text
GET_DATE_EXPED <id_bordereau>
```

Retourne la date d’expédition (`date_exped`).

---

### `GET_DATE_LIVR`

```text
GET_DATE_LIVR <id_bordereau>
```

Retourne la date réelle de livraison (`date_livraison_reel`).

---

### `GET_STATUT`

```text
GET_STATUT <id_bordereau>
```

Retourne le statut textuel du colis.

---

### `GET_RAISON_REFUS`

```text
GET_RAISON_REFUS <id_bordereau>
```

Retourne la raison du refus si existante.

---

### Réponses communes

* Valeur demandée
* `Bordereau inconnu`
* `erreur: connexion requise`

---

## 8. Gestion de session

### Commande : `QUIT`

```text
QUIT
```

### Effets

* Envoie `Au revoir !`
* Fermeture immédiate de la socket client
* le serveur attendra ensuite un nouveau client

---

## 9. Machine à états

| Étape | Description        | Zone       |
| ----- | ------------------ | ---------- |
| 0     | En attente         | Illimitée  |
| 1     | Préparation        | Restreinte |
| 2     | Acheminement       | Restreinte |
| 3     | Transporteur       | Restreinte |
| 4     | Départ plateforme  | Restreinte |
| 5     | Arrivée plateforme | Libre      |
| 6     | Centre local       | Libre      |
| 7     | Arrivée centre     | Libre      |
| 8     | Livraison          | Libre      |
| 9     | Livré / Refusé     | Libre      |

---

## 10. Base de données

### Table : `sae._livraison`

| Colonne               | Type       | Description               |
| --------------------- | ---------- | ------------------------- |
| `id_livraison`        | SERIAL     | Clé primaire              |
| `num_commande`        | INT UNIQUE | Numéro de commande Alizon |
| `bordereau`           | VARCHAR    | Identifiant bordereau     |
| `etape`               | INT        | Étape (0–9)               |
| `statut`              | TEXT       | Statut lisible            |
| `date_exped`          | DATE       | Date d’expédition         |
| `date_livraison_reel` | DATE       | Date réelle de livraison  |
| `raison_refus`        | TEXT       | Raison du refus           |

---

## 11. Logs

Fichier : `delivraptor.log`

Format :

```text
YYYY-MM-DD HH:MM:SS - [Client: IP] Action
```

Événements journalisés :

* Connexion / déconnexion client
* Authentification
* Exécution des commandes
* Erreurs fonctionnelles

---

