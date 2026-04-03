<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403172207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE login_attempt (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, ip_address VARCHAR(45) NOT NULL, attempted_at DATETIME NOT NULL, successful TINYINT NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, failure_reason VARCHAR(50) DEFAULT NULL, INDEX idx_login_email (email), INDEX idx_login_ip (ip_address), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_token (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used TINYINT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_452C9EC5B3BC57DA (token_hash), INDEX IDX_452C9EC5A76ED395 (user_id), INDEX idx_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reset_password_token ADD CONSTRAINT FK_452C9EC5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY `fk_avantage_offre`');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY `candidature_ibfk_1`');
        $this->addSql('ALTER TABLE candidature DROP FOREIGN KEY `candidature_ibfk_2`');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY `fk_comment_author_user`');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY `fk_comment_post`');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY `fk_decision_entretien`');
        $this->addSql('ALTER TABLE entretien DROP FOREIGN KEY `entretien_ibfk_1`');
        $this->addSql('ALTER TABLE entretien DROP FOREIGN KEY `fk_entretien_candidature`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `fk_message_receiver`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `fk_message_sender`');
        $this->addSql('ALTER TABLE piece_jointe DROP FOREIGN KEY `piece_jointe_ibfk_1`');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY `fk_post_author`');
        $this->addSql('ALTER TABLE post_votes DROP FOREIGN KEY `fk_postvote_post`');
        $this->addSql('ALTER TABLE post_votes DROP FOREIGN KEY `fk_postvote_user`');
        $this->addSql('DROP TABLE avantage');
        $this->addSql('DROP TABLE candidature');
        $this->addSql('DROP TABLE comments');
        $this->addSql('DROP TABLE decision_finale');
        $this->addSql('DROP TABLE entretien');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE offre');
        $this->addSql('DROP TABLE piece_jointe');
        $this->addSql('DROP TABLE posts');
        $this->addSql('DROP TABLE post_votes');
        $this->addSql('DROP INDEX idx_user_nom ON user');
        $this->addSql('ALTER TABLE user ADD blocked TINYINT DEFAULT 0 NOT NULL, ADD last_login_at DATETIME DEFAULT NULL, CHANGE roles roles JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX `unique` ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avantage (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, offre_id INT DEFAULT NULL, INDEX offre_id (offre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE candidature (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, offre_id INT NOT NULL, cv_url VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, motivation TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_postulation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'EN_ATTENTE\' COLLATE `utf8mb4_general_ci`, email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, langue VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'INCONNU\' COLLATE `utf8mb4_general_ci`, INDEX idx_candidature_statut (statut), INDEX user_id (user_id), INDEX offre_id (offre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE comments (id INT AUTO_INCREMENT NOT NULL, postId INT NOT NULL, author_id INT NOT NULL, authorName VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX idx_comment_post (postId), INDEX fk_comment_user (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE decision_finale (id INT AUTO_INCREMENT NOT NULL, entretien_id INT NOT NULL, decision ENUM(\'ACCEPTE\', \'REFUSE\', \'EN_ATTENTE\') CHARACTER SET utf8mb4 DEFAULT \'EN_ATTENTE\' NOT NULL COLLATE `utf8mb4_general_ci`, motif VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_decision DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, score DOUBLE PRECISION DEFAULT NULL, UNIQUE INDEX uq_decision_entretien (entretien_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE entretien (id INT AUTO_INCREMENT NOT NULL, candidature_id INT NOT NULL, date_heure DATETIME NOT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, lieu VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, lien VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, note_technique INT DEFAULT NULL, note_communication INT DEFAULT NULL, commentaire TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX fk_entretien_candidature (candidature_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, senderId INT NOT NULL, receiverId INT NOT NULL, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, sentAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, audioPath VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, duration INT DEFAULT NULL, transcript TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX idx_message_sender (senderId), INDEX idx_message_receiver (receiverId), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE offre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, localisation VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, type_contrat VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'CDI\' COLLATE `utf8mb4_general_ci`, mode_travail VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'ON_SITE\' COLLATE `utf8mb4_general_ci`, salaire_min DOUBLE PRECISION DEFAULT \'0\', salaire_max DOUBLE PRECISION DEFAULT \'0\', is_active TINYINT DEFAULT 1, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'PUBLISHED\' COLLATE `utf8mb4_general_ci`, INDEX idx_offre_statut (statut), INDEX idx_offre_active (is_active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE piece_jointe (id INT AUTO_INCREMENT NOT NULL, candidature_id INT NOT NULL, titre VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type_doc VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, url VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX candidature_id (candidature_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE posts (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, content TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, author_id INT NOT NULL, authorName VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, authorRole VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, upvotes INT DEFAULT 0, createdAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, image_path VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX idx_post_author (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE post_votes (id INT AUTO_INCREMENT NOT NULL, postId INT NOT NULL, userId INT NOT NULL, vote_type INT NOT NULL, INDEX fk_vote_post (postId), INDEX fk_vote_user (userId), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE avantage ADD CONSTRAINT `fk_avantage_offre` FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT `candidature_ibfk_1` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature ADD CONSTRAINT `candidature_ibfk_2` FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT `fk_comment_author_user` FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT `fk_comment_post` FOREIGN KEY (postId) REFERENCES posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT `fk_decision_entretien` FOREIGN KEY (entretien_id) REFERENCES entretien (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entretien ADD CONSTRAINT `entretien_ibfk_1` FOREIGN KEY (candidature_id) REFERENCES candidature (id)');
        $this->addSql('ALTER TABLE entretien ADD CONSTRAINT `fk_entretien_candidature` FOREIGN KEY (candidature_id) REFERENCES candidature (id)');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `fk_message_receiver` FOREIGN KEY (receiverId) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (senderId) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE piece_jointe ADD CONSTRAINT `piece_jointe_ibfk_1` FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE posts ADD CONSTRAINT `fk_post_author` FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_votes ADD CONSTRAINT `fk_postvote_post` FOREIGN KEY (postId) REFERENCES posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_votes ADD CONSTRAINT `fk_postvote_user` FOREIGN KEY (userId) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_password_token DROP FOREIGN KEY FK_452C9EC5A76ED395');
        $this->addSql('DROP TABLE login_attempt');
        $this->addSql('DROP TABLE reset_password_token');
        $this->addSql('ALTER TABLE `user` DROP blocked, DROP last_login_at, CHANGE roles roles JSON DEFAULT \'[]\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_user_nom ON `user` (nom)');
        $this->addSql('DROP INDEX uniq_8d93d649e7927c74 ON `user`');
        $this->addSql('CREATE UNIQUE INDEX `UNIQUE` ON `user` (email)');
    }
}
