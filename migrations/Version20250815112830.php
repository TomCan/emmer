<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250815112830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add path to bucket';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bucket ADD path VARCHAR(1024) NOT NULL');
        $this->addSql('UPDATE bucket SET path=name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bucket DROP path');
    }
}
