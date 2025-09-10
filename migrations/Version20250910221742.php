<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910221742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'eTag includes "';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE file SET etag = CONCAT(\'"\', etag, \'"\') WHERE etag <> \'\'');
        $this->addSql('UPDATE filepart SET etag = CONCAT(\'"\', etag, \'"\') WHERE etag <> \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE file SET etag =  SUBSTR(SUBSTR(etag, 2), 1, LENGTH(etag) - 2) WHERE etag LIKE \'"%"\'');
        $this->addSql('UPDATE filepart SET etag =  SUBSTR(SUBSTR(etag, 2), 1, LENGTH(etag) - 2) WHERE etag LIKE \'"%"\'');
    }
}
