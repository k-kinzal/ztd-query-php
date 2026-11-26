<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * One argument passed to a trigger function.
 *
 * The server passes every argument as a string: an integer constant as its
 * decimal value, a numeric constant as its written text, a string constant
 * as its value and a word as its name (`TriggerFuncArg` in gram.y).
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility SqlSemantics
 */
final class TriggerArgument implements Node
{
    use Snapshot;

    /**
     * @param IntegerConstant|NumericConstant|StringConstant|Name $value The argument
     */
    public function __construct(public readonly IntegerConstant|NumericConstant|StringConstant|Name $value)
    {
    }

    /**
     * Writes the argument.
     */
    public function render(Output $out): void
    {
        if ($this->value instanceof Name) {
            $out->name($this->value, NameUse::Label);
        } else {
            $out->node($this->value);
        }
    }
}
