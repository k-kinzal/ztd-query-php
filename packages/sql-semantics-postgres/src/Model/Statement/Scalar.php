<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;

/**
 * A scalar expression: an operation over operands that yields one value per evaluation.
 *
 * Its type, NULL fact and name resolution are not stored on the node; the
 * operation that contains the node derives and owns them.
 *
 * @visibility public
 * @example Reading the facts an operation derived for an expression
 *     $operation = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SELECT 1');
 *     $operation->facts->scalar($operation->field(0)->expression)->nullability // => \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull
 */
interface Scalar extends Node
{
    /**
     * Derives the facts of this expression at its use position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact;
}
