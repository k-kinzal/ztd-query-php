<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

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
 * Rule: MYSQL-NULL-LITERAL-001. Facts: the type of a bare NULL; always NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/null-values.html. Status: Implemented.
 *
 * @visibility public
 * @example Typing a bare NULL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = NULL');
 *     $query->facts->scalar($query->statement->where->right)->nullability // => \SqlSemantics\Statement\Type\Nullability::Nullable
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
