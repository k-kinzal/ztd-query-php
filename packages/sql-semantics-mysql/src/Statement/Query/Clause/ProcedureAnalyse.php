<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

/**
 * The `PROCEDURE ANALYSE (max_elements, max_memory)` clause of the 5.x grammars.
 *
 * It asks the server to return a description of the result columns instead
 * of the rows; the arguments are optional unsigned integers. The 8.0 server
 * removed it. Source: https://dev.mysql.com/doc/refman/5.7/en/procedure-analyse.html.
 *
 * @visibility SqlSemantics
 * @example Refusing a third argument
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1');
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse([$one, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3')]) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class ProcedureAnalyse implements Node
{
    use Snapshot;

    /**
     * @var list<NumberLiteral> The maximum number of distinct values and the maximum memory, as written
     */
    public readonly array $arguments;

    /**
     * @param list<NumberLiteral> $arguments At most two unsigned integers
     */
    public function __construct(array $arguments = [])
    {
        $this->arguments = Check::listOf($arguments, NumberLiteral::class, 'PROCEDURE ANALYSE takes numbers.');
        Check::input(count($arguments) <= 2, 'PROCEDURE ANALYSE takes at most two arguments.');
        foreach ($this->arguments as $argument) {
            Check::input($argument->form === NumberForm::Integer, 'A PROCEDURE ANALYSE argument is an unsigned integer.');
        }
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('PROCEDURE', 'ANALYSE')->glue()->symbol('(')->list($this->arguments)->symbol(')');
    }
}
