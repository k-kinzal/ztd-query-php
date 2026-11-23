<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A named builtin column in a native table declaration.
 *
 * @visibility public
 * @example Making a signed integer column required
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('count');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableColumn($name, \SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage::Standard, false))->nullable // => false
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
        public readonly IntegerLiteral|TextLiteral|NullLiteral|null $default = null,
    ) {
        $systemNames = ['tableoid' => true, 'xmin' => true, 'cmin' => true, 'xmax' => true, 'cmax' => true, 'ctid' => true];
        Check::input(!isset($systemNames[$name->value]), 'A PostgreSQL table cannot declare a system column name.');
        Check::input(!$default instanceof TextLiteral || $storage === CharacterStorage::Text, 'A text default requires native text storage.');
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
            $this->default instanceof TextLiteral => ' DEFAULT ' . $this->default->toString(),
            $this->default instanceof NullLiteral => ' DEFAULT NULL',
            $this->default === null => '',
        };
    }
}
