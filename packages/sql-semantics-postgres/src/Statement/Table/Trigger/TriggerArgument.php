<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One argument passed to a trigger function.
 *
 * The server passes every argument as a string: an integer constant as its
 * decimal value, a numeric constant as its written text, a string constant
 * as its value and a word as its name (`TriggerFuncArg` in gram.y).
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility public
 * @example Reading trigger arguments
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE TRIGGER g BEFORE INSERT ON t FOR EACH ROW EXECUTE FUNCTION f(1, 2.5, 'x', word)");
 *     $statement->toString() // => "CREATE TRIGGER g BEFORE INSERT ON t FOR EACH ROW EXECUTE FUNCTION f(1, 2.5, 'x', word)"
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
