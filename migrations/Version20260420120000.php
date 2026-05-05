<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260420120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des crédits temps, catégories et historique des mouvements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE time_credit_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_E7DBD2F15E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE time_credit (id INT AUTO_INCREMENT NOT NULL, entreprise_id INT NOT NULL, category_id INT DEFAULT NULL, created_by_id INT NOT NULL, title VARCHAR(255) NOT NULL, total_minutes INT NOT NULL, remaining_minutes INT NOT NULL, dossier_number VARCHAR(120) DEFAULT NULL, archived TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C2CE071DA4AEAFEA (entreprise_id), INDEX IDX_C2CE071D12469DE2 (category_id), INDEX IDX_C2CE071DB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE time_credit_movement (id INT AUTO_INCREMENT NOT NULL, time_credit_id INT NOT NULL, created_by_id INT NOT NULL, type VARCHAR(30) NOT NULL, delta_minutes INT NOT NULL, description VARCHAR(2000) DEFAULT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_692DF90DC60B8E31 (time_credit_id), INDEX IDX_692DF90DB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE time_credit ADD CONSTRAINT FK_C2CE071DA4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE time_credit ADD CONSTRAINT FK_C2CE071D12469DE2 FOREIGN KEY (category_id) REFERENCES time_credit_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE time_credit ADD CONSTRAINT FK_C2CE071DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE time_credit_movement ADD CONSTRAINT FK_692DF90DC60B8E31 FOREIGN KEY (time_credit_id) REFERENCES time_credit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_credit_movement ADD CONSTRAINT FK_692DF90DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE time_credit DROP FOREIGN KEY FK_C2CE071DA4AEAFEA');
        $this->addSql('ALTER TABLE time_credit DROP FOREIGN KEY FK_C2CE071D12469DE2');
        $this->addSql('ALTER TABLE time_credit DROP FOREIGN KEY FK_C2CE071DB03A8386');
        $this->addSql('ALTER TABLE time_credit_movement DROP FOREIGN KEY FK_692DF90DC60B8E31');
        $this->addSql('ALTER TABLE time_credit_movement DROP FOREIGN KEY FK_692DF90DB03A8386');
        $this->addSql('DROP TABLE time_credit');
        $this->addSql('DROP TABLE time_credit_movement');
        $this->addSql('DROP TABLE time_credit_category');
    }
}
