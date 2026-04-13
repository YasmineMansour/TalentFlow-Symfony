<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add candidature matching_score and candidature_status_history snapshots table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidature ADD COLUMN IF NOT EXISTS matching_score SMALLINT DEFAULT NULL');

        $this->addSql('CREATE TABLE candidature_status_history (
            id INT AUTO_INCREMENT NOT NULL,
            candidature_id INT NOT NULL,
            changed_by_id INT DEFAULT NULL,
            from_status VARCHAR(30) DEFAULT NULL,
            to_status VARCHAR(30) NOT NULL,
            transition_name VARCHAR(100) DEFAULT NULL,
            note LONGTEXT DEFAULT NULL,
            changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CANDIDATURE_STATUS_HISTORY_CANDIDATURE (candidature_id),
            INDEX IDX_CANDIDATURE_STATUS_HISTORY_CHANGED_BY (changed_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE candidature_status_history
            ADD CONSTRAINT FK_CANDIDATURE_STATUS_HISTORY_CANDIDATURE FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE candidature_status_history
            ADD CONSTRAINT FK_CANDIDATURE_STATUS_HISTORY_CHANGED_BY FOREIGN KEY (changed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidature DROP matching_score');
        $this->addSql('DROP TABLE candidature_status_history');
    }
}
