<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810164000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identifiant source ancien extranet sur les crédits temps.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE time_credit ADD legacy_source_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_time_credit_legacy_source_id ON time_credit (legacy_source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_time_credit_legacy_source_id ON time_credit');
        $this->addSql('ALTER TABLE time_credit DROP legacy_source_id');
    }
}
