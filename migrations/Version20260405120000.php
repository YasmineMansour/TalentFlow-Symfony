<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table candidature pour la gestion des candidatures RH';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE candidature (
            id INT AUTO_INCREMENT NOT NULL,
            titre_poste VARCHAR(150) NOT NULL,
            entreprise VARCHAR(150) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            type_contrat VARCHAR(50) NOT NULL,
            statut VARCHAR(30) NOT NULL DEFAULT \'En attente\',
            date_candidature DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            date_entretien DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\',
            lieu VARCHAR(100) DEFAULT NULL,
            salaire_souhaite NUMERIC(10, 2) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CANDIDATURE_STATUT (statut),
            INDEX IDX_CANDIDATURE_TYPE (type_contrat),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE candidature');
    }
}
