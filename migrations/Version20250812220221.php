<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812220221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial version of file';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE file (id INT AUTO_INCREMENT NOT NULL, name VARBINARY(1024) NOT NULL, path VARCHAR(255) NOT NULL, size INT NOT NULL, cdate DATETIME NOT NULL, mtime DATETIME NOT NULL, atime DATETIME NOT NULL, bucket_id INT NOT NULL, INDEX IDX_8C9F361084CE584D (bucket_id), UNIQUE INDEX name_idx (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE file ADD CONSTRAINT FK_8C9F361084CE584D FOREIGN KEY (bucket_id) REFERENCES bucket (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file DROP FOREIGN KEY FK_8C9F361084CE584D');
        $this->addSql('DROP TABLE file');
    }
}
