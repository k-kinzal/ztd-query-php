<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A TIMESTAMPADD or TIMESTAMPDIFF call retaining both operands and their deferred condition.
 *
 * The complete call remains available when its stored instruction is reached.
 *
 * @visibility public
 * @example Refusing to defer an ordinary scalar call
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredTimestamp(\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation::Add, \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredTimestamp implements DeferredOperand
{
    use Snapshot;

    /**
     * @param TimestampOperation $operation The timestamp function
     * @param IntervalUnit $unit The simple interval unit
     * @param Scalar|RowOperand|DeferredOperand $first The quantity or start
     * @param Scalar|RowOperand|DeferredOperand $second The date or end
     */
    public function __construct(public readonly TimestampOperation $operation, public readonly IntervalUnit $unit, public readonly Scalar|RowOperand|DeferredOperand $first, public readonly Scalar|RowOperand|DeferredOperand $second)
    {
        Check::input(!$first instanceof Scalar || !$second instanceof Scalar, 'A deferred timestamp call retains a non-scalar operand.');
        Check::input(in_array($unit, [IntervalUnit::Microsecond, IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::Day, IntervalUnit::Week, IntervalUnit::Month, IntervalUnit::Quarter, IntervalUnit::Year], true), 'TIMESTAMPADD and TIMESTAMPDIFF take a simple interval unit.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both dependencies without invoking the retained condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->first, $environment);
        $quantity = $derivation->scalar($this->second, $environment);
        return new ScalarFact(new Invalid($this->problem()), $operand->nullability->propagate($quantity->nullability));
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Both inputs in preparation order; addition resolves its date before its quantity
     */
    public function operands(): array
    {
        return $this->operation === TimestampOperation::Add ? [$this->second, $this->first] : [$this->first, $this->second];
    }

    /**
     * Answers the first retained operand condition.
     */
    public function problem(): Diagnostic
    {
        return (new DeferredArguments())->problem($this->operands());
    }

    /**
     * Writes the retained operation with its original operand roles.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->operation->value)->glue()->symbol('(')->keyword($this->unit->value)->symbol(',')->node($this->first)->symbol(',')->node($this->second)->symbol(')');
    }
}
