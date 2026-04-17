<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417101040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE document_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, parent_id INT DEFAULT NULL, entreprise_id INT NOT NULL, INDEX IDX_898DE898727ACA70 (parent_id), INDEX IDX_898DE898A4AEAFEA (entreprise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE document_category ADD CONSTRAINT FK_898DE898727ACA70 FOREIGN KEY (parent_id) REFERENCES document_category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_category ADD CONSTRAINT FK_898DE898A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE document ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7612469DE2 FOREIGN KEY (category_id) REFERENCES document_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8698A7612469DE2 ON document (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document_category DROP FOREIGN KEY FK_898DE898727ACA70');
        $this->addSql('ALTER TABLE document_category DROP FOREIGN KEY FK_898DE898A4AEAFEA');
        $this->addSql('DROP TABLE document_category');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7612469DE2');
        $this->addSql('DROP INDEX IDX_D8698A7612469DE2 ON document');
        $this->addSql('ALTER TABLE document DROP category_id');
    }
}
