<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\From\FromScope;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored SELECT of items and an optional input/filter needing deferred preparation.
 *
 * A definition can retain row-valued projections and predicates. Its relation
 * and all operand names bind when control reaches the instruction. This program
 * member is distinct from an ordinary Query and preserves no source text.
 *
 * @visibility public
 * @example Refusing an ordinary selection in the deferred role
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral())]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredSelection implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<SelectExpression|DeferredProjection|Star|TableWildcard> The complete projection
     */
    public readonly array $items;

    /**
     * @param list<Node> $items The ordered projections, including any invocation-time operands
     * @param TableSource|Dual|null $from The complete input relation
     * @param Scalar|RowOperand|DeferredOperand|null $where The row predicate
     */
    public function __construct(array $items, public readonly TableSource|Dual|null $from = null, public readonly Scalar|RowOperand|DeferredOperand|null $where = null)
    {
        $list = [];
        $deferred = $where instanceof RowOperand || $where instanceof DeferredOperand;
        foreach (Check::listOf($items, Node::class, 'A stored selection has at least one item.', 1) as $position => $item) {
            Check::input($item instanceof SelectExpression || $item instanceof DeferredProjection || $item instanceof TableWildcard || ($item instanceof Star && $position === 0), 'A projection is an expression or qualified star; a bare star occurs only first.');
            $list[] = $item;
            $deferred = $deferred || $item instanceof DeferredProjection;
        }
        Check::input($deferred, 'A deferred selection retains a projection or predicate requiring invocation-time preparation.');
        $this->items = $list;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds retained dependencies in the program environment without projecting a row as a scalar.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $from = $this->from === null ? new JoinedInput(new RelationFact(new RowShape([])), [], []) : (new FromScope())->open($this->from, $derivation, $scope->environment, []);
        (new FromScope())->unique($from->visible, $derivation);
        $environment = new Environment($derivation->context, $scope->environment, $from->visible, aggregatesAllowed: true);
        foreach ($this->items as $item) {
            if ($item instanceof DeferredProjection) {
                $derivation->scalar($item->expression, $environment);
            } else {
                (new Projection())->items([$item], $derivation, $environment, $from);
            }
        }
        if ($this->where !== null) {
            $derivation->scalar($this->where, new Environment($derivation->context, $scope->environment, $from->visible));
        }
    }

    /**
     * Writes the complete stored selection.
     */
    public function render(Output $out): void
    {
        $out->keyword('SELECT')->list($this->items);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
