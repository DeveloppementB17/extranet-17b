<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Taxonomies Type/Sujet (document_kind, document_topic), champs year/kind/topic sur document, seed dossiers Stratégie/Pilotage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document_kind (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_DOCUMENT_KIND_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document_topic (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, UNIQUE INDEX UNIQ_DOCUMENT_TOPIC_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE document ADD year SMALLINT DEFAULT NULL, ADD kind_id INT DEFAULT NULL, ADD topic_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76EA1C789 FOREIGN KEY (kind_id) REFERENCES document_kind (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A761F149292 FOREIGN KEY (topic_id) REFERENCES document_topic (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D8698A76EA1C789 ON document (kind_id)');
        $this->addSql('CREATE INDEX IDX_D8698A761F149292 ON document (topic_id)');

        foreach ([
            'Feuille de route',
            'Plan de communication',
            'Compte-rendu',
            'Tips et bonnes pratiques',
        ] as $kindName) {
            $this->addSql('INSERT INTO document_kind (name) SELECT :name FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM document_kind WHERE name = :name)', [
                'name' => $kindName,
            ]);
        }

        foreach ([
            'Stratégie de communication',
            'Digital et social media',
            'Création graphique',
            'Relations presse',
        ] as $topicName) {
            $this->addSql('INSERT INTO document_topic (name) SELECT :name FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM document_topic WHERE name = :name)', [
                'name' => $topicName,
            ]);
        }

        // Racine « Stratégie de communication » + enfants Stratégie / Pilotage
        $this->addSql("INSERT INTO document_category (name, parent_id)
            SELECT 'Stratégie de communication', NULL FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM document_category WHERE name = 'Stratégie de communication' AND parent_id IS NULL)");

        $this->addSql("INSERT INTO document_category (name, parent_id)
            SELECT 'Stratégie', c.id FROM document_category c
            WHERE c.name = 'Stratégie de communication' AND c.parent_id IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM document_category child
                WHERE child.name = 'Stratégie' AND child.parent_id = c.id
              )");

        $this->addSql("INSERT INTO document_category (name, parent_id)
            SELECT 'Pilotage', c.id FROM document_category c
            WHERE c.name = 'Stratégie de communication' AND c.parent_id IS NULL
              AND NOT EXISTS (
                SELECT 1 FROM document_category child
                WHERE child.name = 'Pilotage' AND child.parent_id = c.id
              )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76EA1C789');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A761F149292');
        $this->addSql('DROP INDEX IDX_D8698A76EA1C789 ON document');
        $this->addSql('DROP INDEX IDX_D8698A761F149292 ON document');
        $this->addSql('ALTER TABLE document DROP year, DROP kind_id, DROP topic_id');
        $this->addSql('DROP TABLE document_kind');
        $this->addSql('DROP TABLE document_topic');
    }
}
