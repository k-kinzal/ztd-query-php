<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\Result\SetOutput;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;

/**
 * A query submitted as a statement, separately from a query used as an operand.
 *
 * Query bodies also occur in subqueries and INSERT sources, whose grammar
 * positions admit different forms. Only this wrapper implements the statement
 * role. Constructor checks concern that grammatical position. Fixed output
 * constraints remain explicit execution conditions, alongside catalog-dependent
 * conditions. Preparation resolves their native order without recovering SQL.
 * Unused definitions remain templates.
 *
 * @visibility public
 * @example Retaining a statement without read columns
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])])))->hasExecutionCondition() // => true
 * @example Retaining a tableless star in a read statement
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()])))->hasExecutionCondition() // => true
 * @example Retaining inconsistent VALUES widths in a read statement
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([$one, new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])])))->hasExecutionCondition() // => true
 * @example Retaining INSERT defaults in a read statement
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest()])])))->hasExecutionCondition() // => true
 * @example Retaining inconsistent fixed set widths in a read statement
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     $two = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')])]);
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation($one, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $two)))->hasExecutionCondition() // => true
 * @example Retaining an undefined name in a completed window namespace
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::RowNumber, [], new \SqlSemantics\Statement\Identifier\Name('w'));
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($call)])))->hasExecutionCondition() // => true
 * @example Retaining duplicate window names independently of database state
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$item], windows: [$window, new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec())])))->hasExecutionCondition() // => true
 * @example Retaining an unused cyclic window definition in a completed read
 *     $name = new \SqlSemantics\Statement\Identifier\Name('w');
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition($name, new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec($name));
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'));
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [$item], windows: [$window])))->hasExecutionCondition() // => true
 * @example Reading the body of a SELECT statement
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1');
 *     $operation->query::class // => \SqlSemantics\Platform\MySql\Statement\Query\Select::class
 * @example Retaining partitioning on an inherited window in a completed read
 *     $window = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     $child = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('v'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(new \SqlSemantics\Statement\Identifier\Name('w'), partition: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1.1'))]));
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], windows: [$window, $child]);
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query))->hasExecutionCondition() // => true
 * @example Retaining repeated ordering through a window ancestor
 *     $base = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(order: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1.1'))]));
 *     $child = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('v'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(new \SqlSemantics\Statement\Identifier\Name('w'), order: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2.2'))]));
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], windows: [$base, $child]);
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query))->hasExecutionCondition() // => true
 * @example Retaining inheritance from an explicitly framed window
 *     $frame = new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow));
 *     $base = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(frame: $frame));
 *     $child = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('v'), new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec(new \SqlSemantics\Statement\Identifier\Name('w')));
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))], windows: [$base, $child]);
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query))->hasExecutionCondition() // => true
 * @example Retaining repeated JSON_TABLE output names in a completed read
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Relation\JsonTable(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['$']), [new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('b')), new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('B'))], new \SqlSemantics\Statement\Identifier\Name('j'));
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], $table)))->hasExecutionCondition() // => true
 */
final class QueryRoot extends SqlStatement implements Explainable, \SqlSemantics\Statement\Selection, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Query $query The query in the statement grammar position
     */
    public function __construct(public readonly Query $query, ?StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new StatementQuery())->check($query);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the query and its externally returned fields.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->output((new SetOutput())->derive($this->query, $derivation));
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($this->query);
        if ($problem === null) {
            return;
        }
        foreach ($derivation->diagnostics() as $reported) {
            if ($reported::class === $problem::class && get_object_vars($reported) === get_object_vars($problem)) {
                return;
            }
        }
        $derivation->report($problem);
    }

    /**
     * Reports a fixed read condition whose precedence is resolved during preparation.
     */
    public function hasExecutionCondition(): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($this->query) !== null;
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
