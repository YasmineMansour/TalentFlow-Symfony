<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration unifiée : Création de toutes les tables du projet TalentFlow.
 * Intègre les tâches de : Yasmine (sécurité), Nour (entretien/décision),
 * Wejden (offres/entreprises/avantages/catégories), Mounib (forum posts/commentaires),
 * et Rayen (candidatures/pièces jointes).
 */
final class Version20260406180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration unifiée TalentFlow - toutes les tables du projet intégrées';
    }

    public function up(Schema $schema): void
    {
        // ─── TABLE USER (Yasmine - sécurité avancée) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS `user` (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            telephone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            blocked TINYINT(1) DEFAULT 0 NOT NULL,
            last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            auth_code VARCHAR(10) DEFAULT NULL,
            two_factor_enabled TINYINT(1) DEFAULT 1 NOT NULL,
            UNIQUE INDEX UNIQ_USER_EMAIL (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE LOGIN_ATTEMPT (Yasmine - brute force protection) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS login_attempt (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL,
            successful TINYINT(1) NOT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            failure_reason VARCHAR(50) DEFAULT NULL,
            INDEX idx_login_email (email),
            INDEX idx_login_ip (ip_address),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE RESET_PASSWORD_TOKEN (Yasmine - réinitialisation mot de passe) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS reset_password_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            type VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_TOKEN_HASH (token_hash),
            INDEX IDX_TOKEN_USER (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_RESET_TOKEN_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE ENTREPRISE (Wejden - gestion entreprises) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS entreprise (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(255) NOT NULL,
            secteur VARCHAR(255) DEFAULT NULL,
            adresse VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            telephone VARCHAR(20) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE CATEGORIE (Wejden - catégories d\'offres) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS categorie (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE OFFRE (Wejden - offres d\'emploi) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS offre (
            id INT AUTO_INCREMENT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            localisation VARCHAR(255) DEFAULT NULL,
            type_contrat VARCHAR(50) NOT NULL DEFAULT \'CDI\',
            mode_travail VARCHAR(50) NOT NULL DEFAULT \'ON_SITE\',
            salaire_min DOUBLE PRECISION NOT NULL DEFAULT 0,
            salaire_max DOUBLE PRECISION NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            statut VARCHAR(50) NOT NULL DEFAULT \'PUBLISHED\',
            entreprise_id INT DEFAULT NULL,
            categorie_id INT DEFAULT NULL,
            INDEX IDX_OFFRE_ENTREPRISE (entreprise_id),
            INDEX IDX_OFFRE_CATEGORIE (categorie_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_OFFRE_ENTREPRISE FOREIGN KEY (entreprise_id) REFERENCES entreprise (id),
            CONSTRAINT FK_OFFRE_CATEGORIE FOREIGN KEY (categorie_id) REFERENCES categorie (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE AVANTAGE (Wejden - avantages des offres) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS avantage (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            type VARCHAR(50) NOT NULL DEFAULT \'AUTRE\',
            offre_id INT NOT NULL,
            INDEX IDX_AVANTAGE_OFFRE (offre_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_AVANTAGE_OFFRE FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE CANDIDATURE (Rayen - candidatures) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS candidature (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            offre_id INT NOT NULL,
            langue VARCHAR(50) NOT NULL DEFAULT \'INCONNU\',
            statut VARCHAR(30) NOT NULL DEFAULT \'EN_ATTENTE\',
            cv_url VARCHAR(500) DEFAULT NULL,
            motivation LONGTEXT DEFAULT NULL,
            date_postulation DATETIME NOT NULL,
            email VARCHAR(180) NOT NULL,
            INDEX IDX_CANDIDATURE_USER (user_id),
            INDEX IDX_CANDIDATURE_OFFRE (offre_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE PIECE_JOINTE (Rayen - pièces jointes candidatures) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS piece_jointe (
            id INT AUTO_INCREMENT NOT NULL,
            candidature_id INT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            url VARCHAR(500) NOT NULL,
            type_doc VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_PJ_CANDIDATURE (candidature_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_PJ_CANDIDATURE FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE ENTRETIEN (Nour - gestion entretiens) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS entretien (
            id INT AUTO_INCREMENT NOT NULL,
            candidature_id INT NOT NULL,
            date_heure DATETIME NOT NULL,
            type VARCHAR(20) DEFAULT NULL,
            lieu VARCHAR(255) DEFAULT NULL,
            lien VARCHAR(255) DEFAULT NULL,
            statut VARCHAR(20) DEFAULT NULL,
            note_technique INT DEFAULT NULL,
            note_communication INT DEFAULT NULL,
            commentaire LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE DECISION_FINALE (Nour - décisions finales) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS decision_finale (
            id INT AUTO_INCREMENT NOT NULL,
            entretien_id INT NOT NULL,
            decision VARCHAR(20) NOT NULL DEFAULT \'EN_ATTENTE\',
            motif VARCHAR(255) DEFAULT NULL,
            date_decision DATETIME NOT NULL,
            created_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL,
            score DOUBLE PRECISION DEFAULT NULL,
            UNIQUE INDEX UNIQ_DECISION_ENTRETIEN (entretien_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_DECISION_ENTRETIEN FOREIGN KEY (entretien_id) REFERENCES entretien (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE POSTS (Mounib - forum) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            author_id INT NOT NULL,
            upvotes INT DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            image_path VARCHAR(500) DEFAULT NULL,
            INDEX idx_post_author (author_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_POST_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE COMMENTS (Mounib - commentaires forum) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT NOT NULL,
            post_id INT NOT NULL,
            author_id INT NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_comment_post (post_id),
            INDEX IDX_COMMENT_AUTHOR (author_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_COMMENT_POST FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
            CONSTRAINT FK_COMMENT_AUTHOR FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ─── TABLE MESSENGER_MESSAGES (Symfony Messenger) ───
        $this->addSql('CREATE TABLE IF NOT EXISTS messenger_messages (
            id BIGINT AUTO_INCREMENT NOT NULL,
            body LONGTEXT NOT NULL,
            headers LONGTEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL,
            INDEX IDX_MESSENGER (queue_name, available_at, delivered_at, id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_COMMENT_POST');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_COMMENT_AUTHOR');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY FK_POST_AUTHOR');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY FK_DECISION_ENTRETIEN');
        $this->addSql('ALTER TABLE piece_jointe DROP FOREIGN KEY FK_PJ_CANDIDATURE');
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY FK_AVANTAGE_OFFRE');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_OFFRE_ENTREPRISE');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_OFFRE_CATEGORIE');
        $this->addSql('ALTER TABLE reset_password_token DROP FOREIGN KEY FK_RESET_TOKEN_USER');

        $this->addSql('DROP TABLE IF EXISTS comments');
        $this->addSql('DROP TABLE IF EXISTS posts');
        $this->addSql('DROP TABLE IF EXISTS decision_finale');
        $this->addSql('DROP TABLE IF EXISTS entretien');
        $this->addSql('DROP TABLE IF EXISTS piece_jointe');
        $this->addSql('DROP TABLE IF EXISTS candidature');
        $this->addSql('DROP TABLE IF EXISTS avantage');
        $this->addSql('DROP TABLE IF EXISTS offre');
        $this->addSql('DROP TABLE IF EXISTS categorie');
        $this->addSql('DROP TABLE IF EXISTS entreprise');
        $this->addSql('DROP TABLE IF EXISTS reset_password_token');
        $this->addSql('DROP TABLE IF EXISTS login_attempt');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS `user`');
    }
}
