<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417125647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document ADD external_url VARCHAR(2048) DEFAULT NULL, CHANGE original_name original_name VARCHAR(255) DEFAULT NULL, CHANGE storage_name storage_name VARCHAR(255) DEFAULT NULL, CHANGE storage_path storage_path VARCHAR(255) DEFAULT NULL, CHANGE mime_type mime_type VARCHAR(100) DEFAULT NULL, CHANGE size size INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP external_url, CHANGE original_name original_name VARCHAR(255) NOT NULL, CHANGE storage_name storage_name VARCHAR(255) NOT NULL, CHANGE storage_path storage_path VARCHAR(255) NOT NULL, CHANGE mime_type mime_type VARCHAR(100) NOT NULL, CHANGE size size INT NOT NULL');
    }
}
