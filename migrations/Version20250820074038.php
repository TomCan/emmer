<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250820074038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE policy (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, policy LONGTEXT NOT NULL, bucket_id INT DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_F07D051684CE584D (bucket_id), INDEX IDX_F07D0516A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE policy ADD CONSTRAINT FK_F07D051684CE584D FOREIGN KEY (bucket_id) REFERENCES bucket (id)');
        $this->addSql('ALTER TABLE policy ADD CONSTRAINT FK_F07D0516A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE policy DROP FOREIGN KEY FK_F07D051684CE584D');
        $this->addSql('ALTER TABLE policy DROP FOREIGN KEY FK_F07D0516A76ED395');
        $this->addSql('DROP TABLE policy');
    }
}
