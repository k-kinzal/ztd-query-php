<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

/**
 * A resolved InnoDB string storage family.
 * @visibility public
 * @example Distinguishing a fixed binary field
 *     \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind::Binary->value // => 'BINARY'
 */
enum ForeignKeyStringKind: string
{
    case Char = 'CHAR';
    case Varchar = 'VARCHAR';
    case Binary = 'BINARY';
    case Varbinary = 'VARBINARY';

    /**
     * Determines whether the declared length is measured in characters.
     */
    public function text(): bool
    {
        return match ($this) {
            self::Char, self::Varchar => true,
            self::Binary, self::Varbinary => false,
        };
    }

    /**
     * Distinguishes the fixed stored field layout.
     */
    public function fixed(): bool
    {
        return match ($this) {
            self::Char, self::Binary => true,
            self::Varchar, self::Varbinary => false,
        };
    }
}
