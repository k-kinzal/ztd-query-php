<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;

/**
 * A value with type, nullability and resolution facts at its use position.
 *
 * Some values belong only to a particular statement or argument position.
 * Those values implement this interface without implementing Scalar; their
 * parent names the permitted alternatives explicitly. Scalar is the subset
 * accepted at general expression positions.
 *
 * @visibility public
 * @example Reading the fact of an expression operand
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     $operation->field(0)->expression->scalarInformation()->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
interface Operand extends Expression
{
    /**
     * Returns the type, nullability and reference owned by this value occurrence.
     */
    public function scalarInformation(): ScalarFact;

    /**
     * Completes this occurrence during semantic construction.
     */
    public function initializeScalarInformation(ScalarFact $information): void;

    /**
     * Derives the facts of this value at its use position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact;
    /**
     * Reports whether construction supplied this occurrence's information.
     */
    public function hasScalarInformation(): bool;
}
