<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * The keyword DEFAULT used as a value: the default of the column it is assigned to.
 *
 * Mirrors PostgreSQL's `SetToDefault` node. The value is only meaningful
 * where a statement assigns it to a column (a VALUES row of INSERT, an UPDATE
 * SET item); the statement that holds the assignment knows the column.
 *
 * Rule: PG-DEFAULT-001. Facts: until the assignment converts it to the column
 * type, the value has the pseudo-type `unknown`, like a string constant whose
 * type its context decides; it can be NULL. Source:
 * https://www.postgresql.org/docs/17/sql-insert.html, https://www.postgresql.org/docs/17/sql-update.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Reading DEFAULT
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest()) instanceof \SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar // => true
 */
final class DefaultRequest implements Scalar
{
    use Snapshot;

    /**
     * Derives the pseudo-type the assignment resolves.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Builtin::Unknown), Nullability::Nullable);
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT');
    }
}
