<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

/**
 * The native scalar field encodings used in an InnoDB reference.
 * @visibility public
 * @example Reading a stored temporal encoding
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind::DateTime->value // => 'DATETIME'
 */
enum ForeignKeyScalarKind: string
{
    case Decimal = 'DECIMAL';
    case Bit = 'BIT';
    case Single = 'FLOAT';
    case Double = 'DOUBLE';
    case Date = 'DATE';
    case Year = 'YEAR';
    case Time = 'TIME';
    case DateTime = 'DATETIME';
    case Timestamp = 'TIMESTAMP';

    /**
     * Identifies scalar encodings that participate in native byte comparison.
     */
    public function packed(): bool
    {
        return match ($this) {
            self::Decimal, self::Bit, self::Time, self::DateTime, self::Timestamp => true,
            self::Single, self::Double, self::Date, self::Year => false,
        };
    }

    /**
     * Reports the native dimension range, scale flag and optional display flag.
     * @return array{int, int, bool, bool}|array{null, null, false, false}
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::Decimal => [1, 65, true, false],
            self::Single, self::Double => [1, 255, true, true],
            self::Bit => [1, 64, false, false],
            self::Time, self::DateTime, self::Timestamp => [0, 6, false, false],
            self::Date, self::Year => [null, null, false, false],
        };
    }

    /**
     * Checks the native comparison family shared with the other field.
     */
    public function accepts(ForeignKeyScalarStorage|ForeignKeyIntegerDomain|ForeignKeyStringStorage $other): bool
    {
        if ($other instanceof ForeignKeyScalarStorage) {
            return $this === $other->kind || ($this->packed() && $other->kind->packed());
        }
        if ($other instanceof ForeignKeyStringStorage) {
            return $this->packed() && !$other->kind->text();
        }

        return match ($this) {
            self::Date => $other->storage === IntegerStorage::Medium && !$other->unsigned,
            self::Year => $other->storage === IntegerStorage::Tiny && $other->unsigned,
            self::Decimal, self::Bit, self::Single, self::Double, self::Time, self::DateTime, self::Timestamp => false,
        };
    }
}
