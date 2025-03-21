<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250321110428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename expoPushToken column to expo_push_token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learner CHANGE expoPushToken expo_push_token VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learner CHANGE expo_push_token expoPushToken VARCHAR(255) DEFAULT NULL');
    }
}
