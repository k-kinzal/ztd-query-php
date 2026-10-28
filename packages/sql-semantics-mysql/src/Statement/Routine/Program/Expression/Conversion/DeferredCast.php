<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;

/**
 * A stored cast around an expression whose preparation is deferred until invocation.
 *
 * The target, request form and ARRAY clause retain their semantic roles.
 * Definition-time cast conditions are derived even in unreachable branches.
 * This value never implements Scalar.
 *
 * @visibility public
 * @example Retaining the cast target without making the operand a scalar
 *     $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast($value, new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char))) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredCast implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowOperand|DeferredOperand $operand The retained invocation-time expression
     * @param CastTarget $target The scalar cast target
     */
    public function __construct(public readonly RowOperand|DeferredOperand $operand, public readonly CastTarget $target, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $array = false, public readonly CastForm $form = CastForm::Cast)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input($form !== CastForm::Convert || !$array, 'Typed CONVERT has no ARRAY clause.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives operand dependencies and target notices without invoking the condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        $problem = (new CastNotices())->cast($this->target, $this->operand, $derivation, $this->array, $this->form);

        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($problem ?? $this->problem()), $fact->nullability);
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
        $out->keyword($this->form === CastForm::Convert ? 'CONVERT' : 'CAST')->glue()->symbol('(')->node($this->operand);
        if ($this->form === CastForm::Convert) {
            $out->symbol(',');
        } else {
            if ($this->form === CastForm::AtLocal) {
                $out->keyword('AT', 'LOCAL');
            }
            $out->keyword('AS');
        }
        $out->node($this->target);
        if ($this->array) {
            $out->keyword('ARRAY');
        }
        $out->symbol(')');
    }
}
