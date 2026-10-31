<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Call\TrimSide;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A TRIM call retaining its subject, removed string and deferred operand condition.
 *
 * The complete call remains available when its stored instruction is reached.
 *
 * @visibility public
 * @example Refusing to defer an ordinary scalar call
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredTrim(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredTrim implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|RowOperand|DeferredOperand $subject The complete string operand
     * @param TrimSide|null $side The explicitly selected side
     * @param Scalar|RowOperand|DeferredOperand|null $removed The optional removed string
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $subject, public readonly ?TrimSide $side = null, public readonly Scalar|RowOperand|DeferredOperand|null $removed = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$subject instanceof Scalar || ($removed !== null && !$removed instanceof Scalar), 'A deferred TRIM call retains a non-scalar operand.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both dependencies without invoking the retained condition.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->operands() as $operand) {
            $derivation->scalar($operand, $environment);
        }
        return new ScalarFact(new Invalid($this->problem()), \SqlSemantics\Statement\Type\Nullability::Nullable);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> The subject followed by an explicit removed string
     */
    public function operands(): array
    {
        return $this->removed === null ? [$this->subject] : [$this->subject, $this->removed];
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
        $out->keyword('TRIM')->glue()->symbol('(');
        if ($this->side !== null) {
            $out->keyword($this->side->value);
        }
        $out->node($this->removed);
        if ($this->side !== null || $this->removed !== null) {
            $out->keyword('FROM');
        }
        $out->node($this->subject)->symbol(')');
    }
}
