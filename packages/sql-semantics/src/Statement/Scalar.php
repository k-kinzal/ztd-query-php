<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;

/**
 * A scalar expression: an operation over operands that yields one value per evaluation.
 *
 * Its type, NULL fact and name resolution are not stored on the node; the
 * operation that contains the node derives and owns them.
 *
 * @visibility public
 * @example Reading the facts an operation derived for an expression
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1');
 *     $operation->facts->scalar($operation->field(0)->expression)->nullability // => \SqlSemantics\Statement\Type\Nullability::NotNull
 */
interface Scalar extends Node
{
    /**
     * Derives the facts of this expression at its use position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact;
}
