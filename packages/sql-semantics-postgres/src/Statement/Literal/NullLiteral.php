<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * The constant NULL.
 *
 * Rule: PG-NULL-001. Facts: the null-only type, nullable; the type it takes
 * is decided by its context. Source: https://www.postgresql.org/docs/17/typeconv-overview.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the facts of NULL
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT NULL');
 *     $query->field(0)->nullability // => \SqlSemantics\Statement\Type\Nullability::Nullable
 */
final class NullLiteral implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * Gives a result column no name.
     */
    public function outputName(): ?Name
    {
        return null;
    }

    /**
     * Derives the null-only type.
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
