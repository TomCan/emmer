<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250819195248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User and access key';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_key (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(32) NOT NULL, secret VARCHAR(64) NOT NULL, label VARCHAR(255) NOT NULL, user_id INT NOT NULL, INDEX IDX_EAD0F67CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE access_key ADD CONSTRAINT FK_EAD0F67CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_key DROP FOREIGN KEY FK_EAD0F67CA76ED395');
        $this->addSql('DROP TABLE access_key');
        $this->addSql('DROP TABLE user');
    }
}
