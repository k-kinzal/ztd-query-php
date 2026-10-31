<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A GET_FORMAT call retaining its format kind and deferred standard operand.
 *
 * Every operand remains available for binding when its instruction is reached.
 * An ordinary scalar argument uses the ordinary call structure.
 *
 * @visibility public
 * @example Retaining a row condition until the stored instruction is reached
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]);
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredGetFormat(\SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat::Date, $row);
 *     $call->operands()[0] === $row // => true
 */
final class DeferredGetFormat implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param TemporalFormat $format The keyword operand
     * @param RowOperand|DeferredOperand $standard The complete operand retaining its preparation condition
     */
    public function __construct(public readonly TemporalFormat $format, public readonly RowOperand|DeferredOperand $standard, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the retained argument without invoking its condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->standard, $environment);
        return new ScalarFact(new Invalid($this->problem()), $fact->nullability);
    }

    /**
     * @return non-empty-list<RowOperand|DeferredOperand> The retained argument
     */
    public function operands(): array
    {
        return [$this->standard];
    }

    /**
     * Answers the argument's preparation condition.
     */
    public function problem(): Diagnostic
    {
        return (new DeferredArguments())->problem($this->operands());
    }

    /**
     * Writes the complete function and both semantic operands.
     */
    public function render(Output $out): void
    {
        $out->keyword('GET_FORMAT')->glue()->symbol('(')->keyword($this->format->value)->symbol(',')->node($this->standard)->symbol(')');
    }
}
