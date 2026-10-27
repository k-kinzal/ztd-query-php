<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;

/**
 * A complete window whose partitioning, ordering or frame retains a non-scalar operand.
 *
 * Named definitions and inactive common tables retain this specification without
 * preparing its expressions. A reached window checks every item in written order.
 *
 * @visibility public
 * @example Refusing a sort direction in a deferred window partition
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row, \SqlSemantics\Platform\MySql\Statement\Query\Direction::Descending);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec(partition: [$item]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing to defer an ordinary window
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec() // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredWindowSpec implements WindowSpecification
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<OrderItem|DeferredOrderItem> The partitioning in written order
     */
    public readonly array $partition;

    /**
     * @var list<OrderItem|DeferredOrderItem> The ordering in written order
     */
    public readonly array $order;

    /**
     * @param list<OrderItem|DeferredOrderItem> $partition The complete partitioning
     * @param list<OrderItem|DeferredOrderItem> $order The complete ordering
     */
    public function __construct(public readonly ?Name $base = null, array $partition = [], array $order = [], public readonly Frame|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame|null $frame = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $clauses = [];
        foreach ([$partition, $order] as $items) {
            $clause = [];
            foreach (Check::listOf($items, \SqlSemantics\Statement\Node::class, 'Window clauses retain ordered items.') as $item) {
                Check::input($item instanceof OrderItem || $item instanceof DeferredOrderItem, 'A retained window item is ordinary or deferred.');
                $clause[] = $item;
            }
            $clauses[] = $clause;
        }
        $this->partition = $clauses[0];
        $this->order = $clauses[1];
        Check::input($frame instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\Frame\DeferredFrame || array_filter([...$this->partition, ...$this->order], static fn ($item): bool => $item instanceof DeferredOrderItem) !== [], 'A deferred window has at least one non-scalar item or boundary.');
        foreach ($this->partition as $item) {
            Check::input($item->direction === null, 'A window partition item has no sort direction.');
        }
        foreach ([...$this->partition, ...$this->order] as $item) {
            Check::input(!$item->expression instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal, 'A window ordering has expression operands, not query output positions.');
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds every dependency without treating a deferred item as an ordinary scalar.
     */
    public function deriveWindow(Derivation $derivation, Environment $environment): void
    {
        foreach ([...$this->partition, ...$this->order] as $item) {
            $derivation->scalar($item->expression, $environment);
        }
        foreach ($this->frame?->offsets() ?? [] as $offset) {
            $derivation->scalar($offset, $environment);
        }
    }

    /**
     * Answers the first deferred item or boundary, retaining preceding ordinary items.
     *
     * @throws LogicException When construction invariants have been bypassed
     */
    public function operand(): RowOperand|DeferredOperand
    {
        $items = array_values(array_filter([...$this->partition, ...$this->order], static fn ($item): bool => $item instanceof DeferredOrderItem));
        if ($items !== []) {
            return $items[0]->expression;
        }
        foreach ($this->frame?->offsets() ?? [] as $offset) {
            if ($offset instanceof RowOperand || $offset instanceof DeferredOperand) {
                return $offset;
            }
        }
        throw new LogicException('A deferred window retains a non-scalar item or boundary.');
    }

    /**
     * Writes every window clause without substituting expressions.
     */
    public function render(Output $out): void
    {
        $out->symbol('(');
        if ($this->base !== null) {
            $out->name($this->base, NameUse::Label);
        }
        if ($this->partition !== []) {
            $out->keyword('PARTITION', 'BY')->list($this->partition);
        }
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        $out->node($this->frame)->symbol(')');
    }
}
