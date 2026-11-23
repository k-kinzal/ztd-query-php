<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A named builtin column in a native table declaration.
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
        public readonly IntegerStorage|CharacterStorage $storage,
        public readonly bool $nullable = true,
        public readonly IntegerLiteral|RangeIntegerLiteral|TextLiteral|NullLiteral|null $default = null,
    ) {
        Check::input(preg_match('/\A[\x01-\x7f]{1,64}(?<! )\z/D', $name->value) === 1, 'This MySQL declaration requires an ASCII column name of 1 to 64 characters without a trailing space.');
        Check::input($nullable || !$default instanceof NullLiteral, 'A MySQL NOT NULL column cannot declare a NULL default.');
        if ($default instanceof IntegerLiteral || $default instanceof RangeIntegerLiteral) {
            Check::input($storage instanceof IntegerStorage, 'A signed default requires native integer storage.');
            Check::input($storage->accepts($default), 'The ' . $storage->value . ' default is outside the signed storage range.');
        }
        if ($default instanceof TextLiteral) {
            Check::input($storage instanceof CharacterStorage, 'A text default requires native VARCHAR storage.');
            Check::input(
                preg_match('/\A.{0,' . $storage->length . '}[\x09-\x0D\x20]*\z/sDu', $default->value) === 1,
                'A VARCHAR default cannot discard significant characters.',
            );
        }
    }

    /**
     * Returns the maximum bytes of this field and its native length prefix.
     */
    public function storageBytes(): int
    {
        if ($this->storage instanceof CharacterStorage) {
            $bytes = $this->storage->length * 4;

            return $bytes + ($bytes > 255 ? 2 : 1);
        }

        return $this->storage->bytes();
    }

    /**
     * Writes the column definition with its canonical builtin type and default.
     */
    public function toString(): string
    {
        $storage = $this->storage instanceof IntegerStorage ? $this->storage->value : $this->storage->toString();
        $definition = $this->name->quoted() . ' ' . $storage . ($this->nullable ? ' NULL' : ' NOT NULL');

        return $definition . match (true) {
            $this->default instanceof IntegerLiteral, $this->default instanceof RangeIntegerLiteral => ' DEFAULT ' . $this->default->decimal,
            $this->default instanceof TextLiteral => ' DEFAULT ' . $this->default->toString(),
            $this->default instanceof NullLiteral => ' DEFAULT NULL',
            $this->default === null => '',
        };
    }
}
