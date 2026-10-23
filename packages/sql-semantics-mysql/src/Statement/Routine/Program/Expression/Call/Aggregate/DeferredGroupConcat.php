<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Aggregation\AggregateNesting;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored GROUP_CONCAT with a fixed group or window nesting contradiction.
 *
 * @visibility public
 * @example Retaining a nested window outside the ordinary scalar role
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Rank, [], new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec());
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredGroupConcat([$call]) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredGroupConcat implements DeferredOperand
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The complete argument list
     */
    public readonly array $arguments;

    /**
     * @var list<OrderItem> The retained ordering
     */
    public readonly array $order;

    /**
     * @param list<Scalar> $arguments The arguments
     * @param bool $distinct Whether DISTINCT is written
     * @param list<OrderItem> $order The concatenation ordering
     * @param Text|null $separator The explicit separator
     * @param Name|WindowSpecification|null $over The optional window
     */
    public function __construct(array $arguments, public readonly bool $distinct = false, array $order = [], public readonly ?Text $separator = null, public readonly Name|WindowSpecification|null $over = null)
    {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'GROUP_CONCAT takes at least one expression.', 1);
        $this->order = Check::listOf($order, OrderItem::class, 'A concatenation ordering contains ordering items.');
        foreach ($this->order as $item) {
            Check::input($item->expression instanceof Scalar, 'Concatenation ordering contains scalar expressions.');
        }
        Check::input((new AggregateNesting())->problem($this->operands()) !== null, 'A deferred concatenation retains an intrinsic nesting contradiction.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives retained dependencies without publishing an ordinary aggregate.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->operands() as $operand) {
            $derivation->scalar($operand, $environment);
        }
        (new Windows())->derive($this->over, $derivation, $environment);
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return non-empty-list<Scalar> The arguments and ordering dependencies
     */
    public function operands(): array
    {
        $operands = $this->arguments;
        foreach ($this->order as $item) {
            Check::invariant($item->expression instanceof Scalar, 'Concatenation ordering contains scalar expressions.');
            $operands[] = $item->expression;
        }
        return $operands;
    }

    /**
     * Returns the fixed nesting contradiction.
     */
    public function problem(): AggregateNestingConstraint
    {
        $problem = (new AggregateNesting())->problem($this->operands());
        Check::invariant($problem !== null, 'A deferred concatenation retains an intrinsic nesting contradiction.');
        return $problem;
    }

    /**
     * Writes the complete aggregate and its modifiers.
     */
    public function render(Output $out): void
    {
        $out->keyword('GROUP_CONCAT')->glue()->symbol('(');
        if ($this->distinct) {
            $out->keyword('DISTINCT');
        }
        $out->list($this->arguments);
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        if ($this->separator !== null) {
            $out->keyword('SEPARATOR')->node($this->separator);
        }
        $out->symbol(')');
        (new Windows())->render($this->over, $out);
    }
}
