<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250917182611 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lifecycle_rules (id INT AUTO_INCREMENT NOT NULL, rules LONGTEXT NOT NULL, bucket_id INT NOT NULL, INDEX IDX_A65A60AD84CE584D (bucket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE lifecycle_rules ADD CONSTRAINT FK_A65A60AD84CE584D FOREIGN KEY (bucket_id) REFERENCES bucket (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lifecycle_rules DROP FOREIGN KEY FK_A65A60AD84CE584D');
        $this->addSql('DROP TABLE lifecycle_rules');
    }
}
