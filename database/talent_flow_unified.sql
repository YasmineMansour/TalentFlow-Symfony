-- =============================================================================
-- TalentFlow - Base de données unifiée
-- Importation de toutes les données des 5 tâches
-- Date: 06/04/2026
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- =============================================================================
-- SUPPRESSION (ordre inverse des dépendances)
-- =============================================================================
DROP TABLE IF EXISTS `comments`;
DROP TABLE IF EXISTS `posts`;
DROP TABLE IF EXISTS `decision_finale`;
DROP TABLE IF EXISTS `entretien`;
DROP TABLE IF EXISTS `piece_jointe`;
DROP TABLE IF EXISTS `candidature`;
DROP TABLE IF EXISTS `avantage`;
DROP TABLE IF EXISTS `offre`;
DROP TABLE IF EXISTS `categorie`;
DROP TABLE IF EXISTS `entreprise`;
DROP TABLE IF EXISTS `reset_password_token`;
DROP TABLE IF EXISTS `login_attempt`;
DROP TABLE IF EXISTS `messenger_messages`;
DROP TABLE IF EXISTS `doctrine_migration_versions`;
DROP TABLE IF EXISTS `user`;

-- =============================================================================
-- TABLE: user (Yasmine - sécurité avancée)
-- =============================================================================
CREATE TABLE `user` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `email` VARCHAR(180) NOT NULL,
    `roles` JSON NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `telephone` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `blocked` TINYINT(1) DEFAULT 0 NOT NULL,
    `last_login_at` DATETIME DEFAULT NULL,
    `auth_code` VARCHAR(10) DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 1 NOT NULL,
    UNIQUE INDEX UNIQ_USER_EMAIL (`email`),
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user` (`id`, `nom`, `prenom`, `email`, `roles`, `password`, `telephone`, `created_at`, `updated_at`, `blocked`, `last_login_at`, `auth_code`, `two_factor_enabled`) VALUES
(1, 'BEN SALAH', 'Ahmed', 'ahmed.updated@test.tn', '["ROLE_ADMIN"]', '$2y$13$wsWdm5HcSd5.jEkLxN7wdeqaBucjjkwiZRnLt6iCCgjFQywlrzA36', '12345678', '2026-04-05 17:56:30', NULL, 0, NULL, NULL, 1),
(2, 'ADMIN', 'Super', 'admin@talentflow.com', '["ROLE_ADMIN"]', '$2y$13$wsWdm5HcSd5.jEkLxN7wdeqaBucjjkwiZRnLt6iCCgjFQywlrzA36', '+21600000000', '2026-04-05 16:44:47', NULL, 0, NULL, NULL, 0),
(9, 'ABIDI', 'Awatef', 'awatefe@aaaa.com', '["ROLE_RH"]', '$2y$13$wsWdm5HcSd5.jEkLxN7wdeqaBucjjkwiZRnLt6iCCgjFQywlrzA36', '12345678', '2026-04-05 17:56:30', NULL, 0, NULL, NULL, 1),
(39, 'MANSOUR', 'Yasmine', 'yasmine.mansour@esprit.tn', '["ROLE_ADMIN"]', '$2y$13$yc/KzyIPTPyUJbshRdgLhOWClVivVf39ZTEEM/2OMVjin.TJJlhNS', '94466585', '2026-04-05 17:56:30', NULL, 0, NULL, NULL, 1),
(42, 'SAIDANE', 'Wejden', 'wejden.saidane@esprit.tn', '["ROLE_CANDIDAT"]', '$2y$13$wsWdm5HcSd5.jEkLxN7wdeqaBucjjkwiZRnLt6iCCgjFQywlrzA36', '21696898', '2026-04-05 17:56:30', NULL, 0, NULL, NULL, 1),
(43, 'MANSOUR', 'Yasmine', 'yasminemansour912@gmail.com', '["ROLE_CANDIDAT"]', '$2y$13$wsWdm5HcSd5.jEkLxN7wdeqaBucjjkwiZRnLt6iCCgjFQywlrzA36', '94466585', '2026-04-05 17:56:30', NULL, 0, NULL, NULL, 1),
(45, 'ALOUINI', 'Nour', 'nouralouini004@gmail.com', '["ROLE_RH"]', '$2y$13$X0I20yFVCliyBO8Z.sHDW.PvIpiEXg0Ifm0RAHQgSlkl9dsSkrDpm', '51274821', '2026-04-05 17:56:30', NULL, 0, NULL, NULL, 1);

-- =============================================================================
-- TABLE: login_attempt (Yasmine - brute force protection)
-- =============================================================================
CREATE TABLE `login_attempt` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `email` VARCHAR(180) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL,
    `successful` TINYINT(1) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `failure_reason` VARCHAR(50) DEFAULT NULL,
    INDEX idx_login_email (`email`),
    INDEX idx_login_ip (`ip_address`),
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE: reset_password_token (Yasmine - réinitialisation mot de passe)
-- =============================================================================
CREATE TABLE `reset_password_token` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `user_id` INT NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `type` VARCHAR(20) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL,
    UNIQUE INDEX UNIQ_TOKEN_HASH (`token_hash`),
    INDEX IDX_TOKEN_USER (`user_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_RESET_TOKEN_USER FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE: entreprise (Wejden - gestion entreprises)
