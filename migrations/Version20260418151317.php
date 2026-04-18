<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418151317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE post_reports (id INT AUTO_INCREMENT NOT NULL, reason VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, reported_by INT NOT NULL, INDEX IDX_CCF710764B89032C (post_id), INDEX IDX_CCF71076144F5BA4 (reported_by), INDEX idx_post_reports_status (status), INDEX idx_post_reports_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE post_reports ADD CONSTRAINT FK_CCF710764B89032C FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reports ADD CONSTRAINT FK_CCF71076144F5BA4 FOREIGN KEY (reported_by) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avantage RENAME INDEX idx_avantage_offre TO IDX_A95D71E54CC8505A');
        $this->addSql('ALTER TABLE candidature_status_history CHANGE changed_at changed_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE candidature_status_history RENAME INDEX idx_candidature_status_history_candidature TO IDX_B918459CB6121583');
        $this->addSql('ALTER TABLE candidature_status_history RENAME INDEX idx_candidature_status_history_changed_by TO IDX_B918459C828AD0A0');
        $this->addSql('ALTER TABLE comments RENAME INDEX idx_comment_author TO IDX_5F9E962AF675F31B');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY `FK_C2521BF19EC8D52E`');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY `FK_C2521BF1F59432E1`');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY `FK_DECISION_ENTRETIEN`');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT FK_E4CA35E0548DCEA2 FOREIGN KEY (entretien_id) REFERENCES entretien (id)');
        $this->addSql('ALTER TABLE decision_finale RENAME INDEX uniq_decision_entretien TO UNIQ_E4CA35E0548DCEA2');
        $this->addSql('ALTER TABLE entreprise ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE login_attempt CHANGE attempted_at attempted_at DATETIME NOT NULL, CHANGE successful successful TINYINT NOT NULL');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `MSG_FK_conversation`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `MSG_FK_sender`');
        $this->addSql('ALTER TABLE offre CHANGE salaire_min salaire_min DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE salaire_max salaire_max DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE offre RENAME INDEX idx_offre_entreprise TO IDX_AF86866FA4AEAFEA');
        $this->addSql('ALTER TABLE offre RENAME INDEX idx_offre_categorie TO IDX_AF86866FBCF5E72D');
        $this->addSql('ALTER TABLE piece_jointe CHANGE type_document type_document VARCHAR(50) NOT NULL, CHANGE taille_fichier taille_fichier INT NOT NULL, CHANGE uploaded_at uploaded_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE piece_jointe RENAME INDEX idx_piece_candidature TO IDX_AB5111D4B6121583');
        $this->addSql('ALTER TABLE posts ADD hidden TINYINT DEFAULT 0 NOT NULL, CHANGE content content LONGTEXT DEFAULT NULL, CHANGE audio_path audio_path LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_token_hash ON reset_password_token (token_hash)');
        $this->addSql('ALTER TABLE reset_password_token RENAME INDEX uniq_token_hash TO UNIQ_452C9EC5B3BC57DA');
        $this->addSql('ALTER TABLE reset_password_token RENAME INDEX idx_token_user TO IDX_452C9EC5A76ED395');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX uniq_user_email TO UNIQ_8D93D649E7927C74');
        $this->addSql('ALTER TABLE user_log CHANGE logged_at logged_at DATETIME NOT NULL, CHANGE successful successful TINYINT NOT NULL');
        $this->addSql('ALTER TABLE votes DROP FOREIGN KEY `VOTE_FK_post`');
        $this->addSql('ALTER TABLE votes DROP FOREIGN KEY `VOTE_FK_user`');
        $this->addSql('ALTER TABLE messenger_messages RENAME INDEX idx_messenger TO IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE post_reports DROP FOREIGN KEY FK_CCF710764B89032C');
        $this->addSql('ALTER TABLE post_reports DROP FOREIGN KEY FK_CCF71076144F5BA4');
        $this->addSql('DROP TABLE post_reports');
        $this->addSql('ALTER TABLE avantage RENAME INDEX idx_a95d71e54cc8505a TO IDX_AVANTAGE_OFFRE');
        $this->addSql('ALTER TABLE candidature_status_history CHANGE changed_at changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE candidature_status_history RENAME INDEX idx_b918459cb6121583 TO IDX_CANDIDATURE_STATUS_HISTORY_CANDIDATURE');
        $this->addSql('ALTER TABLE candidature_status_history RENAME INDEX idx_b918459c828ad0a0 TO IDX_CANDIDATURE_STATUS_HISTORY_CHANGED_BY');
        $this->addSql('ALTER TABLE comments RENAME INDEX idx_5f9e962af675f31b TO IDX_COMMENT_AUTHOR');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY FK_E4CA35E0548DCEA2');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT `FK_DECISION_ENTRETIEN` FOREIGN KEY (entretien_id) REFERENCES entretien (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision_finale RENAME INDEX uniq_e4ca35e0548dcea2 TO UNIQ_DECISION_ENTRETIEN');
        $this->addSql('ALTER TABLE entreprise DROP updated_at');
        $this->addSql('ALTER TABLE login_attempt CHANGE attempted_at attempted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE successful successful TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE messenger_messages RENAME INDEX idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750 TO IDX_MESSENGER');
        $this->addSql('ALTER TABLE offre CHANGE salaire_min salaire_min DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE salaire_max salaire_max DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE offre RENAME INDEX idx_af86866fa4aeafea TO IDX_OFFRE_ENTREPRISE');
        $this->addSql('ALTER TABLE offre RENAME INDEX idx_af86866fbcf5e72d TO IDX_OFFRE_CATEGORIE');
        $this->addSql('ALTER TABLE piece_jointe CHANGE type_document type_document VARCHAR(100) DEFAULT \'CV\' NOT NULL, CHANGE taille_fichier taille_fichier INT DEFAULT 0 NOT NULL, CHANGE uploaded_at uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE piece_jointe RENAME INDEX idx_ab5111d4b6121583 TO IDX_PIECE_CANDIDATURE');
        $this->addSql('ALTER TABLE posts DROP hidden, CHANGE content content LONGTEXT NOT NULL, CHANGE audio_path audio_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX idx_token_hash ON reset_password_token');
        $this->addSql('ALTER TABLE reset_password_token RENAME INDEX uniq_452c9ec5b3bc57da TO UNIQ_TOKEN_HASH');
        $this->addSql('ALTER TABLE reset_password_token RENAME INDEX idx_452c9ec5a76ed395 TO IDX_TOKEN_USER');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE `user` RENAME INDEX uniq_8d93d649e7927c74 TO UNIQ_USER_EMAIL');
        $this->addSql('ALTER TABLE user_log CHANGE logged_at logged_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE successful successful TINYINT DEFAULT 1 NOT NULL');
    }
}
