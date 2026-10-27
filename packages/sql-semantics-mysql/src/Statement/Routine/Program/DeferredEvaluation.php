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

/**
 * A stored DO instruction retaining wildcard or non-scalar preparation until invocation.
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
 *     $create->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation // => true
 */
final class DeferredEvaluation implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<SelectExpression|DeferredProjection|Star|TableWildcard> The program instruction's items
     */
    public readonly array $items;

    /**
     * @param list<SelectExpression|DeferredProjection|Star|TableWildcard> $items Items including a wildcard or non-scalar whose check is deferred
     */
    public function __construct(array $items, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $list = [];
        foreach (Check::listOf($items, \SqlSemantics\Statement\Node::class, 'A deferred DO has at least one item.', 1) as $position => $item) {
            Check::input($item instanceof SelectExpression || $item instanceof DeferredProjection || $item instanceof TableWildcard || ($item instanceof Star && $position === 0), 'A select list item is an expression or a qualified star, and an unqualified star comes first.');
            $list[] = $item;
        }
        $this->items = $list;
        Check::input(array_filter($this->items, static fn ($item): bool => !$item instanceof SelectExpression) !== [], 'A deferred DO retains a wildcard or non-scalar; ordinary expressions use Evaluation.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the retained operands in the enclosing program scope.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($this->items as $item) {
            if ($item instanceof DeferredProjection) {
                $derivation->scalar($item->expression, $scope->environment);
            } else {
                (new Projection())->items([$item], $derivation, $scope->environment, new JoinedInput(new RelationFact(new RowShape([])), [], []));
            }
        }
    }

    /**
     * Answers the retained condition; operand dependencies still require preparation.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        foreach ($this->items as $item) {
            if ($item instanceof Star || $item instanceof TableWildcard) {
                return new EvaluationWildcard($item instanceof TableWildcard ? $item->table : null);
            }
        }
        foreach ($this->items as $item) {
            if ($item instanceof DeferredProjection) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$item->expression]);
            }
        }
        throw new LogicException('The deferred DO constructor requires a wildcard or non-scalar.');
    }

    /**
     * Writes the stored instruction with every operand preserved.
     */
    public function render(Output $out): void
    {
        $out->keyword('DO')->list($this->items);
    }
}
