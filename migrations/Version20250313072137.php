<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250313072137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subject_points (id BIGINT AUTO_INCREMENT NOT NULL, learner INT DEFAULT NULL, subject INT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, points INT DEFAULT 0 NOT NULL, INDEX subject_points_learner_idx (learner), INDEX subject_points_subject_idx (subject), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subject_points ADD CONSTRAINT FK_AFA3470E8EF3834 FOREIGN KEY (learner) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE subject_points ADD CONSTRAINT FK_AFA3470EFBCE3E7A FOREIGN KEY (subject) REFERENCES subject (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subject_points DROP FOREIGN KEY FK_AFA3470E8EF3834');
        $this->addSql('ALTER TABLE subject_points DROP FOREIGN KEY FK_AFA3470EFBCE3E7A');
        $this->addSql('DROP TABLE subject_points');
    }
}
