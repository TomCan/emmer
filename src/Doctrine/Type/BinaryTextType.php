<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\BinaryType;

class BinaryTextType extends BinaryType
{
    /*
     * Custom type that extends from binary, but specifically for binary text
     * Forces parameter binding to be string as otherwise not working properly for sqlite
     */

    public const BINARY_TEXT = 'binary_text';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform instanceof SQLitePlatform) {
            return 'CLOB';
        }

        return parent::getSQLDeclaration($column, $platform);
    }

    public function getName(): string
    {
        return self::BINARY_TEXT;
    }

    public function getMappedDatabaseTypes(AbstractPlatform $platform): array
    {
        if ($platform instanceof SQLitePlatform) {
            return ['clob'];
        }

        return parent::getMappedDatabaseTypes($platform);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::STRING;
    }
}
