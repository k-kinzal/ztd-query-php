<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;

/**
 * A stored cast around an expression whose preparation is deferred until invocation.
 *
 * The operand retains its role and the BINARY spelling retains its notice.
 * This value never implements Scalar.
 *
 * @visibility public
 * @example Retaining a binary cast without making the operand a scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredBinaryCast($value)) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredBinaryCast implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowOperand|DeferredOperand $operand The retained invocation-time expression
     */
    public function __construct(public readonly RowOperand|DeferredOperand $operand, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Expression\Precedence())->opening($operand) >= \SqlSemantics\Platform\MySql\Rules\Expression\Precedence::PREFIX, 'A BINARY operand requires grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives operand dependencies and the operator notice without invoking the condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        if (\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator->warnedIn($derivation->context->profile->grammar)) {
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BinaryOperator));
        }

        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($this->problem()), $fact->nullability);
    }

    /**
     * @return non-empty-list<RowOperand|DeferredOperand> The retained preparation dependency
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Preserves the operand's invocation-time condition.
     */
    public function problem(): Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem($this->operands());
    }

    /**
     * Writes the complete scalar cast.
     */
    public function render(Output $out): void
    {
        $out->keyword('BINARY')->node($this->operand);
    }
}
