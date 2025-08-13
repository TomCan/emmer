<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250812202540 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial version of bucket';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bucket (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, description VARCHAR(255) NOT NULL, UNIQUE INDEX name_idx (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F361084CE584D');
        $this->addSql('DROP TABLE bucket');
    }
}
