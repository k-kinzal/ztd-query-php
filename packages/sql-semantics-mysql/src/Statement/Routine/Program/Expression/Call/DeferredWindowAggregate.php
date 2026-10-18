<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A retained aggregate invocation with a fixed modifier or window constraint.
 *
 * The arguments, modifiers and sole OVER clause are retained directly. This
 * value is not a Scalar and does not assign aggregate ownership
 * to its enclosing query while deriving a deferred preparation condition.
 *
 * @visibility public
 * @example Refusing an ordinary window in a deferred invocation
 *     $aggregate = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count, []);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowAggregate($aggregate, new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredWindowAggregate implements DeferredOperand
{
    use Snapshot;

    /**
     * @var AggregateFunction|DeferredAggregateKind The aggregate operation
     */
    public readonly AggregateFunction|DeferredAggregateKind $function;

    /**
     * @var list<Scalar> The original arguments in order
     */
    public readonly array $arguments;

    /**
     * @var bool Whether DISTINCT is written
     */
    public readonly bool $distinct;

    /**
     * @var bool Whether ALL precedes the first argument or star
     */
    public readonly bool $all;

    /**
     * @var bool Whether ALL precedes the JSON value argument
     */
    public readonly bool $valueAll;

    /**
     * @var list<OrderItem> Concatenation ordering dependencies
     */
    public readonly array $order;

    /**
     * @var Text|null The concatenation separator
     */
    public readonly ?Text $separator;

    /**
     * @param Aggregate|GroupConcat|JsonObjectAggregate $aggregate The aggregate without an OVER clause
     * @param Name|WindowSpecification $over The original window reference or specification
     */
    public function __construct(Aggregate|GroupConcat|JsonObjectAggregate $aggregate, public readonly Name|WindowSpecification $over)
    {
        Check::input($aggregate->over === null, 'A retained aggregate has one OVER clause.');
        $this->function = $aggregate instanceof Aggregate ? $aggregate->function : ($aggregate instanceof GroupConcat ? DeferredAggregateKind::GroupConcat : DeferredAggregateKind::JsonObject);
        $this->arguments = $aggregate instanceof JsonObjectAggregate ? [$aggregate->key, $aggregate->value] : $aggregate->arguments;
        $this->distinct = $aggregate instanceof JsonObjectAggregate ? false : $aggregate->distinct;
        $this->all = $aggregate instanceof Aggregate ? $aggregate->all : ($aggregate instanceof JsonObjectAggregate && $aggregate->keyAll);
        $this->valueAll = $aggregate instanceof JsonObjectAggregate && $aggregate->valueAll;
        $this->order = $aggregate instanceof GroupConcat ? $aggregate->order : [];
        $this->separator = $aggregate instanceof GroupConcat ? $aggregate->separator : null;
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Call\Window\Aggregates())->limitation($this->function, $this->distinct) !== null || (new Constraints())->problem($over) !== null, 'A deferred aggregate retains a fixed invocation or window constraint.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives every operand without treating a deferred invocation as an ordinary aggregate.
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
     * @return list<Scalar> The arguments and any concatenation ordering dependencies
     */
    public function operands(): array
    {
        $ordering = array_map(static function (OrderItem $item): Scalar {
            Check::invariant($item->expression instanceof Scalar, 'Concatenation ordering retains scalar expressions.');
            return $item->expression;
        }, $this->order);
        return [...$this->arguments, ...$ordering];
    }

    /**
     * Answers the invocation modifier or specification condition.
     */
    public function problem(): Diagnostic
    {
        $limit = (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Aggregates())->limitation($this->function, $this->distinct);
        if ($limit !== null) {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing($limit, $this);
        }
        $problem = (new Constraints())->problem($this->over);
        Check::invariant($problem !== null, 'A deferred aggregate retains its window constraint.');
        return $problem;
    }

    /**
     * Writes the aggregate and its sole window reference.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->function->value)->glue()->symbol('(');
        if ($this->distinct) {
            $out->keyword('DISTINCT');
        }
        if ($this->all) {
            $out->keyword('ALL');
        }
        if ($this->arguments === []) {
            $out->symbol('*');
        }
        foreach ($this->arguments as $index => $argument) {
            if ($index > 0) {
                $out->symbol(',');
                if ($this->valueAll) {
                    $out->keyword('ALL');
                }
            }
            $out->node($argument);
        }
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
