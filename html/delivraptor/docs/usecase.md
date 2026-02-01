# Use Cases - Service Délivraptor

Ce document présente des exemples pratiques d'utilisation du service Délivraptor par le client (Alizon).

---

## 1. Scénario : Premier achat et création de livraison

**Contexte :** Un client vient de payer sa commande (n°42) sur Alizon. Le site doit obtenir un bordereau.

### Connexion et Authentification

```
Client  : LOGIN alizon_app 70196850422c5344405370f6d0f5075d
Serveur : Utilisateur Connecté : alizon_web
```
(Hash MD5 du mot de passe)

### Demande de bordereau

```
Client  : GENERER_BORDEREAU 42
Serveur : OK BORD-GHYZ-8921-0042
```
Le serveur mémorise le lien entre la commande 42 et ce bordereau.

### Fermeture

```
Client  : QUIT
Serveur : Au revoir !
```

---

## 2. Scénario : Consultation de l'état d'un colis

**Contexte :** L'acheteur souhaite savoir où en est son colis depuis son espace client.

### Requête de suivi

```
Client  : SUIVRE_COLIS BORD-GHYZ-8921-0042
Serveur : 3
```

**Interprétation PHP :** Le site Alizon reçoit "3" et affiche au destinataire l'étape a laquelle le colis en est.

(Correspond à : **Arrivée chez le transporteur**)

---

## 3. Scénario : Gestion de la saturation du transporteur

**Contexte :** Le transporteur a une capacité limitée défini dans le lancement du serveur(ex: `-c 2`). Deux colis sont déjà en transit (étapes 1 à 4).

### Tentative de prise en charge (BLOQUÉE)

```
Client  : GENERER_BORDEREAU 45
Serveur : Capacité maximale atteinte : 2
```

**Action Alizon :** Le script PHP doit informer l'utilisateur ou mettre la demande en attente car l'étape 1 est bloquée.

### Libération de place

Le simulateur `simulation.php` est lancé. Un colis passe de l'étape 4 à l'étape 5.

### Nouvelle tentative (SUCCÈS)

```
Client  : GENERER_BORDEREAU 45
Serveur : OK BORD-RTYN-1120-0045
```

La place s'est libérée, la création est validée.

---

## 4. Scénario : Finalisation de livraison (Étape 9)

**Contexte :** Le colis a atteint l'étape finale via le simulateur.

### Demande de statut final

```
Client  : SUIVRE_COLIS BORD-GHYZ-8921-0042
Serveur : Etape: 9
```

### Cas "Livré en l'absence"

Si le statut en base est "Livré en l'absence du destinataire", le service fournit l'accès à l'image de la boîte aux lettres.

### Cas "Refusé"

Si le colis est refusé, le serveur transmet la raison (ex: "Colis endommagé") au client PHP.

---