-- =============================================================================
CREATE TABLE `entreprise` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `nom` VARCHAR(255) NOT NULL,
    `secteur` VARCHAR(255) DEFAULT NULL,
    `adresse` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `telephone` VARCHAR(20) DEFAULT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `entreprise` (`id`, `nom`, `secteur`, `adresse`, `email`, `telephone`, `description`, `logo`) VALUES
(1, 'TechCorp', 'Informatique', 'Rue des Palmiers, Tunis', 'contact@techcorp.tn', '+21621696898', 'Entreprise spécialisée dans le développement logiciel', NULL),
(2, 'DataSoft', 'IT', 'Centre Urbain Nord, Tunis', 'info@datasoft.tn', NULL, NULL, NULL);

-- =============================================================================
-- TABLE: categorie (Wejden - catégories d'offres)
-- =============================================================================
CREATE TABLE `categorie` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `nom` VARCHAR(255) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categorie` (`id`, `nom`, `description`) VALUES
(1, 'Développement', 'Postes de développement logiciel et web');

-- =============================================================================
-- TABLE: offre (Wejden - offres d'emploi)
-- =============================================================================
CREATE TABLE `offre` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `localisation` VARCHAR(255) DEFAULT NULL,
    `type_contrat` VARCHAR(50) NOT NULL DEFAULT 'CDI',
    `mode_travail` VARCHAR(50) NOT NULL DEFAULT 'ON_SITE',
    `salaire_min` DOUBLE PRECISION NOT NULL DEFAULT 0,
    `salaire_max` DOUBLE PRECISION NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `statut` VARCHAR(50) NOT NULL DEFAULT 'PUBLISHED',
    `entreprise_id` INT DEFAULT NULL,
    `categorie_id` INT DEFAULT NULL,
    INDEX IDX_OFFRE_ENTREPRISE (`entreprise_id`),
    INDEX IDX_OFFRE_CATEGORIE (`categorie_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_OFFRE_ENTREPRISE FOREIGN KEY (`entreprise_id`) REFERENCES `entreprise` (`id`),
    CONSTRAINT FK_OFFRE_CATEGORIE FOREIGN KEY (`categorie_id`) REFERENCES `categorie` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `offre` (`id`, `titre`, `description`, `localisation`, `type_contrat`, `mode_travail`, `salaire_min`, `salaire_max`, `is_active`, `statut`, `entreprise_id`, `categorie_id`) VALUES
(1, 'Développeur Full Stack', 'Nous recherchons un développeur Full Stack expérimenté', 'Tunis', 'CDI', 'ON_SITE', 1500, 2500, 1, 'PUBLISHED', 1, 1),
(2, 'Développeur Frontend React', 'Poste de développeur frontend React/TypeScript', 'Tunis', 'CDI', 'ON_SITE', 1000, 1500, 1, 'PUBLISHED', 1, 1),
(4, 'Développeur Java', 'Nous recherchons un jeune développeur dynamique', 'Kairouan', 'CDI', 'ON_SITE', 800, 2000, 1, 'PUBLISHED', NULL, NULL),
(6, 'Développeur Senior', 'Poste senior avec télétravail possible', 'Monastir', 'Stage', 'REMOTE', 1200, 2400, 1, 'PUBLISHED', NULL, NULL),
(7, 'Data Analyst', 'Analyste de données pour notre équipe BI', 'Sousse', 'Alternance', 'REMOTE', 700, 900, 1, 'PUBLISHED', NULL, NULL);

-- =============================================================================
-- TABLE: avantage (Wejden - avantages des offres)
-- =============================================================================
CREATE TABLE `avantage` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `nom` VARCHAR(255) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'AUTRE',
    `offre_id` INT NOT NULL,
    INDEX IDX_AVANTAGE_OFFRE (`offre_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_AVANTAGE_OFFRE FOREIGN KEY (`offre_id`) REFERENCES `offre` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `avantage` (`id`, `nom`, `description`, `type`, `offre_id`) VALUES
(1, 'Assurance santé', 'Couverture santé complète pour l employé et sa famille', 'FINANCIER', 2),
(17, 'Télétravail', '3 jours/semaine en télétravail', 'BIEN_ETRE', 7),
(18, 'Voiture de fonction', 'Disponible du lundi au vendredi', 'MATERIEL', 7),
(19, 'Indemnité télétravail', 'Allocation mensuelle pour couvrir les frais liés au travail à domicile', 'FINANCIER', 7);

-- =============================================================================
-- TABLE: candidature (Rayen - candidatures)
-- =============================================================================
CREATE TABLE `candidature` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `titre_poste` VARCHAR(150) NOT NULL,
    `entreprise` VARCHAR(150) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `type_contrat` VARCHAR(50) NOT NULL,
    `statut` VARCHAR(30) NOT NULL DEFAULT 'En attente',
    `date_candidature` DATE NOT NULL,
    `date_entretien` DATE DEFAULT NULL,
    `lieu` VARCHAR(100) DEFAULT NULL,
    `salaire_souhaite` DECIMAL(10,2) DEFAULT NULL,
    `notes` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `candidature` (`id`, `titre_poste`, `entreprise`, `description`, `type_contrat`, `statut`, `date_candidature`, `date_entretien`, `lieu`, `salaire_souhaite`, `notes`, `created_at`) VALUES
(1, 'Développeur Full Stack', 'TechCorp', 'Poste de développeur Full Stack PHP/Symfony', 'CDI', 'Entretien', '2026-03-03', '2026-03-10', 'Tunis', 2000.00, 'Candidature envoyée via le site', '2026-03-03 00:29:05'),
(2, 'Développeur Frontend React', 'DataSoft', 'Poste frontend React/TypeScript', 'CDD', 'En attente', '2026-03-03', NULL, 'Tunis', 1500.00, NULL, '2026-03-03 01:07:27'),
(3, 'Data Analyst', 'InfoGroup', 'Analyste de données junior', 'Stage', 'Acceptée', '2026-03-03', '2026-03-08', 'Sousse', 900.00, 'Très bonne expérience', '2026-03-03 12:55:20'),
(4, 'Développeur Java', 'JavaTech', 'Développeur Java/Spring Boot', 'CDI', 'En attente', '2026-03-03', NULL, 'Kairouan', 1800.00, NULL, '2026-03-03 12:57:36'),
(5, 'Designer UX/UI', 'CreativeStudio', 'Poste de designer UX/UI', 'Freelance', 'Refusée', '2026-03-03', '2026-03-05', 'Monastir', 1200.00, 'Profil non retenu', '2026-03-03 13:01:06'),
(6, 'Développeur Mobile', 'AppFactory', 'Développeur Flutter/Dart', 'Alternance', 'En attente', '2026-04-05', NULL, 'Sfax', 1000.00, NULL, '2026-04-05 17:08:00');

-- =============================================================================
-- TABLE: piece_jointe (Rayen - pièces jointes)
-- =============================================================================
CREATE TABLE `piece_jointe` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `candidature_id` INT NOT NULL,
    `nom_fichier` VARCHAR(255) NOT NULL,
    `type_document` VARCHAR(50) NOT NULL,
    `chemin_fichier` VARCHAR(500) NOT NULL,
    `taille_fichier` INT NOT NULL,
    `uploaded_at` DATETIME NOT NULL,
    INDEX IDX_PJ_CANDIDATURE (`candidature_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_PJ_CANDIDATURE FOREIGN KEY (`candidature_id`) REFERENCES `candidature` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `piece_jointe` (`id`, `candidature_id`, `nom_fichier`, `type_document`, `chemin_fichier`, `taille_fichier`, `uploaded_at`) VALUES
(1, 1, 'cv_fullstack.pdf', 'CV', 'uploads/pieces_jointes/cv_fullstack.pdf', 245000, '2026-03-03 10:00:00'),
(2, 1, 'lettre_motivation.pdf', 'Lettre de motivation', 'uploads/pieces_jointes/lm_fullstack.pdf', 180000, '2026-03-03 10:05:00'),
(3, 3, 'diplome_ingenieur.pdf', 'Diplôme', 'uploads/pieces_jointes/diplome.pdf', 520000, '2026-03-03 13:00:00');

-- =============================================================================
-- TABLE: entretien (Nour - gestion entretiens)
-- =============================================================================
CREATE TABLE `entretien` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `candidature_id` INT NOT NULL,
    `date_heure` DATETIME NOT NULL,
    `type` VARCHAR(20) DEFAULT NULL,
    `lieu` VARCHAR(255) DEFAULT NULL,
    `lien` VARCHAR(255) DEFAULT NULL,
    `statut` VARCHAR(20) DEFAULT NULL,
    `note_technique` INT DEFAULT NULL,
    `note_communication` INT DEFAULT NULL,
    `commentaire` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `entretien` (`id`, `candidature_id`, `date_heure`, `type`, `lieu`, `lien`, `statut`, `note_technique`, `note_communication`, `commentaire`, `created_at`, `updated_at`) VALUES
(20, 3, '2026-03-05 10:30:00', 'PRESENTIEL', 'Bureau 2', NULL, 'REALISE', 17, 10, NULL, '2026-03-03 12:55:43', '2026-04-05 17:04:02'),
(21, 4, '2026-03-02 17:00:00', 'PRESENTIEL', 'Salle 10', '', 'REALISE', 15, 17, 'Très bon niveau technique', '2026-03-03 12:58:30', '2026-03-03 12:58:30'),
(23, 5, '2026-03-03 13:30:00', 'EN_LIGNE', NULL, 'https://googlemeet.com/abc', 'REALISE', 20, 20, NULL, '2026-03-03 13:03:29', '2026-04-05 17:06:17'),
(26, 4, '2026-04-07 13:10:00', 'PRESENTIEL', 'Bureau 10', NULL, 'PLANIFIE', NULL, NULL, NULL, '2026-04-05 17:04:58', '2026-04-05 17:04:58'),
(27, 6, '2026-03-11 15:05:00', 'PRESENTIEL', 'Salle 101', NULL, 'REALISE', 11, 19, 'Pas mal', '2026-04-05 17:09:13', '2026-04-05 17:09:13');

-- =============================================================================
-- TABLE: decision_finale (Nour - décisions finales)
-- =============================================================================
CREATE TABLE `decision_finale` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `entretien_id` INT NOT NULL,
    `decision` VARCHAR(20) NOT NULL DEFAULT 'EN_ATTENTE',
    `motif` VARCHAR(255) DEFAULT NULL,
    `date_decision` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `score` DOUBLE PRECISION DEFAULT NULL,
    UNIQUE INDEX UNIQ_DECISION_ENTRETIEN (`entretien_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_DECISION_ENTRETIEN FOREIGN KEY (`entretien_id`) REFERENCES `entretien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `decision_finale` (`id`, `entretien_id`, `decision`, `motif`, `date_decision`, `created_at`, `updated_at`, `score`) VALUES
(44, 21, 'ACCEPTE', 'Décision automatique : score 15.6 >= seuil 14.0', '2026-03-03 11:59:02', '2026-03-03 12:59:02', '2026-03-03 13:06:53', 15.6),
(46, 20, 'ACCEPTE', 'Décision automatique : score 20.00 >= seuil 14.00', '2026-03-31 10:57:34', '2026-03-31 11:57:34', '2026-04-05 17:07:56', 20),
(48, 27, 'ACCEPTE', 'Décision validée manuellement par le responsable RH.', '2026-04-05 17:09:26', '2026-04-05 17:09:26', '2026-04-05 17:46:18', 13.4);

-- =============================================================================
-- TABLE: posts (Mounib - forum)
-- =============================================================================
CREATE TABLE `posts` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `author_id` INT NOT NULL,
    `upvotes` INT DEFAULT 0 NOT NULL,
    `created_at` DATETIME NOT NULL,
    `image_path` VARCHAR(500) DEFAULT NULL,
    INDEX idx_post_author (`author_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_POST_AUTHOR FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `posts` (`id`, `title`, `content`, `author_id`, `upvotes`, `created_at`, `image_path`) VALUES
(1, 'Bienvenue sur le forum TalentFlow', 'Ce forum est dédié à la communication entre candidats et recruteurs. Partagez vos expériences et posez vos questions !', 1, 1, '2026-02-20 15:03:49', NULL),
(2, 'Recherche stage JavaFX', 'Je suis étudiant et je recherche un stage en JavaFX. J ai de l expérience avec MySQL et Java.', 42, 0, '2026-02-20 15:03:49', NULL),
(3, 'Nous recrutons des développeurs Java', 'Notre entreprise recrute des développeurs Java juniors. Contactez-nous pour plus d informations.', 9, 0, '2026-02-20 15:03:49', NULL);

-- =============================================================================
-- TABLE: comments (Mounib - commentaires forum)
-- =============================================================================
CREATE TABLE `comments` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `post_id` INT NOT NULL,
    `author_id` INT NOT NULL,
    `content` LONGTEXT NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX idx_comment_post (`post_id`),
    INDEX IDX_COMMENT_AUTHOR (`author_id`),
    PRIMARY KEY(`id`),
    CONSTRAINT FK_COMMENT_POST FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT FK_COMMENT_AUTHOR FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `comments` (`id`, `post_id`, `author_id`, `content`, `created_at`) VALUES
(1, 1, 9, 'Merci pour la création de ce forum !', '2026-02-20 15:03:58'),
(2, 2, 1, 'Envoyez votre CV à notre adresse email.', '2026-02-20 15:03:58'),
(3, 3, 42, 'C est une excellente opportunité pour les candidats.', '2026-02-20 15:03:58');

-- =============================================================================
-- TABLE: messenger_messages (Symfony Messenger)
-- =============================================================================
CREATE TABLE `messenger_messages` (
    `id` BIGINT AUTO_INCREMENT NOT NULL,
    `body` LONGTEXT NOT NULL,
    `headers` LONGTEXT NOT NULL,
    `queue_name` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `available_at` DATETIME NOT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    INDEX IDX_MESSENGER (`queue_name`, `available_at`, `delivered_at`, `id`),
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE: doctrine_migration_versions
-- =============================================================================
CREATE TABLE `doctrine_migration_versions` (
    `version` VARCHAR(191) NOT NULL,
    `executed_at` DATETIME DEFAULT NULL,
    `execution_time` INT DEFAULT NULL,
    PRIMARY KEY(`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260406180000', NOW(), 100);

COMMIT;
