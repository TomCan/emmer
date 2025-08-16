<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250816121954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE filepart (id INT AUTO_INCREMENT NOT NULL, name VARBINARY(1024) NOT NULL, path VARCHAR(255) NOT NULL, size INT NOT NULL, mtime DATETIME NOT NULL, etag VARCHAR(255) NOT NULL, partnumber INT NOT NULL, file_id INT NOT NULL, INDEX IDX_2B27E7A593CB796C (file_id), UNIQUE INDEX name_idx (name), UNIQUE INDEX filepart_idx (file_id, partnumber), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE filepart ADD CONSTRAINT FK_2B27E7A593CB796C FOREIGN KEY (file_id) REFERENCES file (id)');
        $this->addSql('INSERT INTO filepart (file_id, name, path, size, mtime, etag, partnumber) SELECT id, name, path, size, mtime, etag, 1 FROM file');
        $this->addSql('ALTER TABLE file DROP COLUMN path');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD COLUMN path VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE filepart DROP FOREIGN KEY FK_2B27E7A593CB796C');
        $this->addSql('DROP TABLE filepart');
    }
}
