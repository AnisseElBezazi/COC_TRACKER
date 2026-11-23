<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261123204658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE joueur ADD COLUMN importe_le DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__joueur AS SELECT id, tag, nom, niveau_hdv, clan_tag, clan_nom, ligue_id FROM joueur');
        $this->addSql('DROP TABLE joueur');
        $this->addSql('CREATE TABLE joueur (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tag VARCHAR(15) NOT NULL, nom VARCHAR(50) DEFAULT NULL, niveau_hdv SMALLINT DEFAULT NULL, clan_tag VARCHAR(15) DEFAULT NULL, clan_nom VARCHAR(50) DEFAULT NULL, ligue_id INTEGER DEFAULT NULL, CONSTRAINT FK_FD71A9C54D7328E5 FOREIGN KEY (ligue_id) REFERENCES ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO joueur (id, tag, nom, niveau_hdv, clan_tag, clan_nom, ligue_id) SELECT id, tag, nom, niveau_hdv, clan_tag, clan_nom, ligue_id FROM __temp__joueur');
        $this->addSql('DROP TABLE __temp__joueur');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FD71A9C5389B783 ON joueur (tag)');
        $this->addSql('CREATE INDEX IDX_FD71A9C54D7328E5 ON joueur (ligue_id)');
    }
}
