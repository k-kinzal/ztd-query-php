<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * The constant NULL.
 *
 * Rule: PG-NULL-001. Facts: the null-only type, nullable; the type it takes
 * is decided by its context. Source: https://www.postgresql.org/docs/17/typeconv-overview.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
