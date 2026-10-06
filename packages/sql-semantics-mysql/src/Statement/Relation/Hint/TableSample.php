<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The TABLESAMPLE clause of a table reference (MySQL 9.x grammars): the sampling method and the percentage.
 *
 * The percentage is a number, a user variable or a parameter marker. The
 * statement that holds the table derives it; it sees no column.
 * Source: https://dev.mysql.com/doc/refman/9.1/en/select.html.
 *
 * @visibility public
 * @example Reading a sampling clause
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-9.1.0'))->analyze('SELECT a FROM t TABLESAMPLE BERNOULLI (10)');
 *     [$query->statement->from->sample->method, $query->statement->from->sample->percentage->text] // => [\SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod::Bernoulli, '10']
 * @example Refusing an expression as the percentage
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample(\SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod::System, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class TableSample implements Node
{
    use Snapshot;

    /**
     * @param SamplingMethod $method The sampling method
     * @param Scalar $percentage The percentage of rows to sample
     */
    public function __construct(public readonly SamplingMethod $method, public readonly Scalar $percentage)
    {
        Check::input($percentage instanceof NumberLiteral || $percentage instanceof UserVariable || $percentage instanceof Parameter, 'A sampling percentage is a number, a user variable or a parameter marker.');
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('TABLESAMPLE', $this->method->value)->symbol('(')->node($this->percentage)->symbol(')');
    }
}
