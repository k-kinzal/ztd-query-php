<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Term;

/**
 * A normal coerced value together with possible argument-type failure.
 *
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class TypeCheck
{
    /**
     * @param Term $value value
     * @param bool $mayFail mayFail
     * @param bool $mustFail mustFail
     * @param bool $diagnostic Whether a successful coercion emits a target diagnostic
     * @param Term|null $coercion Operand of an unresolved implicit conversion or callable check
     * @param string $operation Missing implicit-call protocol
     */
    public function __construct(
        public readonly Term $value,
        public readonly bool $mayFail = false,
        public readonly bool $mustFail = false,
        public readonly bool $diagnostic = false,
        public readonly ?Term $coercion = null,
        public readonly string $operation = 'object-string-coercion',
    ) {
    }

    /**
     * Includes arbitrary exceptions from unresolved user-defined coercion.
     * @return Term Type failure or an unknown throwable after coercion effects
     */
    public function exception(): Term
    {
        return $this->coercion === null ? new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError') : new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable', attributes: ['uncertain' => true]);
    }
}
