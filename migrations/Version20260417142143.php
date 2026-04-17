<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260417142143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag agency sur entreprise + liaison user_managed_entreprise pour ROLE_17B_USER.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_managed_entreprise (user_id INT NOT NULL, entreprise_id INT NOT NULL, INDEX IDX_1D37F740A76ED395 (user_id), INDEX IDX_1D37F740A4AEAFEA (entreprise_id), PRIMARY KEY (user_id, entreprise_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_managed_entreprise ADD CONSTRAINT FK_1D37F740A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_managed_entreprise ADD CONSTRAINT FK_1D37F740A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE entreprise ADD agency TINYINT DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE entreprise SET agency = 1 WHERE slug = '17b'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_managed_entreprise DROP FOREIGN KEY FK_1D37F740A76ED395');
        $this->addSql('ALTER TABLE user_managed_entreprise DROP FOREIGN KEY FK_1D37F740A4AEAFEA');
        $this->addSql('DROP TABLE user_managed_entreprise');
        $this->addSql('ALTER TABLE entreprise DROP agency');
    }
}
