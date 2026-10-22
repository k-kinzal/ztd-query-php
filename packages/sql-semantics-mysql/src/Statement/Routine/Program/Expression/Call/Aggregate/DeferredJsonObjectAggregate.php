<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Aggregation\AggregateNesting;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
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
 * A stored JSON_OBJECTAGG retaining an intrinsic group or window nesting condition.
 *
 * @visibility public
 * @example Retaining a constant aggregate nested in an ordinary group aggregate
 *     $count = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count, []);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\Aggregate\DeferredJsonObjectAggregate($count, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredJsonObjectAggregate implements DeferredOperand
{
    use Snapshot;

    /**
     * @param Scalar $key The complete key expression
     * @param Scalar $value The complete value expression
     * @param bool $keyAll Whether ALL precedes the key
     * @param bool $valueAll Whether ALL precedes the value
     * @param Name|WindowSpecification|null $over The optional window
     */
    public function __construct(public readonly Scalar $key, public readonly Scalar $value, public readonly bool $keyAll = false, public readonly bool $valueAll = false, public readonly Name|WindowSpecification|null $over = null)
    {
        Check::input((new AggregateNesting())->problem([$key, $value], $over !== null) !== null, 'A deferred JSON aggregate retains an intrinsic nesting contradiction.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Retains dependencies without publishing an ordinary aggregate occurrence.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->key, $environment);
        $derivation->scalar($this->value, $environment);
        (new Windows())->derive($this->over, $derivation, $environment);
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return non-empty-list<Scalar> The key followed by the value
     */
    public function operands(): array
    {
        return [$this->key, $this->value];
    }

    /**
     * Returns the fixed nesting condition.
     */
    public function problem(): AggregateNestingConstraint
    {
        $problem = (new AggregateNesting())->problem($this->operands(), $this->over !== null);
        Check::invariant($problem !== null, 'A deferred JSON aggregate retains an intrinsic nesting contradiction.');
        return $problem;
    }

    /**
     * Writes both expressions, their quantifiers and the optional window.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_OBJECTAGG')->glue()->symbol('(');
        if ($this->keyAll) {
            $out->keyword('ALL');
        }
        $out->node($this->key)->symbol(',');
        if ($this->valueAll) {
            $out->keyword('ALL');
        }
        $out->node($this->value)->symbol(')');
        (new Windows())->render($this->over, $out);
    }
}
