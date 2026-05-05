<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend document.client optionnel pour rattachement principal via entreprise.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7619EB6921');
        $this->addSql('ALTER TABLE document MODIFY client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7619EB6921 FOREIGN KEY (client_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A7619EB6921');
        $this->addSql('UPDATE document SET client_id = (SELECT u.id FROM `user` u WHERE u.email = \'admin-test@17b.test\' LIMIT 1) WHERE client_id IS NULL');
        $this->addSql('ALTER TABLE document MODIFY client_id INT NOT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7619EB6921 FOREIGN KEY (client_id) REFERENCES `user` (id) ON DELETE RESTRICT');
    }
}
