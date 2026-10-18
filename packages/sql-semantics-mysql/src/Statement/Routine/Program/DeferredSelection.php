<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored SELECT retaining items, input, filters, grouping, ordering and limits before invocation.
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
     * @var list<SelectOption> The complete ordered modifiers
     */
    public readonly array $options;

    /**
     * @var list<OrderItem|DeferredOrderItem> The complete ordering
     */
    public readonly array $orderBy;

    /**
     * @var list<\SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition> Named windows belonging to this selection
     */
    public readonly array $windows;

    /**
     * @param list<SelectOption> $options The modifiers
     * @param Grouping|DeferredGrouping|null $groupBy The complete grouping clause
     * @param Scalar|RowOperand|DeferredOperand|null $having The group predicate
     * @param list<OrderItem|DeferredOrderItem> $orderBy The complete ordering
     * @param Limit|null $limit The row limit
     * @param list<Node> $items The ordered projections, including any invocation-time operands
     * @param TableSource|Dual|null $from The complete input relation
     * @param Scalar|RowOperand|DeferredOperand|null $where The row predicate
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition> $windows The complete WINDOW clause
     * @param \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse|null $procedure The legacy result-analysis clause
     */
    public function __construct(array $items, public readonly TableSource|Dual|null $from = null, public readonly Scalar|RowOperand|DeferredOperand|null $where = null, array $options = [], public readonly Grouping|DeferredGrouping|null $groupBy = null, public readonly Scalar|RowOperand|DeferredOperand|null $having = null, array $orderBy = [], public readonly ?Limit $limit = null, array $windows = [], public readonly ?\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse $procedure = null)
    {
        $this->windows = Check::listOf($windows, \SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition::class, 'A stored selection retains its named window definitions.');
        $this->options = Check::listOf($options, SelectOption::class, 'A stored selection retains SELECT modifiers.');
        $ordering = [];
        foreach (Check::listOf($orderBy, Node::class, 'A stored ordering retains ordering items.') as $item) {
            Check::input($item instanceof OrderItem || $item instanceof DeferredOrderItem, 'A stored ordering item retains its scalar, ordinal or deferred role.');
            $ordering[] = $item;
        }
        $this->orderBy = $ordering;
        (new \SqlSemantics\Platform\MySql\Rules\Query\SortScopes())->check(array_values(array_filter([...$ordering, ...($groupBy->items ?? [])], static fn ($item): bool => $item instanceof OrderItem)));
        $list = [];
        $deferred = $where instanceof RowOperand || $where instanceof DeferredOperand || $having instanceof RowOperand || $having instanceof DeferredOperand || $groupBy instanceof DeferredGrouping || array_filter($ordering, static fn ($item): bool => $item instanceof DeferredOrderItem) !== [];
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
     * @return non-empty-list<RowOperand|DeferredOperand> Deferred operands in preparation order
     */
    public function deferred(): array
    {
        $operands = [];
        foreach ($this->items as $item) {
            if ($item instanceof DeferredProjection) {
                $operands[] = $item->expression;
            }
        }
        if ($this->where instanceof RowOperand || $this->where instanceof DeferredOperand) {
            $operands[] = $this->where;
        }
        foreach ($this->groupBy->items ?? [] as $item) {
            if ($item instanceof DeferredOrderItem) {
                $operands[] = $item->expression;
            }
        }
        if ($this->having instanceof RowOperand || $this->having instanceof DeferredOperand) {
            $operands[] = $this->having;
        }
        foreach ($this->orderBy as $item) {
            if ($item instanceof DeferredOrderItem) {
                $operands[] = $item->expression;
            }
        }
        Check::invariant($operands !== [], 'A deferred selection retains at least one preparation condition.');
        return $operands;
    }

    /**
     * Binds retained dependencies in the program environment without projecting a row as a scalar.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Routine\Selection\SelectionFacts())->derive($this, $derivation, $scope);
    }

    /**
     * Writes the complete stored selection.
     */
    public function render(Output $out): void
    {
        $out->keyword('SELECT');
        foreach ($this->options as $option) {
            $out->keyword($option->value);
        }
        $out->list($this->items);
        if ($this->from !== null) {
            $out->keyword('FROM')->node($this->from);
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        $out->node($this->groupBy);
        if ($this->having !== null) {
            $out->keyword('HAVING')->node($this->having);
        }
        if ($this->windows !== []) {
            $out->keyword('WINDOW')->list($this->windows);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit)->node($this->procedure);
    }
}
