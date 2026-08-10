<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810160900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag legacy + id source ancien extranet sur entreprise.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise ADD legacy TINYINT(1) DEFAULT 0 NOT NULL, ADD legacy_source_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_entreprise_legacy_source_id ON entreprise (legacy_source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_entreprise_legacy_source_id ON entreprise');
        $this->addSql('ALTER TABLE entreprise DROP legacy, DROP legacy_source_id');
    }
}
