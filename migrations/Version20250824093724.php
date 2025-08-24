<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250824093724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add roles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD roles LONGTEXT NOT NULL');
        $this->addSql('UPDATE user SET roles=\'ROOT\' WHERE id=1');
        $this->addSql('UPDATE user SET roles=\'USER\' WHERE id>1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP roles');
    }
}
