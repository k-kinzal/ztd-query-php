<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * The NULL literal.
 *
 * Rule: SQLITE-NULL-LITERAL-001. The literal is the NULL value and nothing else.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the facts of NULL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NULL');
 *     [$query->field(0)->type instanceof \SqlSemantics\Statement\Type\NullOnly, $query->field(0)->nullability] // => [true, \SqlSemantics\Statement\Type\Nullability::Nullable]
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
