<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417121018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Document: rattachement client + traçabilité upload (uploaded_by).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD client_id INT DEFAULT NULL, ADD uploaded_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE document d SET d.client_id = (SELECT u.id FROM user u WHERE u.email = \'client@17b.test\' LIMIT 1), d.uploaded_by_id = (SELECT u2.id FROM user u2 WHERE u2.email = \'admin@17b.test\' LIMIT 1) WHERE d.client_id IS NULL');
        $this->addSql('ALTER TABLE document MODIFY client_id INT NOT NULL, MODIFY uploaded_by_id INT NOT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7619EB6921 FOREIGN KEY (client_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_D8698A7619EB6921 ON document (client_id)');
        $this->addSql('CREATE INDEX IDX_D8698A76A2B28FE8 ON document (uploaded_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7619EB6921');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76A2B28FE8');
        $this->addSql('DROP INDEX IDX_D8698A7619EB6921 ON document');
        $this->addSql('DROP INDEX IDX_D8698A76A2B28FE8 ON document');
        $this->addSql('ALTER TABLE document DROP client_id, DROP uploaded_by_id');
    }
}
