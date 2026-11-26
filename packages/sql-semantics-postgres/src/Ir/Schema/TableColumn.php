<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
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
     * Fixes the name, type, nullability and the declared default and collation.
     */
    public function __construct(
        public readonly Identifier $name,
        public readonly IntegerStorage|CharacterStorage|BooleanStorage $storage,
        public readonly bool $nullable = true,
        public readonly IntegerLiteral|WideIntegerLiteral|TextLiteral|BooleanLiteral|NullLiteral|null $default = null,
        public readonly ?TextCollation $collation = null,
    ) {
        Check::input($collation === null || $storage === CharacterStorage::Text, 'A text collation requires native text storage.');
        $systemNames = ['tableoid' => true, 'xmin' => true, 'cmin' => true, 'xmax' => true, 'cmax' => true, 'ctid' => true];
        Check::input(!isset($systemNames[$name->value]), 'A PostgreSQL table cannot declare a system column name.');
        Check::input(!$default instanceof TextLiteral || $storage === CharacterStorage::Text, 'A text default requires native text storage.');
        Check::input(!$default instanceof BooleanLiteral || $storage === BooleanStorage::Boolean, 'A Boolean default requires native Boolean storage.');
        Check::input(
            $storage !== BooleanStorage::Boolean || $default === null || $default instanceof BooleanLiteral || $default instanceof NullLiteral,
            'The Boolean column requires a Boolean or NULL default.'
        );
    }

    /**
     * Writes the column definition with its canonical builtin type and default.
     */
    public function toString(): string
    {
        $definition = $this->name->quoted() . ' ' . $this->storage->value
            . ($this->collation === null ? '' : ' COLLATE pg_catalog."' . $this->collation->value . '"')
            . ($this->nullable ? ' NULL' : ' NOT NULL');

        return $definition . match (true) {
            $this->default instanceof IntegerLiteral => ' DEFAULT ' . $this->default->decimal,
            $this->default instanceof WideIntegerLiteral => ' DEFAULT CAST(' . $this->default->decimal . ' AS pg_catalog.int8)',
            $this->default instanceof TextLiteral => ' DEFAULT ' . $this->default->toString(),
            $this->default instanceof BooleanLiteral => $this->default->value ? ' DEFAULT TRUE' : ' DEFAULT FALSE',
            $this->default instanceof NullLiteral => ' DEFAULT NULL',
            $this->default === null => '',
        };
    }
}
