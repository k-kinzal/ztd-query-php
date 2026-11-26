<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * The literal TRUE or FALSE.
 *
 * Rule: MYSQL-BOOLEAN-LITERAL-001. Facts: TRUE and FALSE evaluate to the
 * integers 1 and 0, so the type is that of an integer literal, BIGINT; never
 * NULL. Source: https://dev.mysql.com/doc/refman/8.4/en/boolean-literals.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
