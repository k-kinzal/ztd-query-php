<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Model\Statement\Type\NullOnly;

/**
 * The NULL literal.
 *
 * Rule: MYSQL-NULL-LITERAL-001. Facts: the type of a bare NULL; always NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/null-values.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class NullLiteral implements Scalar
{
    use Snapshot;

    /**
     * Derives the NULL-only type.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new NullOnly(), Nullability::Nullable);
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('NULL');
    }
}
