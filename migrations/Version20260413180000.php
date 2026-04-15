<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration : Métiers de gestion utilisateurs avancée.
 * - Ajout des colonnes titre_poste et bio dans la table user
 * - Création de la table user_log (historique de connexion)
 */
final class Version20260413180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Métiers User : titre_poste, bio sur User + table user_log (historique connexion)';
    }

    public function up(Schema $schema): void
    {
        // Ajout des champs profil sur User
        $this->addSql("ALTER TABLE `user`
            ADD COLUMN IF NOT EXISTS titre_poste VARCHAR(100) DEFAULT NULL AFTER telephone,
            ADD COLUMN IF NOT EXISTS bio LONGTEXT DEFAULT NULL AFTER titre_poste
        ");

        // Historique de connexion (UserLog)
        $this->addSql("CREATE TABLE IF NOT EXISTS user_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL,
            browser VARCHAR(100) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            logged_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            successful TINYINT(1) NOT NULL DEFAULT 1,
            email VARCHAR(180) DEFAULT NULL,
            INDEX idx_userlog_user (user_id),
            INDEX idx_userlog_logged_at (logged_at),
            PRIMARY KEY(id),
            CONSTRAINT fk_userlog_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_log');
        $this->addSql('ALTER TABLE `user` DROP COLUMN IF EXISTS titre_poste, DROP COLUMN IF EXISTS bio');
    }
}
