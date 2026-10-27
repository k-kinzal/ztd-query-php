<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
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
 * An aggregate invocation retaining a non-scalar argument until preparation.
 *
 * No ordinary aggregate or fabricated scalar is needed to retain this call.
 * All arguments, quantifiers and the optional window keep their semantic roles.
 *
 * @visibility public
 * @example Keeping an aggregate's row operand outside Scalar
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum, [$row]) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredAggregate implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Scalar|RowOperand|DeferredOperand> The complete arguments
     */
    public readonly array $arguments;

    /**
     * @param AggregateFunction $function The aggregate operation
     * @param non-empty-list<Scalar|RowOperand|DeferredOperand> $arguments The ordered arguments
     * @param bool $distinct Whether DISTINCT is written
     * @param bool $all Whether ALL is written
     * @param Name|WindowSpecification|null $over The optional window
     */
    public function __construct(public readonly AggregateFunction $function, array $arguments, public readonly bool $distinct = false, public readonly bool $all = false, public readonly Name|WindowSpecification|null $over = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $arguments = Check::listOf($arguments, \SqlSemantics\Statement\Operand::class, 'A retained aggregate has a non-empty argument list.', 1);
        foreach ($arguments as $argument) {
            Check::input($argument instanceof Scalar || $argument instanceof RowOperand || $argument instanceof DeferredOperand, 'An aggregate retains typed operands.');
        }
        Check::input(array_filter($arguments, static fn ($argument): bool => !$argument instanceof Scalar) !== [], 'A retained aggregate requires a non-scalar argument.');
        Check::input(count($arguments) === 1 || ($function === AggregateFunction::Count && $distinct && !$all), 'Only COUNT(DISTINCT ...) takes several arguments.');
        Check::input(!$distinct || $function->distinctive(), 'The aggregate accepts its DISTINCT quantifier.');
        Check::input(!($distinct && $all && $function === AggregateFunction::Count), 'COUNT(DISTINCT ...) takes no ALL.');
        $this->arguments = $arguments;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives all retained dependencies without publishing an ordinary aggregate.
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
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> The arguments in preparation order
     */
    public function operands(): array
    {
        return $this->arguments;
    }

    /**
     * Answers the first retained argument condition.
     */
    public function problem(): Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem($this->arguments);
    }

    /**
     * Writes the complete aggregate invocation.
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
        $out->list($this->arguments)->symbol(')');
        (new Windows())->render($this->over, $out);
    }
}
