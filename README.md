# TalentFlow — Plateforme de Gestion RH & Recrutement

TalentFlow est une application web complète de gestion des ressources humaines et du recrutement, développée avec **Symfony 6.4** (PHP 8.2) et **MySQL**. Elle couvre l'ensemble du cycle de recrutement : publication d'offres, candidatures, entretiens, décisions finales, messagerie, forum communautaire et intégration JavaFX.

---

## Table des matières

1. [Prérequis](#prérequis)
2. [Installation rapide](#installation-rapide)
3. [Configuration](#configuration)
4. [Démarrer le serveur](#démarrer-le-serveur)
5. [Structure du projet](#structure-du-projet)
6. [Fonctionnalités](#fonctionnalités)
7. [Rôles et accès](#rôles-et-accès)
8. [API JavaFX](#api-javafx)
9. [Tests](#tests)
10. [Commandes utiles](#commandes-utiles)
11. [Dépannage](#dépannage)

---

## Prérequis

| Outil | Version minimale | Téléchargement |
|-------|-----------------|----------------|
| PHP | 8.1+ | [XAMPP 8.x](https://www.apachefriends.org/) |
| MySQL | 8.0+ | Inclus dans XAMPP |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/) |
| Node.js | 18+ (optionnel, pour assets) | [nodejs.org](https://nodejs.org/) |

> **Windows / XAMPP** : les chemins ci-dessous supposent une installation XAMPP standard sous `C:\xampp\`.

---

## Installation rapide

### 1. Cloner le projet

```bash
git clone https://github.com/YasmineMansour/TalentFlow-Symfony.git
cd TalentFlow-Symfony
```

### 2. Installer les dépendances PHP

```bash
# Avec Composer global
composer install

# Ou avec le Composer de XAMPP (Windows)
C:\xampp\php\php.exe C:\xampp\php\composer.phar install
```

### 3. Créer la base de données

Démarrez MySQL (via XAMPP Control Panel), puis :

```bash
# Créer la base
php bin/console doctrine:database:create

# Appliquer toutes les migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

Ou importer le dump SQL complet directement :

```bash
C:\xampp\mysql\bin\mysql.exe -u root talent_flow_db < database/talent_flow_unified.sql
```

### 4. Configurer l'environnement

Copiez `.env` en `.env.local` et adaptez :

```bash
cp .env .env.local
```

Editez `.env.local` (voir section [Configuration](#configuration)).

---

## Configuration

### Variables d'environnement (`.env.local`)

```dotenv
# Application
APP_ENV=dev
APP_SECRET=CHANGEZ_CE_SECRET_EN_PRODUCTION

# Base de données (XAMPP par défaut)
DATABASE_URL="mysql://root:@127.0.0.1:3306/talent_flow_db?serverVersion=8.0.32&charset=utf8mb4"

# Mailer — Gmail (remplacez par vos identifiants)
MAILER_DSN=gmail://votre_email:votre_mot_de_passe_app@default

# Messenger (file de messages via Doctrine)
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

# Live Coding — serveur de code runner (optionnel)
RUNNER_BASE_URL=http://127.0.0.1:9001

# API Video — Daily.co (optionnel, pour les entretiens vidéo)
DAILY_API_KEY=votre_cle_daily

# Brevo — SMS et emails transactionnels (optionnel)
BREVO_API_KEY=votre_cle_brevo
BREVO_FROM_EMAIL=no-reply@talentflow.local
BREVO_FROM_NAME=TalentFlow
BREVO_SMS_SENDER=TalentFlow
BREVO_SMS_ENABLED=0

# API JavaFX (ne pas changer si vous utilisez le DAO fourni)
JAVA_INTEGRATION_API_KEY=talentflow-java-api-key-2026
```

> **Note Gmail** : il faut activer la validation en 2 étapes et générer un [mot de passe d'application](https://myaccount.google.com/apppasswords) dans votre compte Google.

---

## Démarrer le serveur

### Serveur PHP intégré (développement)

```bash
# Avec PHP global
php -S 127.0.0.1:8000 -t public

# Avec XAMPP (Windows)
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

Accédez ensuite à : **http://127.0.0.1:8000**

### Avec Symfony CLI (si installé)

```bash
symfony server:start
```

---

## Structure du projet

```
TalentFlow-Symfony/
├── assets/                  # JS/CSS (Stimulus, AssetMapper)
├── config/
│   ├── packages/            # Config bundles (security, doctrine, mailer…)
│   └── routes/              # Fichiers de routes additionnels
├── database/
│   └── talent_flow_unified.sql  # Dump complet de la BDD
├── migrations/              # Migrations Doctrine
├── public/
│   ├── index.php            # Point d'entrée
│   ├── uploads/             # Fichiers uploadés (avatars, logos, pièces jointes)
│   └── java-integration/    # DAOs Java pour l'intégration JavaFX
│       ├── UserApiDAO.java
│       └── PostApiDAO.java
├── src/
│   ├── Controller/          # Tous les contrôleurs HTTP + API
│   ├── Entity/              # Entités Doctrine (User, Offre, Candidature…)
│   ├── Form/                # Formulaires Symfony
│   ├── Repository/          # Repositories Doctrine
│   ├── Service/             # Services métier (IA, mailer, PDF…)
│   ├── Security/            # Voters, authenticators
│   ├── EventListener/       # Listeners Symfony
│   └── EventSubscriber/     # Subscribers
├── templates/               # Vues Twig
│   ├── base.html.twig
│   ├── admin/
│   ├── candidature/
│   ├── offre/
│   ├── forum/ (post/, comment/)
│   ├── entretien/
│   └── ...
├── tests/                   # Tests PHPUnit
├── .env                     # Variables d'environnement (template)
├── composer.json
└── importmap.php
```

---

## Fonctionnalités

### Gestion des utilisateurs
- Inscription / Connexion avec **authentification 2FA par email**
- Récupération de mot de passe (lien magique + token)
- Gestion de profil (avatar, informations personnelles)
- Blocage / déblocage de compte par l'admin
- Historique des connexions (`UserLog`)

### Offres d'emploi
- CRUD complet des offres (titre, description, salaire, catégorie, entreprise)
- Pagination (KnpPaginator)
- Filtres par catégorie, localisation, type de contrat
- Export PDF des offres
- Statistiques de vues et de candidatures

### Candidatures
- Dépôt de candidature avec CV (upload VichUploader)
- Suivi du statut : `en_attente → en_cours → acceptée / refusée`
- Historique des changements de statut
- **Analyse IA** de la candidature (score de compatibilité, suggestions)
- Notification email à chaque changement de statut

### Entretiens
- Planification d'entretiens (date, heure, type)
- **Entretiens vidéo** via Daily.co
- **Live Coding** intégré : éditeur de code en temps réel avec exécution
- Diagnostic du live coding
- Entretiens RH + techniques

### Décision finale
- Workflow décision : Approuvé / Refusé / En attente
- Notification automatique au candidat par email

### Forum communautaire
- Posts avec upvotes/downvotes
- Commentaires imbriqués
- Signalement de contenu inapproprié (`PostReport`)
- Modération admin (masquer / supprimer un post)
- Upload d'images et d'audio dans les posts

### Messagerie privée
- Chat entre utilisateurs (RH ↔ Candidat)
- Historique des conversations
- **Temps réel avec Pusher** (WebSockets)

### Chatbot
- Assistant IA intégré pour guider les candidats

### Administration
- Tableau de bord avec statistiques globales
- Gestion des utilisateurs, entreprises, catégories, avantages
- Logs d'activité

### Internationalisation
- Interface disponible en **Français** et **Anglais** (`/fr/`, `/en/`)
- Changement de langue dynamique

---

## Rôles et accès

| Rôle | Description | Accès principal |
|------|-------------|-----------------|
| `ROLE_ADMIN` | Administrateur système | Tout + tableau de bord admin |
| `ROLE_RH` | Responsable RH | Offres, candidatures, entretiens, décisions |
| `ROLE_CANDIDAT` | Candidat | Offres publiques, ses candidatures, forum, messagerie |
| `ROLE_USER` | Utilisateur de base | Accès limité (forum, profil) |

### Compte admin par défaut (après import du dump)

```
Email    : ahmed@talentflow.tn
Password : Admin1234!
```

> Changez ce mot de passe immédiatement après la première connexion.

---

## API JavaFX

L'application expose une API REST pour l'intégration avec l'application desktop **JavaFX**.

### Authentification

Toutes les requêtes doivent inclure le header :

```
X-API-KEY: talentflow-java-api-key-2026
```

### Endpoints disponibles

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/integration/users` | Liste tous les utilisateurs |
| `GET` | `/api/integration/users/{id}` | Détail d'un utilisateur |
| `POST` | `/api/integration/users` | Créer un utilisateur |
| `PUT` | `/api/integration/users/{id}` | Modifier un utilisateur |
| `DELETE` | `/api/integration/users/{id}` | Supprimer un utilisateur |
| `GET` | `/api/integration/stats` | Statistiques globales |
| `GET` | `/api/integration/posts` | Liste des posts du forum |
| `GET` | `/api/integration/posts/{id}` | Détail post + commentaires |
| `POST` | `/api/integration/posts` | Créer un post |
| `PUT` | `/api/integration/posts/{id}` | Modifier un post |
| `DELETE` | `/api/integration/posts/{id}` | Supprimer un post |
| `GET` | `/api/integration/posts/{id}/comments` | Commentaires d'un post |
| `POST` | `/api/integration/posts/{id}/comments` | Ajouter un commentaire |
| `DELETE` | `/api/integration/comments/{id}` | Supprimer un commentaire |

### Exemple de requête (cURL)

```bash
curl -H "X-API-KEY: talentflow-java-api-key-2026" \
     http://127.0.0.1:8000/api/integration/posts
```

### DAOs Java fournis

Les fichiers suivants sont disponibles dans `public/java-integration/` :

- `UserApiDAO.java` — CRUD utilisateurs
- `PostApiDAO.java` — CRUD posts + commentaires

Copiez ces fichiers dans votre projet JavaFX (adaptez le package `org.example.dao`).

---

## Tests

```bash
# Lancer tous les tests
php bin/phpunit

# Avec XAMPP
C:\xampp\php\php.exe bin/phpunit

# Un seul fichier de test
php bin/phpunit tests/Controller/UserControllerTest.php
```

---

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Créer une nouvelle migration
php bin/console make:migration

# Appliquer les migrations
php bin/console doctrine:migrations:migrate

# Lister toutes les routes
php bin/console debug:router

# Créer un utilisateur admin en CLI
php bin/console app:create-admin

# Vérifier la configuration Symfony
php bin/console about

# Activer/désactiver le mode debug
# → Modifier APP_ENV=prod et APP_DEBUG=0 dans .env.local
```

---

## Dépannage

### `vendor/autoload_runtime.php: No such file`
```bash
composer install
```

### `An exception occurred while executing a query: SQLSTATE[42S02]`
La table n'existe pas. Appliquez les migrations :
```bash
php bin/console doctrine:migrations:migrate
```

### Erreur 500 sur toutes les pages
```bash
php bin/console cache:clear
# Vérifiez les logs :
# var/log/dev.log
```

### Les emails ne partent pas
- Vérifiez `MAILER_DSN` dans `.env.local`
- Activez les mots de passe d'application Google si vous utilisez Gmail
- En dev, redirigez vers un fichier : `MAILER_DSN=null://null`

### `Column 'created_at' doesn't have a default value` (JavaFX)
Votre `PostService.java` n'inclut pas `created_at` dans l'INSERT. Assurez-vous d'utiliser la version corrigée du service (voir `public/java-integration/`).

### `Unknown column 'u.role'` (JavaFX)
Symfony stocke les rôles dans la colonne `roles` (JSON), pas `role`. Utilisez la version corrigée de `MessageService.java`.

---

## Stack technique

| Composant | Technologie |
|-----------|------------|
| Backend | PHP 8.2 / Symfony 6.4 |
| ORM | Doctrine ORM 3.x |
| Base de données | MySQL 8.0 |
| Templates | Twig |
| Frontend | AssetMapper + Stimulus (vanilla JS) |
| Auth 2FA | scheb/2fa-bundle |
| Upload | VichUploaderBundle |
| Pagination | KnpPaginatorBundle |
| PDF | DomPDF |
| WebSockets | Pusher |
| Vidéo | Daily.co |
| Emails | Symfony Mailer (Gmail / Brevo) |
| Tests | PHPUnit 11 |
| Desktop client | JavaFX (projet séparé) |

---

## Contributeurs

- **Yasmine Mansour** — Lead developer
- **Nour Alouini** — RH & fonctionnalités candidature
- **Awatef Abidi** — Entretiens & décisions
- **Sami Benali** — Forum & messagerie

---

## Licence

Projet académique — usage interne. Tous droits réservés.
