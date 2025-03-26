<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250326065514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add capturer field to subject table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject ADD capturer INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A1A9C0F7A FOREIGN KEY (capturer) REFERENCES learner (id)');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A1A9C0F7A ON subject (capturer)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7A1A9C0F7A');
        $this->addSql('DROP INDEX IDX_FBCE3E7A1A9C0F7A ON subject');
        $this->addSql('ALTER TABLE subject DROP capturer');
    }
}
