<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une date document modifiable (initialisée à la date d’upload).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD document_date DATETIME DEFAULT NULL');
        $this->addSql('UPDATE document SET document_date = created_at WHERE document_date IS NULL');
        $this->addSql('ALTER TABLE document MODIFY document_date DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP document_date');
    }
}
