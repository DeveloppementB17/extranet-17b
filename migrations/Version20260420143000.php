<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend les catégories de documents globales (non rattachées à une entreprise).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_category DROP FOREIGN KEY FK_898DE898A4AEAFEA');
        $this->addSql('ALTER TABLE document_category MODIFY entreprise_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document_category ADD CONSTRAINT FK_898DE898A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_category DROP FOREIGN KEY FK_898DE898A4AEAFEA');
        $this->addSql("UPDATE document_category SET entreprise_id = (SELECT id FROM entreprise WHERE agency = 1 ORDER BY id ASC LIMIT 1) WHERE entreprise_id IS NULL");
        $this->addSql('ALTER TABLE document_category MODIFY entreprise_id INT NOT NULL');
        $this->addSql('ALTER TABLE document_category ADD CONSTRAINT FK_898DE898A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE RESTRICT');
    }
}
