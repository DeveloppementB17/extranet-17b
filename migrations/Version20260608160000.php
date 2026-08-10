<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260608160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'URL de site monitor optionnelle sur les crédits temps (liaison interventions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE time_credit ADD site_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE time_credit DROP site_url');
    }
}
