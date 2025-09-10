<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910173326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Size is now unsigned bigint';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file CHANGE size size BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE filepart CHANGE size size BIGINT UNSIGNED NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE file CHANGE size size INT NOT NULL');
        $this->addSql('ALTER TABLE filepart CHANGE size size INT NOT NULL');
    }
}
