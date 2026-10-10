<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A column attribute incompatible with its declared type or fractional seconds precision.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/timestamp-initialization.html.
 *
 * @visibility public
 * @example Reading the incompatible attribute
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT SRID 1)')->facts->diagnostics[0]->message() // => 'Incorrect usage of SRID and non-geometry column'
 */
final class InvalidColumnAttribute implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The column with the attribute
     * @param 'SRID'|'DEFAULT'|'ON UPDATE' $attribute The incompatible attribute
     */
    public function __construct(public readonly Name $column, public readonly string $attribute)
    {
    }

    /**
     * Describes the incompatible attribute.
     */
    public function message(): string
    {
        return match ($this->attribute) {
            'SRID' => 'Incorrect usage of SRID and non-geometry column',
            'DEFAULT' => "Invalid default value for '{$this->column->value}'",
            'ON UPDATE' => "Invalid ON UPDATE clause for '{$this->column->value}' column",
        };
    }
}
