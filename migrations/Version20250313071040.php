<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250313071040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE grade (id INT AUTO_INCREMENT NOT NULL, number INT DEFAULT NULL, active SMALLINT DEFAULT 1, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE learner (id INT AUTO_INCREMENT NOT NULL, grade INT DEFAULT NULL, uid VARCHAR(255) DEFAULT NULL, score INT DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, notification_hour INT DEFAULT NULL, role VARCHAR(10) DEFAULT \'learner\' NOT NULL, created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, lastSeen DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, school_name VARCHAR(100) DEFAULT NULL, school_address VARCHAR(100) DEFAULT NULL, school_latitude DOUBLE PRECISION DEFAULT NULL, school_longitude DOUBLE PRECISION DEFAULT NULL, terms VARCHAR(50) DEFAULT NULL, curriculum VARCHAR(50) DEFAULT NULL, private_school TINYINT(1) DEFAULT 0, email VARCHAR(100) DEFAULT NULL, rating DOUBLE PRECISION DEFAULT \'0\', rating_cancelled DATETIME DEFAULT NULL, INDEX learner_grade_idx (grade), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE learner_streak (id INT AUTO_INCREMENT NOT NULL, learner_id INT NOT NULL, current_streak INT NOT NULL, longest_streak INT NOT NULL, questions_answered_today INT NOT NULL, last_answered_at DATETIME NOT NULL, last_streak_update_date DATETIME NOT NULL, INDEX IDX_3B8C80CA6209CB66 (learner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE learnersubjects (id INT AUTO_INCREMENT NOT NULL, learner INT DEFAULT NULL, subject INT DEFAULT NULL, higherGrade TINYINT(1) DEFAULT NULL, overideTerm TINYINT(1) DEFAULT NULL, last_updated DATETIME DEFAULT NULL, percentage DOUBLE PRECISION DEFAULT NULL, INDEX learnersubject_learner_idx (learner), INDEX learnersubject_subject_idx (subject), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, capturer INT DEFAULT NULL, reviewer INT DEFAULT NULL, subject INT DEFAULT NULL, question LONGTEXT DEFAULT NULL, type VARCHAR(45) DEFAULT NULL, context LONGTEXT DEFAULT NULL, answer MEDIUMTEXT DEFAULT NULL, options JSON DEFAULT NULL, term INT DEFAULT NULL, image_path VARCHAR(100) DEFAULT NULL, explanation LONGTEXT DEFAULT NULL, higher_grade SMALLINT DEFAULT NULL, active TINYINT(1) DEFAULT 1, posted TINYINT(1) DEFAULT 0, year INT NOT NULL, answer_image VARCHAR(100) DEFAULT NULL, status VARCHAR(10) DEFAULT \'new\' NOT NULL, created DATETIME DEFAULT CURRENT_TIMESTAMP, question_image_path VARCHAR(50) DEFAULT NULL, comment VARCHAR(100) DEFAULT NULL, ai_explanation LONGTEXT DEFAULT NULL, curriculum VARCHAR(10) DEFAULT \'CAPS\' NOT NULL, INDEX IDX_B6F7494EBCE217CC (capturer), INDEX IDX_B6F7494EE0472730 (reviewer), INDEX question_subject (subject), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE result (id INT AUTO_INCREMENT NOT NULL, question INT DEFAULT NULL, learner INT DEFAULT NULL, outcome VARCHAR(10) DEFAULT NULL, created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX result_question (question), INDEX result_learner_idx (learner), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subject (id INT AUTO_INCREMENT NOT NULL, grade INT DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, active TINYINT(1) DEFAULT 1, INDEX subject_grade_idx (grade), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subscription (id INT AUTO_INCREMENT NOT NULL, phone_number VARCHAR(15) NOT NULL, created DATETIME NOT NULL, UNIQUE INDEX UNIQ_A3C664D36B01BC5B (phone_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE learner ADD CONSTRAINT FK_8EF3834595AAE34 FOREIGN KEY (grade) REFERENCES grade (id)');
        $this->addSql('ALTER TABLE learner_streak ADD CONSTRAINT FK_3B8C80CA6209CB66 FOREIGN KEY (learner_id) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE learnersubjects ADD CONSTRAINT FK_E64CCD338EF3834 FOREIGN KEY (learner) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE learnersubjects ADD CONSTRAINT FK_E64CCD33FBCE3E7A FOREIGN KEY (subject) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EBCE217CC FOREIGN KEY (capturer) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EE0472730 FOREIGN KEY (reviewer) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494EFBCE3E7A FOREIGN KEY (subject) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC113B6F7494E FOREIGN KEY (question) REFERENCES question (id)');
        $this->addSql('ALTER TABLE result ADD CONSTRAINT FK_136AC1138EF3834 FOREIGN KEY (learner) REFERENCES learner (id)');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A595AAE34 FOREIGN KEY (grade) REFERENCES grade (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE learner DROP FOREIGN KEY FK_8EF3834595AAE34');
        $this->addSql('ALTER TABLE learner_streak DROP FOREIGN KEY FK_3B8C80CA6209CB66');
        $this->addSql('ALTER TABLE learnersubjects DROP FOREIGN KEY FK_E64CCD338EF3834');
        $this->addSql('ALTER TABLE learnersubjects DROP FOREIGN KEY FK_E64CCD33FBCE3E7A');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EBCE217CC');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EE0472730');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494EFBCE3E7A');
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC113B6F7494E');
        $this->addSql('ALTER TABLE result DROP FOREIGN KEY FK_136AC1138EF3834');
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7A595AAE34');
        $this->addSql('DROP TABLE grade');
        $this->addSql('DROP TABLE learner');
        $this->addSql('DROP TABLE learner_streak');
        $this->addSql('DROP TABLE learnersubjects');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE result');
        $this->addSql('DROP TABLE subject');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE user');
    }
}
