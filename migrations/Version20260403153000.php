<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration de bootstrap et de normalisation du schéma TalentFlow.
 */
final class Version20260403153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée ou normalise les tables user, entretien et decision_finale pour le module TalentFlow Symfony';
    }

    public function up(Schema $schema): void
    {
        $this->syncUserTable($schema);
        $this->syncEntretienTable($schema);
        $this->syncDecisionFinaleTable($schema);
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('decision_finale')) {
            $this->addSql('DROP TABLE decision_finale');
        }

        if ($schema->hasTable('entretien')) {
            $this->addSql('DROP TABLE entretien');
        }

        if ($schema->hasTable('user')) {
            $this->addSql('DROP TABLE `user`');
        }
    }

    private function syncUserTable(Schema $schema): void
    {
        if (!$schema->hasTable('user')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE `user` (
                    id INT AUTO_INCREMENT NOT NULL,
                    nom VARCHAR(100) NOT NULL,
                    prenom VARCHAR(100) NOT NULL,
                    email VARCHAR(180) NOT NULL,
                    roles JSON NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    telephone VARCHAR(20) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME DEFAULT NULL,
                    UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

            return;
        }

        $table = $schema->getTable('user');

        if ($table->hasColumn('roles') && $table->hasColumn('role')) {
            $this->addSql(<<<'SQL'
                UPDATE `user`
                SET roles = CASE role
                    WHEN 'ADMIN' THEN JSON_ARRAY('ROLE_ADMIN')
                    WHEN 'RH' THEN JSON_ARRAY('ROLE_RH')
                    ELSE JSON_ARRAY('ROLE_CANDIDAT')
                END
                WHERE roles IS NULL OR roles = ''
            SQL);
        }

        $alterParts = [];

        if ($table->hasColumn('nom')) {
            $alterParts[] = 'CHANGE nom nom VARCHAR(100) NOT NULL';
        }

        if ($table->hasColumn('prenom')) {
            $alterParts[] = 'CHANGE prenom prenom VARCHAR(100) NOT NULL';
        }

        if ($table->hasColumn('email')) {
            $alterParts[] = 'CHANGE email email VARCHAR(180) NOT NULL';
        }

        if ($table->hasColumn('telephone')) {
            $alterParts[] = 'CHANGE telephone telephone VARCHAR(20) DEFAULT NULL';
        }

        if ($table->hasColumn('roles')) {
            $alterParts[] = 'CHANGE roles roles JSON NOT NULL';
        }

        if ($table->hasColumn('created_at')) {
            $alterParts[] = 'CHANGE created_at created_at DATETIME NOT NULL';
        }

        if ($table->hasColumn('updated_at')) {
            $alterParts[] = 'CHANGE updated_at updated_at DATETIME DEFAULT NULL';
        }

        foreach (['role', 'blocked', 'last_login_at', 'auth_code', 'two_factor_enabled'] as $column) {
            if ($table->hasColumn($column)) {
                $alterParts[] = sprintf('DROP COLUMN %s', $column);
            }
        }

        if ($alterParts !== []) {
            $this->addSql("ALTER TABLE `user`\n    " . implode(",\n    ", $alterParts));
        }

        if ($table->hasIndex('UNIQUE')) {
            $this->addSql('DROP INDEX `UNIQUE` ON `user`');
        }

        if (!$table->hasIndex('UNIQ_8D93D649E7927C74')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON `user` (email)');
        }
    }

    private function syncEntretienTable(Schema $schema): void
    {
        if (!$schema->hasTable('entretien')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE entretien (
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
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

            return;
        }

        $table = $schema->getTable('entretien');
        $alterParts = [];

        if ($table->hasColumn('commentaire')) {
            $alterParts[] = 'CHANGE commentaire commentaire LONGTEXT DEFAULT NULL';
        }

        if ($table->hasColumn('created_at')) {
            $alterParts[] = 'CHANGE created_at created_at DATETIME DEFAULT NULL';
        }

        if ($table->hasColumn('updated_at')) {
            $alterParts[] = 'CHANGE updated_at updated_at DATETIME DEFAULT NULL';
        }

        if ($alterParts !== []) {
            $this->addSql("ALTER TABLE entretien\n    " . implode(",\n    ", $alterParts));
        }

        if ($table->hasIndex('fk_entretien_candidature')) {
            $this->addSql('DROP INDEX fk_entretien_candidature ON entretien');
        }
    }

    private function syncDecisionFinaleTable(Schema $schema): void
    {
        if (!$schema->hasTable('decision_finale')) {
            $this->addSql(<<<'SQL'
                CREATE TABLE decision_finale (
                    id INT AUTO_INCREMENT NOT NULL,
                    decision VARCHAR(20) NOT NULL,
                    motif VARCHAR(255) DEFAULT NULL,
                    date_decision DATETIME NOT NULL,
                    created_at DATETIME DEFAULT NULL,
                    updated_at DATETIME DEFAULT NULL,
                    score DOUBLE PRECISION DEFAULT NULL,
                    entretien_id INT NOT NULL,
                    UNIQUE INDEX UNIQ_E4CA35E0548DCEA2 (entretien_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
            $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT FK_E4CA35E0548DCEA2 FOREIGN KEY (entretien_id) REFERENCES entretien (id)');

            return;
        }

        $table = $schema->getTable('decision_finale');

        $this->addSql(<<<'SQL'
            DELETE d
            FROM decision_finale d
            LEFT JOIN entretien e ON e.id = d.entretien_id
            WHERE e.id IS NULL
        SQL);

        $alterParts = [];

        if ($table->hasColumn('decision')) {
            $alterParts[] = 'CHANGE decision decision VARCHAR(20) NOT NULL';
        }

        if ($table->hasColumn('date_decision')) {
            $alterParts[] = 'CHANGE date_decision date_decision DATETIME NOT NULL';
        }

        if ($table->hasColumn('created_at')) {
            $alterParts[] = 'CHANGE created_at created_at DATETIME DEFAULT NULL';
        }

        if ($table->hasColumn('updated_at')) {
            $alterParts[] = 'CHANGE updated_at updated_at DATETIME DEFAULT NULL';
        }

        if ($alterParts !== []) {
            $this->addSql("ALTER TABLE decision_finale\n    " . implode(",\n    ", $alterParts));
        }

        if ($table->hasIndex('uq_decision_entretien')) {
            $this->addSql('DROP INDEX uq_decision_entretien ON decision_finale');
        }

        if (!$table->hasIndex('UNIQ_E4CA35E0548DCEA2')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_E4CA35E0548DCEA2 ON decision_finale (entretien_id)');
        }

        if (!$table->hasForeignKey('FK_E4CA35E0548DCEA2')) {
            $this->addSql('ALTER TABLE decision_finale ADD CONSTRAINT FK_E4CA35E0548DCEA2 FOREIGN KEY (entretien_id) REFERENCES entretien (id)');
        }
    }
}
