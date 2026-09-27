<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\Value\Term;

/**
 * A normal coerced value together with possible argument-type failure.
 *
 * @visibility root
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
        return $this->coercion === null ? new Term('throwable', 'TypeError') : new Term('throwable', 'Throwable', attributes: ['uncertain' => true]);
    }
}
