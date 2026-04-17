<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reminder sent timestamps for entretien T-24h and T-1h emails';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entretien ADD reminder_24h_sent_at DATETIME DEFAULT NULL, ADD reminder_1h_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entretien DROP reminder_24h_sent_at, DROP reminder_1h_sent_at');
    }
}
