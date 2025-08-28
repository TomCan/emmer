<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250828190030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Versioning';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bucket ADD versioned TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE file DROP INDEX bucket_name_version_idx, ADD INDEX bucket_name_version_idx (bucket_id, name, version)');
        $this->addSql('ALTER TABLE file ADD current_version TINYINT(1) NOT NULL, CHANGE version version VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE file SET current_version=1, version=NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bucket DROP versioned');
        $this->addSql('ALTER TABLE file DROP current_version, version');
        $this->addSql('ALTER TABLE file ADD version INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE file DROP INDEX bucket_name_version_idx, ADD UNIQUE INDEX bucket_name_version_idx (bucket_id, name, version)');
    }
}
