<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250922212612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ctime and newer_noncurrent_versions for lifecycle rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD ctime DATETIME, ADD nctime DATETIME, ADD newer_noncurrent_versions INT NOT NULL');
        $this->addSql('UPDATE file SET ctime=mtime, newer_noncurrent_versions = 0');
        $this->addSql('UPDATE file SET nctime=mtime WHERE current_version = 0');
        $this->addSql('ALTER TABLE file MODIFY ctime DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file DROP ctime, DROP newer_noncurrent_versions');
    }
}
