<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261124100055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE participation ADD COLUMN attaques_importees SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__participation AS SELECT id, trophees, attaques_gagnees, attaques_perdues, defenses_gagnees, defenses_perdues, joueur_id, groupe_id FROM participation');
        $this->addSql('DROP TABLE participation');
        $this->addSql('CREATE TABLE participation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, trophees INTEGER NOT NULL, attaques_gagnees SMALLINT NOT NULL, attaques_perdues SMALLINT NOT NULL, defenses_gagnees SMALLINT NOT NULL, defenses_perdues SMALLINT NOT NULL, joueur_id INTEGER NOT NULL, groupe_id INTEGER NOT NULL, CONSTRAINT FK_AB55E24FA9E2D76C FOREIGN KEY (joueur_id) REFERENCES joueur (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AB55E24F7A45358C FOREIGN KEY (groupe_id) REFERENCES groupe_ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO participation (id, trophees, attaques_gagnees, attaques_perdues, defenses_gagnees, defenses_perdues, joueur_id, groupe_id) SELECT id, trophees, attaques_gagnees, attaques_perdues, defenses_gagnees, defenses_perdues, joueur_id, groupe_id FROM __temp__participation');
        $this->addSql('DROP TABLE __temp__participation');
        $this->addSql('CREATE UNIQUE INDEX participation_joueur_groupe ON participation (joueur_id, groupe_id)');
        $this->addSql('CREATE INDEX IDX_AB55E24FA9E2D76C ON participation (joueur_id)');
        $this->addSql('CREATE INDEX IDX_AB55E24F7A45358C ON participation (groupe_id)');
    }
}
