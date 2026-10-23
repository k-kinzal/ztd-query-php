<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Update;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;

/**
 * Derives an UPDATE predicate or ordering operand without erasing its value role.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ClauseFacts
{
    /**
     * Resolves the complete operand and records any retained scalar-use condition.
     */
    public function derive(Scalar|RowOperand|DeferredOperand $operand, Derivation $derivation, Environment $environment): void
    {
        $fact = $derivation->scalar($operand, $environment);
        if ($operand instanceof RowOperand || $operand instanceof DeferredOperand) {
            $derivation->report((new DeferredArguments())->problem([$operand]));
        } else {
            (new \SqlSemantics\Platform\MySql\Rules\Expression\Operands())->single($fact, $derivation);
        }
    }
}
