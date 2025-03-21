<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250321105658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expoPushToken column to learner table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learner ADD expoPushToken VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE learner DROP expoPushToken');
    }
}
