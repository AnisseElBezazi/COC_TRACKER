<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120120950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE combat (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_combat VARCHAR(10) NOT NULL, etoiles SMALLINT NOT NULL, pourcentage SMALLINT NOT NULL, trophees SMALLINT DEFAULT NULL, date_combat DATETIME DEFAULT NULL, empreinte VARCHAR(40) NOT NULL, attaquant_id INTEGER NOT NULL, defenseur_id INTEGER NOT NULL, groupe_id INTEGER DEFAULT NULL, composition_id INTEGER DEFAULT NULL, CONSTRAINT FK_8D51E398743E27C4 FOREIGN KEY (attaquant_id) REFERENCES joueur (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D51E39892D3E6E2 FOREIGN KEY (defenseur_id) REFERENCES joueur (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D51E3987A45358C FOREIGN KEY (groupe_id) REFERENCES groupe_ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_8D51E39887A2E12 FOREIGN KEY (composition_id) REFERENCES composition (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D51E39832AF9675 ON combat (empreinte)');
        $this->addSql('CREATE INDEX IDX_8D51E398743E27C4 ON combat (attaquant_id)');
        $this->addSql('CREATE INDEX IDX_8D51E39892D3E6E2 ON combat (defenseur_id)');
        $this->addSql('CREATE INDEX IDX_8D51E3987A45358C ON combat (groupe_id)');
        $this->addSql('CREATE INDEX IDX_8D51E39887A2E12 ON combat (composition_id)');
        $this->addSql('CREATE TABLE composition (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, code VARCHAR(255) NOT NULL, archetype_troupes VARCHAR(60) DEFAULT NULL, archetype_heros VARCHAR(120) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C7F434777153098 ON composition (code)');
        $this->addSql('CREATE TABLE composition_troupe (composition_id INTEGER NOT NULL, troupe_id INTEGER NOT NULL, PRIMARY KEY (composition_id, troupe_id), CONSTRAINT FK_41FCE00487A2E12 FOREIGN KEY (composition_id) REFERENCES composition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_41FCE004EF084B38 FOREIGN KEY (troupe_id) REFERENCES troupe (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_41FCE00487A2E12 ON composition_troupe (composition_id)');
        $this->addSql('CREATE INDEX IDX_41FCE004EF084B38 ON composition_troupe (troupe_id)');
        $this->addSql('CREATE TABLE groupe_ligue (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tag VARCHAR(15) NOT NULL, saison_id INTEGER NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, ligue_id INTEGER NOT NULL, CONSTRAINT FK_AB6BDE494D7328E5 FOREIGN KEY (ligue_id) REFERENCES ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX groupe_ligue_tag_saison ON groupe_ligue (tag, saison_id)');
        $this->addSql('CREATE INDEX IDX_AB6BDE494D7328E5 ON groupe_ligue (ligue_id)');
        $this->addSql('CREATE TABLE joueur (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, tag VARCHAR(15) NOT NULL, nom VARCHAR(50) DEFAULT NULL, niveau_hdv SMALLINT DEFAULT NULL, clan_tag VARCHAR(15) DEFAULT NULL, clan_nom VARCHAR(50) DEFAULT NULL, ligue_id INTEGER DEFAULT NULL, CONSTRAINT FK_FD71A9C54D7328E5 FOREIGN KEY (ligue_id) REFERENCES ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FD71A9C5389B783 ON joueur (tag)');
        $this->addSql('CREATE INDEX IDX_FD71A9C54D7328E5 ON joueur (ligue_id)');
        $this->addSql('CREATE TABLE ligne_composition (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quantite SMALLINT NOT NULL, composition_id INTEGER NOT NULL, troupe_id INTEGER NOT NULL, CONSTRAINT FK_6F9A837087A2E12 FOREIGN KEY (composition_id) REFERENCES composition (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6F9A8370EF084B38 FOREIGN KEY (troupe_id) REFERENCES troupe (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX ligne_composition_troupe ON ligne_composition (composition_id, troupe_id)');
        $this->addSql('CREATE INDEX IDX_6F9A837087A2E12 ON ligne_composition (composition_id)');
        $this->addSql('CREATE INDEX IDX_6F9A8370EF084B38 ON ligne_composition (troupe_id)');
        $this->addSql('CREATE TABLE ligue (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_supercell INTEGER NOT NULL, nom VARCHAR(50) NOT NULL, url_icone VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEC6101996226DFE ON ligue (id_supercell)');
        $this->addSql('CREATE TABLE participation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, trophees INTEGER NOT NULL, attaques_gagnees SMALLINT NOT NULL, attaques_perdues SMALLINT NOT NULL, defenses_gagnees SMALLINT NOT NULL, defenses_perdues SMALLINT NOT NULL, joueur_id INTEGER NOT NULL, groupe_id INTEGER NOT NULL, CONSTRAINT FK_AB55E24FA9E2D76C FOREIGN KEY (joueur_id) REFERENCES joueur (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AB55E24F7A45358C FOREIGN KEY (groupe_id) REFERENCES groupe_ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX participation_joueur_groupe ON participation (joueur_id, groupe_id)');
        $this->addSql('CREATE INDEX IDX_AB55E24FA9E2D76C ON participation (joueur_id)');
        $this->addSql('CREATE INDEX IDX_AB55E24F7A45358C ON participation (groupe_id)');
        $this->addSql('CREATE TABLE resultat_semaine (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, saison_id INTEGER NOT NULL, classement SMALLINT NOT NULL, trophees INTEGER NOT NULL, attaques_gagnees SMALLINT NOT NULL, attaques_perdues SMALLINT NOT NULL, etoiles_attaque SMALLINT NOT NULL, defenses_gagnees SMALLINT NOT NULL, defenses_perdues SMALLINT NOT NULL, etoiles_defense SMALLINT NOT NULL, combats_max SMALLINT NOT NULL, joueur_id INTEGER NOT NULL, ligue_id INTEGER NOT NULL, CONSTRAINT FK_6EFF37DFA9E2D76C FOREIGN KEY (joueur_id) REFERENCES joueur (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6EFF37DF4D7328E5 FOREIGN KEY (ligue_id) REFERENCES ligue (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX resultat_joueur_saison ON resultat_semaine (joueur_id, saison_id)');
        $this->addSql('CREATE INDEX IDX_6EFF37DFA9E2D76C ON resultat_semaine (joueur_id)');
        $this->addSql('CREATE INDEX IDX_6EFF37DF4D7328E5 ON resultat_semaine (ligue_id)');
        $this->addSql('CREATE TABLE troupe (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, type VARCHAR(15) NOT NULL, id_jeu INTEGER DEFAULT NULL, place SMALLINT DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_81FBFC146C6E55B5 ON troupe (nom)');
        $this->addSql('CREATE UNIQUE INDEX troupe_type_id_jeu ON troupe (type, id_jeu)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE combat');
        $this->addSql('DROP TABLE composition');
        $this->addSql('DROP TABLE composition_troupe');
        $this->addSql('DROP TABLE groupe_ligue');
        $this->addSql('DROP TABLE joueur');
        $this->addSql('DROP TABLE ligne_composition');
        $this->addSql('DROP TABLE ligue');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE resultat_semaine');
        $this->addSql('DROP TABLE troupe');
    }
}
