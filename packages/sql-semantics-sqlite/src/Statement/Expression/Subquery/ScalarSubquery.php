<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Type\Vector;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A query in parentheses used as a value: the first row of its result, or NULL when it has no row.
 *
 * Rule: SQLITE-SCALAR-SUBQUERY-001. The query is derived inside the
 * environment of the expression, so it can refer to the enclosing queries.
 * A query of one column yields the type of that column and can always be
 * NULL; a query of several columns is a row value; a query whose shape is
 * open depends on the missing inputs; a query without any column is invalid.
 * Source: https://sqlite.org/lang_expr.html#subquery_expressions. Status: Implemented.
 *
 * @visibility public
 * @example Reading the facts of a scalar subquery
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT (SELECT 1)');
 *     [$query->field(0)->type->descriptor, $query->field(0)->nullability] // => [\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer, \SqlSemantics\Statement\Type\Nullability::Nullable]
 */
final class ScalarSubquery implements Scalar
{
    use Snapshot;

    /**
     * @param Query $query The query
     */
    public function __construct(public readonly Query $query)
    {
    }

    /**
     * Derives the query and the value it yields.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->query($this->query, $environment);
        $fields = $fact->fields();
        if ($fields === null) {
            return new ScalarFact(new Dependent($fact->shape->missing), Nullability::Nullable);
        }
        if (count($fields) === 0) {
            return new ScalarFact(new Invalid(new ArityMismatch(ArityRule::ScalarSubquery, 1, 0)), Nullability::Nullable);
        }
        if (count($fields) !== 1) {
            return new ScalarFact(new Known(new Vector(count($fields))), Nullability::Nullable);
        }

        return new ScalarFact($fields->at(0)->type, Nullability::Nullable);
    }

    /**
     * Writes the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
