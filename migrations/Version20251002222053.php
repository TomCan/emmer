<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251002222053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make access_key field larger for storing it encrypted';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_key CHANGE secret secret VARCHAR(128) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_key CHANGE secret secret VARCHAR(64) NOT NULL');
    }
}
