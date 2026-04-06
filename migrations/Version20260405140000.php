<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table piece_jointe et suppression de la table product';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS product');

        $this->addSql('CREATE TABLE piece_jointe (
            id INT AUTO_INCREMENT NOT NULL,
            candidature_id INT NOT NULL,
            nom_fichier VARCHAR(255) NOT NULL,
            type_document VARCHAR(50) NOT NULL,
            chemin_fichier VARCHAR(500) NOT NULL,
            taille_fichier INT NOT NULL,
            uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_PIECE_JOINTE_CANDIDATURE (candidature_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_PJ_CANDIDATURE FOREIGN KEY (candidature_id) REFERENCES candidature (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE piece_jointe');
    }
}
