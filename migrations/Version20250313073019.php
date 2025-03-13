<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250313073019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE badge (id BIGINT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) DEFAULT NULL, rules VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE learner_badges (id BIGINT AUTO_INCREMENT NOT NULL, learner INT DEFAULT NULL, badge BIGINT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_5AF0C8798EF3834 (learner), INDEX IDX_5AF0C879FEF0481D (badge), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE learner_badges ADD CONSTRAINT FK_5AF0C8798EF3834 FOREIGN KEY (learner) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE learner_badges ADD CONSTRAINT FK_5AF0C879FEF0481D FOREIGN KEY (badge) REFERENCES badge (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE learner_badges DROP FOREIGN KEY FK_5AF0C8798EF3834');
        $this->addSql('ALTER TABLE learner_badges DROP FOREIGN KEY FK_5AF0C879FEF0481D');
        $this->addSql('DROP TABLE badge');
        $this->addSql('DROP TABLE learner_badges');
    }
}
