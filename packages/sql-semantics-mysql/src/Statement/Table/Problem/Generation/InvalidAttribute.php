<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An attribute or declared type that cannot accompany a generation expression.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html.
 *
 * @visibility public
 * @example Describing an incompatible generation default
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\InvalidAttribute(new \SqlSemantics\Statement\Identifier\Name('g'), 'DEFAULT'))->message() // => 'Incorrect usage of DEFAULT and generated column'
 */
final class InvalidAttribute implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $column The owning column
     * @param string $attribute The incompatible attribute or SERIAL type
     */
    public function __construct(public readonly Name $column, public readonly string $attribute)
    {
        \SqlSemantics\Diagnostic\Check::input(in_array($attribute, ['DEFAULT', 'ON UPDATE', 'AUTO_INCREMENT', 'SERIAL DEFAULT VALUE', 'COLUMN_FORMAT', 'STORAGE', 'SERIAL'], true), 'A generated-column attribute condition names its incompatible SQL role.');
    }

    /**
     * Describes the incompatible generation attribute.
     */
    public function message(): string
    {
        return 'Incorrect usage of ' . $this->attribute . ' and generated column';
    }
}
