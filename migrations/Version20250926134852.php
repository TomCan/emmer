<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250926134852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cors_rule table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cors_rule (id INT AUTO_INCREMENT NOT NULL, custom_id VARCHAR(255) DEFAULT NULL, allowed_methods LONGTEXT NOT NULL, allowed_origins LONGTEXT NOT NULL, allowed_headers LONGTEXT DEFAULT NULL, expose_headers LONGTEXT DEFAULT NULL, max_age_seconds INT DEFAULT NULL, bucket_id INT NOT NULL, INDEX IDX_A1B28E7F84CE584D (bucket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE cors_rule ADD CONSTRAINT FK_A1B28E7F84CE584D FOREIGN KEY (bucket_id) REFERENCES bucket (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cors_rule DROP FOREIGN KEY FK_A1B28E7F84CE584D');
        $this->addSql('DROP TABLE cors_rule');
    }
}
