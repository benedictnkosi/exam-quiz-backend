<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250313071826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE favorites (id BIGINT AUTO_INCREMENT NOT NULL, learner INT DEFAULT NULL, question INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_E46960F58EF3834 (learner), INDEX IDX_E46960F5B6F7494E (question), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE favorites ADD CONSTRAINT FK_E46960F58EF3834 FOREIGN KEY (learner) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE favorites ADD CONSTRAINT FK_E46960F5B6F7494E FOREIGN KEY (question) REFERENCES question (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE favorites DROP FOREIGN KEY FK_E46960F58EF3834');
        $this->addSql('ALTER TABLE favorites DROP FOREIGN KEY FK_E46960F5B6F7494E');
        $this->addSql('DROP TABLE favorites');
    }
}
