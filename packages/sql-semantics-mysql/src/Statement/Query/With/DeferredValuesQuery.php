<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\With;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Result\ResultSlots;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * A VALUES query template with a retained operand preparation condition.
 *
 * QueryRoot, CTE and stored instruction roles retain every row, including rows
 * before and after the first deferred operand. Reading checks the condition.
 *
 * @visibility public
 * @example Refusing a template without a deferred row
 *     new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Retaining a reached template with its execution condition
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow([$row])]);
 *     (new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query))->hasExecutionCondition() // => true
 */
final class DeferredValuesQuery implements Query
{
    use \SqlSemantics\Statement\Semantics\QuerySemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<ValueRow|DeferredValueRow> The complete ordered rows
     */
    public readonly array $rows;

    /**
     * @param list<ValueRow|DeferredValueRow> $rows At least one deferred row
     */
    public function __construct(array $rows, ?QueryFact $queryInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($queryInformation !== null) {
            $this->initializeQueryInformation($queryInformation);
        }
        $items = [];
        $deferred = false;
        foreach (Check::listOf($rows, Node::class, 'VALUES retains at least one row.', 1) as $row) {
            Check::input($row instanceof ValueRow || $row instanceof DeferredValueRow, 'VALUES retains ordinary or deferred rows.');
            $items[] = $row;
            $deferred = $deferred || $row instanceof DeferredValueRow;
        }
        Check::input($deferred, 'A deferred VALUES query retains a deferred row.');
        $this->rows = $items;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds every row and retains invalid output facts without replacing values.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new ResultSlots())->values($this, $derivation, $outer);
    }

    /**
     * Describes the first intrinsic condition in row order.
     */
    public function problem(): Diagnostic
    {
        $problem = (new ReadColumns())->rows($this);
        Check::invariant($problem !== null, 'Deferred VALUES retains an operand preparation condition.');
        return $problem;
    }

    /**
     * Writes every retained row.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->list($this->rows);
    }
}
