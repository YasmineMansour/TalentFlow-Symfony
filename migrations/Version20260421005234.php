<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421005234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY `FK_AVANTAGE_OFFRE`');
        $this->addSql('DROP INDEX idx_avantage_offre ON avantage');
        $this->addSql('CREATE INDEX IDX_A95D71E54CC8505A ON avantage (offre_id)');
        $this->addSql('ALTER TABLE avantage ADD CONSTRAINT `FK_AVANTAGE_OFFRE` FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature ADD confirmation_email_sent_at DATETIME DEFAULT NULL, ADD blocking_sms_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE candidature_status_history DROP FOREIGN KEY `FK_CANDIDATURE_STATUS_HISTORY_CANDIDATURE`');
        $this->addSql('ALTER TABLE candidature_status_history DROP FOREIGN KEY `FK_CANDIDATURE_STATUS_HISTORY_CHANGED_BY`');
        $this->addSql('ALTER TABLE candidature_status_history CHANGE changed_at changed_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_candidature_status_history_candidature ON candidature_status_history');
        $this->addSql('CREATE INDEX IDX_B918459CB6121583 ON candidature_status_history (candidature_id)');
        $this->addSql('DROP INDEX idx_candidature_status_history_changed_by ON candidature_status_history');
        $this->addSql('CREATE INDEX IDX_B918459C828AD0A0 ON candidature_status_history (changed_by_id)');
        $this->addSql('ALTER TABLE candidature_status_history ADD CONSTRAINT `FK_CANDIDATURE_STATUS_HISTORY_CANDIDATURE` FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature_status_history ADD CONSTRAINT `FK_CANDIDATURE_STATUS_HISTORY_CHANGED_BY` FOREIGN KEY (changed_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY `FK_COMMENT_AUTHOR`');
        $this->addSql('DROP INDEX idx_comment_author ON comments');
        $this->addSql('CREATE INDEX IDX_5F9E962AF675F31B ON comments (author_id)');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT `FK_COMMENT_AUTHOR` FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY `FK_C2521BF19EC8D52E`');
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY `FK_C2521BF1F59432E1`');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY `FK_DECISION_ENTRETIEN`');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY `FK_DECISION_ENTRETIEN`');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT FK_E4CA35E0548DCEA2 FOREIGN KEY (entretien_id) REFERENCES entretien (id)');
        $this->addSql('DROP INDEX uniq_decision_entretien ON decision_finale');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E4CA35E0548DCEA2 ON decision_finale (entretien_id)');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT `FK_DECISION_ENTRETIEN` FOREIGN KEY (entretien_id) REFERENCES entretien (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE login_attempt CHANGE attempted_at attempted_at DATETIME NOT NULL, CHANGE successful successful TINYINT NOT NULL');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `MSG_FK_conversation`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `MSG_FK_sender`');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY `FK_OFFRE_CATEGORIE`');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY `FK_OFFRE_ENTREPRISE`');
        $this->addSql('ALTER TABLE offre CHANGE salaire_min salaire_min DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE salaire_max salaire_max DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX idx_offre_entreprise ON offre');
        $this->addSql('CREATE INDEX IDX_AF86866FA4AEAFEA ON offre (entreprise_id)');
        $this->addSql('DROP INDEX idx_offre_categorie ON offre');
        $this->addSql('CREATE INDEX IDX_AF86866FBCF5E72D ON offre (categorie_id)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT `FK_OFFRE_CATEGORIE` FOREIGN KEY (categorie_id) REFERENCES categorie (id)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT `FK_OFFRE_ENTREPRISE` FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE piece_jointe DROP FOREIGN KEY `FK_PIECE_CANDIDATURE`');
        $this->addSql('ALTER TABLE piece_jointe CHANGE type_document type_document VARCHAR(50) NOT NULL, CHANGE taille_fichier taille_fichier INT NOT NULL, CHANGE uploaded_at uploaded_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_piece_candidature ON piece_jointe');
        $this->addSql('CREATE INDEX IDX_AB5111D4B6121583 ON piece_jointe (candidature_id)');
        $this->addSql('ALTER TABLE piece_jointe ADD CONSTRAINT `FK_PIECE_CANDIDATURE` FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reports DROP FOREIGN KEY `FK_6E5683F84B89032C`');
        $this->addSql('ALTER TABLE post_reports DROP FOREIGN KEY `FK_6E5683F890D4C597`');
        $this->addSql('ALTER TABLE post_reports CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_6e5683f84b89032c ON post_reports');
        $this->addSql('CREATE INDEX IDX_CCF710764B89032C ON post_reports (post_id)');
        $this->addSql('DROP INDEX idx_6e5683f890d4c597 ON post_reports');
        $this->addSql('CREATE INDEX IDX_CCF71076144F5BA4 ON post_reports (reported_by)');
        $this->addSql('ALTER TABLE post_reports ADD CONSTRAINT `FK_6E5683F84B89032C` FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reports ADD CONSTRAINT `FK_6E5683F890D4C597` FOREIGN KEY (reported_by) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE posts CHANGE content content LONGTEXT DEFAULT NULL, CHANGE audio_path audio_path LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE reset_password_token DROP FOREIGN KEY `FK_RESET_TOKEN_USER`');
        $this->addSql('CREATE INDEX idx_token_hash ON reset_password_token (token_hash)');
        $this->addSql('DROP INDEX uniq_token_hash ON reset_password_token');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_452C9EC5B3BC57DA ON reset_password_token (token_hash)');
        $this->addSql('DROP INDEX idx_token_user ON reset_password_token');
        $this->addSql('CREATE INDEX IDX_452C9EC5A76ED395 ON reset_password_token (user_id)');
        $this->addSql('ALTER TABLE reset_password_token ADD CONSTRAINT `FK_RESET_TOKEN_USER` FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_user_email ON user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('ALTER TABLE user_log CHANGE logged_at logged_at DATETIME NOT NULL, CHANGE successful successful TINYINT NOT NULL');
        $this->addSql('ALTER TABLE votes DROP FOREIGN KEY `VOTE_FK_post`');
        $this->addSql('ALTER TABLE votes DROP FOREIGN KEY `VOTE_FK_user`');
        $this->addSql('DROP INDEX idx_messenger ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avantage DROP FOREIGN KEY FK_A95D71E54CC8505A');
        $this->addSql('DROP INDEX idx_a95d71e54cc8505a ON avantage');
        $this->addSql('CREATE INDEX IDX_AVANTAGE_OFFRE ON avantage (offre_id)');
        $this->addSql('ALTER TABLE avantage ADD CONSTRAINT FK_A95D71E54CC8505A FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature DROP confirmation_email_sent_at, DROP blocking_sms_sent_at');
        $this->addSql('ALTER TABLE candidature_status_history DROP FOREIGN KEY FK_B918459CB6121583');
        $this->addSql('ALTER TABLE candidature_status_history DROP FOREIGN KEY FK_B918459C828AD0A0');
        $this->addSql('ALTER TABLE candidature_status_history CHANGE changed_at changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_b918459cb6121583 ON candidature_status_history');
        $this->addSql('CREATE INDEX IDX_CANDIDATURE_STATUS_HISTORY_CANDIDATURE ON candidature_status_history (candidature_id)');
        $this->addSql('DROP INDEX idx_b918459c828ad0a0 ON candidature_status_history');
        $this->addSql('CREATE INDEX IDX_CANDIDATURE_STATUS_HISTORY_CHANGED_BY ON candidature_status_history (changed_by_id)');
        $this->addSql('ALTER TABLE candidature_status_history ADD CONSTRAINT FK_B918459CB6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature_status_history ADD CONSTRAINT FK_B918459C828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AF675F31B');
        $this->addSql('DROP INDEX idx_5f9e962af675f31b ON comments');
        $this->addSql('CREATE INDEX IDX_COMMENT_AUTHOR ON comments (author_id)');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY FK_E4CA35E0548DCEA2');
        $this->addSql('ALTER TABLE decision_finale DROP FOREIGN KEY FK_E4CA35E0548DCEA2');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT `FK_DECISION_ENTRETIEN` FOREIGN KEY (entretien_id) REFERENCES entretien (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_e4ca35e0548dcea2 ON decision_finale');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DECISION_ENTRETIEN ON decision_finale (entretien_id)');
        $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT FK_E4CA35E0548DCEA2 FOREIGN KEY (entretien_id) REFERENCES entretien (id)');
        $this->addSql('ALTER TABLE login_attempt CHANGE attempted_at attempted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE successful successful TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('DROP INDEX idx_75ea56e0fb7336f0e3bd61ce16ba31dbbf396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_MESSENGER ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_AF86866FA4AEAFEA');
        $this->addSql('ALTER TABLE offre DROP FOREIGN KEY FK_AF86866FBCF5E72D');
        $this->addSql('ALTER TABLE offre CHANGE salaire_min salaire_min DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE salaire_max salaire_max DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('DROP INDEX idx_af86866fbcf5e72d ON offre');
        $this->addSql('CREATE INDEX IDX_OFFRE_CATEGORIE ON offre (categorie_id)');
        $this->addSql('DROP INDEX idx_af86866fa4aeafea ON offre');
        $this->addSql('CREATE INDEX IDX_OFFRE_ENTREPRISE ON offre (entreprise_id)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT FK_AF86866FA4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE offre ADD CONSTRAINT FK_AF86866FBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id)');
        $this->addSql('ALTER TABLE piece_jointe DROP FOREIGN KEY FK_AB5111D4B6121583');
        $this->addSql('ALTER TABLE piece_jointe CHANGE type_document type_document VARCHAR(100) DEFAULT \'CV\' NOT NULL, CHANGE taille_fichier taille_fichier INT DEFAULT 0 NOT NULL, CHANGE uploaded_at uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_ab5111d4b6121583 ON piece_jointe');
        $this->addSql('CREATE INDEX IDX_PIECE_CANDIDATURE ON piece_jointe (candidature_id)');
        $this->addSql('ALTER TABLE piece_jointe ADD CONSTRAINT FK_AB5111D4B6121583 FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE posts CHANGE content content LONGTEXT NOT NULL, CHANGE audio_path audio_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE post_reports DROP FOREIGN KEY FK_CCF710764B89032C');
        $this->addSql('ALTER TABLE post_reports DROP FOREIGN KEY FK_CCF71076144F5BA4');
        $this->addSql('ALTER TABLE post_reports CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX idx_ccf710764b89032c ON post_reports');
        $this->addSql('CREATE INDEX IDX_6E5683F84B89032C ON post_reports (post_id)');
        $this->addSql('DROP INDEX idx_ccf71076144f5ba4 ON post_reports');
        $this->addSql('CREATE INDEX IDX_6E5683F890D4C597 ON post_reports (reported_by)');
        $this->addSql('ALTER TABLE post_reports ADD CONSTRAINT FK_CCF710764B89032C FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reports ADD CONSTRAINT FK_CCF71076144F5BA4 FOREIGN KEY (reported_by) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_token_hash ON reset_password_token');
        $this->addSql('ALTER TABLE reset_password_token DROP FOREIGN KEY FK_452C9EC5A76ED395');
        $this->addSql('DROP INDEX uniq_452c9ec5b3bc57da ON reset_password_token');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TOKEN_HASH ON reset_password_token (token_hash)');
        $this->addSql('DROP INDEX idx_452c9ec5a76ed395 ON reset_password_token');
        $this->addSql('CREATE INDEX IDX_TOKEN_USER ON reset_password_token (user_id)');
        $this->addSql('ALTER TABLE reset_password_token ADD CONSTRAINT FK_452C9EC5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_8d93d649e7927c74 ON `user`');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON `user` (email)');
        $this->addSql('ALTER TABLE user_log CHANGE logged_at logged_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE successful successful TINYINT DEFAULT 1 NOT NULL');
    }
}
