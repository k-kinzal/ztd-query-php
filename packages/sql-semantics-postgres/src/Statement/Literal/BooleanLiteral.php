<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The constant TRUE or FALSE.
 *
 * Rule: PG-BOOLEAN-001. Facts: `boolean`, never NULL. Since PostgreSQL 15
 * the raw parser reads the keyword as a plain boolean constant (`A_Const`
 * with a `Boolean` value, `makeBoolAConst` in gram.y), not as a cast to
 * `bool`, so the expression gives an unaliased result column no name and the
 * column is named `?column?`.
 * Source: https://www.postgresql.org/docs/17/datatype-boolean.html,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST. Status: Implemented.
 *
 * @visibility public
 * @example Reading the name TRUE gives its column
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT TRUE');
 *     [$query->statement->targets[0]->expression->value, $query->field(0)->name->value] // => [true, '?column?']
 */
final class BooleanLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param bool $value The truth value
     */
    public function __construct(public readonly bool $value)
    {
    }

    /**
     * Derives `boolean`, never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Builtin::Bool), Nullability::NotNull);
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value ? 'TRUE' : 'FALSE');
    }
}
