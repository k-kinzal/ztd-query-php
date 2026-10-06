<?php

declare(strict_types=1);

namespace SqlSemantics\Diagnostic;

use LogicException;
use SqlSemantics\Lowering\Form;

/**
 * A structuring, derivation, or rendering rule that the library has not implemented.
 *
 * It is a defect of the library and never a property of the SQL: grammatical
 * input must not be turned into an unknown value or a rejection because a rule
 * is absent.
 *
 * @visibility public
 * @example Naming the production that has no rule
 *     \SqlSemantics\Diagnostic\ImplementationGap::rule('cmd: PRAGMA nm')->getMessage() // => 'No semantic rule is implemented for: cmd: PRAGMA nm'
 */
final class ImplementationGap extends LogicException
{
    /**
     * Reports a grammar production no lowering rule claims.
     */
    public static function production(Form $form): self
    {
        return self::rule($form->signature);
    }

    /**
     * Reports a named rule obligation that has no implementation.
     */
    public static function rule(string $obligation): self
    {
        return new self('No semantic rule is implemented for: ' . $obligation);
    }
}
