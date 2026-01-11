<?php

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class VectorType extends Type
{
    public const VECTOR = 'vector';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $length = $column['length'] ?? 1536; // Default dimension
        return sprintf('vector(%d)', $length);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value;
    }

    public function getName(): string
    {
        return self::VECTOR;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
