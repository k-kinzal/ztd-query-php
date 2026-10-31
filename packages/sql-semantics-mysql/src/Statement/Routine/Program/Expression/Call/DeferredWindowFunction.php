<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored window invocation retaining an argument, option, count or frame condition.
 *
 * Function identity, window, options, counts and all argument roles survive
 * until the program reaches the instruction. This is not a Scalar.
 *
 * @visibility public
 * @example Refusing an ordinary window call in a deferred role
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::FirstValue, [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')], new \SqlSemantics\Statement\Identifier\Name('w')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredWindowFunction implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Scalar|RoutineVariable|RowOperand|DeferredOperand> The complete arguments
     */
    public readonly array $arguments;

    /**
     * @param list<Scalar|RoutineVariable|RowOperand|DeferredOperand> $arguments Arguments retaining at least one invocation-time condition
     */
    public function __construct(public readonly WindowFunctionKind $kind, array $arguments, public readonly Name|WindowSpecification $over, public readonly ?NullTreatment $nulls = null, public readonly ?CountingEdge $edge = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $values = (new Arguments())->checked($kind, $arguments, $nulls, $edge);
        Check::input((array_filter($values, static fn ($value): bool => $value instanceof RowOperand || $value instanceof DeferredOperand) !== [] || (new Arguments())->limitation($nulls, $edge) !== null || (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Counts())->invalid($kind, $values) || (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($over) !== null), 'A deferred window call retains an argument, option, count or frame preparation condition.');
        $this->arguments = $values;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds every argument and window dependency without executing the instruction.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument, $environment);
        }
        (new Windows())->derive($this->over, $derivation, $environment);
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return list<Scalar|RoutineVariable|RowOperand|DeferredOperand> Arguments in preparation order
     */
    public function operands(): array
    {
        return $this->arguments;
    }

    /**
     * Answers the first retained argument constraint, before window option checks.
     */
    public function problem(): Diagnostic
    {
        if (array_filter($this->arguments, static fn ($argument): bool => $argument instanceof RowOperand || $argument instanceof DeferredOperand) !== []) {
            Check::invariant($this->arguments !== [], 'A retained argument condition has dependencies.');
            return (new DeferredArguments())->problem($this->arguments);
        }
        $limit = (new Arguments())->limitation($this->nulls, $this->edge);
        if ($limit !== null) {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing($limit, $this);
        }
        return (new \SqlSemantics\Platform\MySql\Rules\Call\Window\Constraints())->problem($this->over) ?? new \SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidWindowCount($this->kind);
    }

    /**
     * Writes the complete invocation without substituting any operands.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value)->glue()->symbol('(')->list($this->arguments)->symbol(')');
        if ($this->edge !== null) {
            $out->keyword(...explode(' ', $this->edge->value));
        }
        if ($this->nulls !== null) {
            $out->keyword(...explode(' ', $this->nulls->value));
        }
        (new Windows())->render($this->over, $out);
    }
}
