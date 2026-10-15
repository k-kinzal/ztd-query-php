<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored DO instruction whose wildcard expansion is deferred until invocation.
 *
 * MySQL accepts this program definition, then raises the wildcard error when
 * control reaches it. This is a program member, never a top-level statement.
 * The original expression operands remain available for definition-time binding
 * and notices; no source text or parser tree is retained.
 *
 * @visibility public
 * @example Refusing to defer ordinary expressions
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral())]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Keeping a deferred wildcard in a stored procedure
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() DO *');
 *     $create->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation // => true
 */
final class DeferredEvaluation implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<SelectExpression|Star|TableWildcard> The program instruction's items
     */
    public readonly array $items;

    /**
     * @param list<SelectExpression|Star|TableWildcard> $items Items including a wildcard whose check is deferred
     */
    public function __construct(array $items)
    {
        $this->items = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SelectItems())->checked($items, 'A deferred DO has at least one item.');
        Check::input(array_filter($this->items, static fn ($item): bool => !$item instanceof SelectExpression) !== [], 'A deferred DO contains a wildcard; ordinary expressions use Evaluation.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the retained operands in the enclosing program scope.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new Projection())->items($this->items, $derivation, $scope->environment, new JoinedInput(new RelationFact(new RowShape([])), [], []));
    }

    /**
     * Answers the first wildcard expansion that fails when the instruction is reached.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): EvaluationWildcard
    {
        foreach ($this->items as $item) {
            if (!$item instanceof SelectExpression) {
                return new EvaluationWildcard($item instanceof TableWildcard ? $item->table : null);
            }
        }
        throw new LogicException('The deferred DO constructor requires a wildcard.');
    }

    /**
     * Writes the stored instruction with every operand preserved.
     */
    public function render(Output $out): void
    {
        $out->keyword('DO')->list($this->items);
    }
}
