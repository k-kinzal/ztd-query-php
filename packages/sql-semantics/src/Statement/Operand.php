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
 *     $operation->facts->scalar($operation->field(0)->expression)->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
interface Operand extends Node
{
    /**
     * Derives the facts of this value at its use position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact;
}
