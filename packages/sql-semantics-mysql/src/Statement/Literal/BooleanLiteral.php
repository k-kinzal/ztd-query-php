<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The literal TRUE or FALSE.
 *
 * Rule: MYSQL-BOOLEAN-LITERAL-001. Facts: TRUE and FALSE evaluate to the
 * integers 1 and 0, so the type is that of an integer literal, BIGINT; never
 * NULL. Source: https://dev.mysql.com/doc/refman/8.4/en/boolean-literals.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a boolean literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = TRUE');
 *     $query->statement->where->right->value // => true
 */
final class BooleanLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param bool $value Whether the literal is TRUE
     */
    public function __construct(public readonly bool $value)
    {
    }

    /**
     * Derives the integer type; a literal is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull);
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value ? 'TRUE' : 'FALSE');
    }
}
