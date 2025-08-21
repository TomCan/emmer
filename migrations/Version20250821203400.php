<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250821203400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'File index + add version prep';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bucket CHANGE ctime ctime DATETIME NOT NULL');
        $this->addSql('DROP INDEX name_idx ON file');
        $this->addSql('ALTER TABLE file ADD version INT NOT NULL');
        $this->addSql('UPDATE file SET version=0');
        $this->addSql('CREATE UNIQUE INDEX bucket_name_version_idx ON file (bucket_id, name, version)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bucket CHANGE ctime ctime DATETIME DEFAULT \'2000-01-02 03:04:05\' NOT NULL');
        $this->addSql('DROP INDEX bucket_name_version_idx ON file');
        $this->addSql('ALTER TABLE file DROP version');
        $this->addSql('CREATE UNIQUE INDEX name_idx ON file (name)');
    }
}
