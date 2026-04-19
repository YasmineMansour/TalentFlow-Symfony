<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add post hidden flag and post reports moderation table';
    }

    public function up(Schema $schema): void
    {
        // Idempotent migration: environments may already have posts.hidden.
        $this->addSql('ALTER TABLE posts ADD COLUMN IF NOT EXISTS hidden TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("CREATE TABLE IF NOT EXISTS post_reports (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, reported_by INT NOT NULL, reason VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_post_reports_status (status), INDEX idx_post_reports_created_at (created_at), INDEX IDX_6E5683F84B89032C (post_id), INDEX IDX_6E5683F890D4C597 (reported_by), PRIMARY KEY(id), CONSTRAINT FK_6E5683F84B89032C FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE, CONSTRAINT FK_6E5683F890D4C597 FOREIGN KEY (reported_by) REFERENCES `user` (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS post_reports');
        $this->addSql('ALTER TABLE posts DROP COLUMN IF EXISTS hidden');
    }
}
