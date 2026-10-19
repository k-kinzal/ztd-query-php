<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Result\SetOutput;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * A query submitted as a statement, separately from a query used as an operand.
 *
 * Query bodies also occur in subqueries and INSERT sources, whose grammar
 * positions admit different forms. Only this wrapper implements the statement
 * role. Its checks concern that grammatical position and fixed output columns,
 * independently of catalog resolution or execution eligibility. Reached recursive
 * definitions require an anchor and direct, single self references in each
 * recursive block. Unused definitions remain templates.
 *
 * @visibility public
 * @example Refusing a statement without read columns
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a tableless star in a read statement
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing inconsistent VALUES widths in a read statement
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]);
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([$one, new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing INSERT defaults in a read statement
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest()])])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing inconsistent fixed set widths in a read statement
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     $two = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')])]);
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation($one, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $two)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an undefined name in a completed window namespace
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber, [], new \SqlSemantics\Statement\Identifier\Name('w'));
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($call)])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing duplicate window names independently of database state
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$item], windows: [$window, $window])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an unused cyclic window definition in a completed read
 *     $name = new \SqlSemantics\Statement\Identifier\Name('w');
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition($name, new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec($name));
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$item], windows: [$window])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Reading the body of a SELECT statement
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1');
 *     $operation->statement->query::class // => \SqlSemantics\Platform\MySql\Statement\Query\Select::class
 */
final class QueryRoot implements Explainable, \SqlSemantics\Statement\Selection
{
    use Snapshot;

    /**
     * @param Query $query The query in the statement grammar position
     */
    public function __construct(public readonly Query $query)
    {
        (new StatementQuery())->check($query);
        \SqlSemantics\Diagnostic\Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query) === null, 'A read statement has coherent fixed output columns.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the query and its externally returned fields.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output((new SetOutput())->derive($this->query, $derivation));
    }

    /**
     * Answers the input relation of the query body, when it has one.
     */
    public function input(): ?\SqlSemantics\Statement\Relation
    {
        return $this->query instanceof \SqlSemantics\Statement\Selection ? $this->query->input() : null;
    }

    /**
     * Writes the query in its statement position.
     */
    public function render(Output $out): void
    {
        $out->node($this->query);
    }
}
