<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260126142211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pointage ADD latitude_entree NUMERIC(10, 8) DEFAULT NULL, ADD longitude_entree NUMERIC(11, 8) DEFAULT NULL, ADD latitude_sortie NUMERIC(10, 8) DEFAULT NULL, ADD longitude_sortie NUMERIC(11, 8) DEFAULT NULL, DROP is_active');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B20FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B20FB88E14F');
        $this->addSql('ALTER TABLE pointage ADD is_active TINYINT(1) NOT NULL, DROP latitude_entree, DROP longitude_entree, DROP latitude_sortie, DROP longitude_sortie');
    }
}
