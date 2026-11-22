<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

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
 * @visibility SqlSemantics
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
