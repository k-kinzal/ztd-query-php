<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A test whether a query returns at least one row.
 *
 * Rule: SQLITE-EXISTS-001. The query is derived inside the environment of
 * the expression. The result is the INTEGER 0 or 1 and never NULL.
 * Source: https://sqlite.org/lang_expr.html#the_exists_operator. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Exists implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Derives the query; the test is an INTEGER that is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->query($this->query, $environment);

        return new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
    }

    /**
     * Writes the test.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXISTS')->symbol('(')->node($this->query)->symbol(')');
    }
}
