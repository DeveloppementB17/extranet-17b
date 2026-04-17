<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417093409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, original_name VARCHAR(255) NOT NULL, storage_name VARCHAR(255) NOT NULL, storage_path VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_D8698A76A4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A4AEAFEA');
        $this->addSql('DROP TABLE document');
    }
}
