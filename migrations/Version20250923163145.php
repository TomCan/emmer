<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250923163145 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make index names unique';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bucket RENAME INDEX name_idx TO bucket_name_idx');
        $this->addSql('ALTER TABLE filepart RENAME INDEX name_idx TO filepart_name_idx');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bucket RENAME INDEX bucket_name_idx TO name_idx');
        $this->addSql('ALTER TABLE filepart RENAME INDEX filepart_name_idx TO name_idx');
    }
}
