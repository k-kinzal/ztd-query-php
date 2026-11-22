<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;

/**
 * The NULL literal.
 *
 * Rule: SQLITE-NULL-LITERAL-001. The literal is the NULL value and nothing else.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
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
