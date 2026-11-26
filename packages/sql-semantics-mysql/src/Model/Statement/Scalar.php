<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;

/**
 * A scalar expression: an operation over operands that yields one value per evaluation.
 *
 * Its type, NULL fact and name resolution are not stored on the node; the
 * operation that contains the node derives and owns them.
 *
 * @visibility SqlSemantics
 */
interface Scalar extends Node
{
    /**
     * Derives the facts of this expression at its use position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact;
}
