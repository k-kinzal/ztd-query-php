<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ErrorCode;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * DECLARE ... CONDITION: a name for an error code or an SQLSTATE value.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-condition.html.
 *
 * @visibility public
 * @example Reading a named condition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() BEGIN DECLARE gone CONDITION FOR SQLSTATE '42S02'; END");
 *     $declaration = $create->statement->body->declarations[0];
 *     [$declaration->name->value, $declaration->value->state->value] // => ['gone', '42S02']
 */
final class ConditionDeclaration implements Declaration
{
    use Snapshot;

    /**
     * @param Name $name The condition name
     * @param ErrorCode|SqlState $value The error code or SQLSTATE value the name stands for
     */
    public function __construct(public readonly Name $name, public readonly ErrorCode|SqlState $value)
    {
    }

    /**
     * Writes the declaration.
     */
    public function render(Output $out): void
    {
        $out->keyword('DECLARE')->name($this->name, NameUse::Identifier)->keyword('CONDITION', 'FOR')->node($this->value);
    }
}
