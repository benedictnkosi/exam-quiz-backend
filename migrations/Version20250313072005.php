<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250313072005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE learner ADD points INT DEFAULT 0 NOT NULL, ADD streak INT DEFAULT 0 NOT NULL, ADD streak_last_updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, ADD avatar VARCHAR(255) DEFAULT \'8.png\' NOT NULL, DROP score, CHANGE uid uid VARCHAR(45) DEFAULT NULL, CHANGE notification_hour notification_hour SMALLINT DEFAULT 0 NOT NULL, CHANGE school_name school_name VARCHAR(50) DEFAULT NULL, CHANGE private_school private_school TINYINT(1) DEFAULT NULL, CHANGE rating rating DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE learner ADD score INT DEFAULT NULL, DROP points, DROP streak, DROP streak_last_updated, DROP avatar, CHANGE uid uid VARCHAR(255) DEFAULT NULL, CHANGE notification_hour notification_hour INT DEFAULT NULL, CHANGE school_name school_name VARCHAR(100) DEFAULT NULL, CHANGE private_school private_school TINYINT(1) DEFAULT 0, CHANGE rating rating DOUBLE PRECISION DEFAULT \'0\'');
    }
}
