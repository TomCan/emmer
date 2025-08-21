<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250821170203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bucket owner';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bucket ADD owner_id INT NOT NULL, ADD ctime DATETIME NOT NULL DEFAULT \'2000-01-02 03:04:05\'');
        $this->addSql('UPDATE bucket SET owner_id=\'1\', ctime=\'2000-01-02 03:04:05\'');
        $this->addSql('ALTER TABLE bucket ADD CONSTRAINT FK_E73F36A67E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_E73F36A67E3C61F9 ON bucket (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bucket DROP FOREIGN KEY FK_E73F36A67E3C61F9');
        $this->addSql('DROP INDEX IDX_E73F36A67E3C61F9 ON bucket');
        $this->addSql('ALTER TABLE bucket DROP owner_id, DROP ctime');
    }
}
