<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A test whether a query returns at least one row.
 *
 * Rule: SQLITE-EXISTS-001. The query is derived inside the environment of
 * the expression. The result is the INTEGER 0 or 1 and never NULL.
 * Source: https://sqlite.org/lang_expr.html#the_exists_operator. Status: Implemented.
 *
 * @visibility public
 * @example Reading the facts of EXISTS
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT EXISTS (SELECT 1 FROM t)');
 *     [$query->field(0)->type->descriptor, $query->field(0)->nullability] // => [\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer, \SqlSemantics\Statement\Type\Nullability::NotNull]
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
