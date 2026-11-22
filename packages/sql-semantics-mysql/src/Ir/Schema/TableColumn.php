<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A named signed integer column in a native table declaration.
 *
 * @visibility public
 * @example Making a signed integer column required
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Identifier('count');
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\TableColumn($name, \SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Standard, false))->nullable // => false
 */
final class TableColumn
{
    use Snapshot;

    /**
     * Fixes the name, type, nullability and optional literal default.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly IntegerStorage $storage,
        public readonly bool $nullable = true,
        public readonly IntegerLiteral|NullLiteral|null $default = null,
    ) {
        Check::input(preg_match('/\A[\x01-\x7f]{1,64}(?<! )\z/D', $name->value) === 1, 'This MySQL declaration requires an ASCII column name of 1 to 64 characters without a trailing space.');
        Check::input($nullable || !$default instanceof NullLiteral, 'A MySQL NOT NULL column cannot declare a NULL default.');
        if ($default instanceof IntegerLiteral && $storage === IntegerStorage::Small) {
            $integer = (int) $default->decimal;
            Check::input($integer >= -32768 && $integer <= 32767, 'The SMALLINT default is outside the signed storage range.');
        }
    }

    /**
     * Writes the column definition with its canonical builtin type and default.
     */
    public function toString(): string
    {
        $definition = $this->name->quoted() . ' ' . $this->storage->value . ($this->nullable ? ' NULL' : ' NOT NULL');

        return $definition . match (true) {
            $this->default instanceof IntegerLiteral => ' DEFAULT ' . $this->default->decimal,
            $this->default instanceof NullLiteral => ' DEFAULT NULL',
            $this->default === null => '',
        };
    }
}
