<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Typing\Texts;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * The BINARY operator: `BINARY x`, the cast of a value to a binary string (`Item_typecast_char` with the binary character set).
 *
 * It binds as tightly as COLLATE and groups to the right, so
 * `BINARY a COLLATE c` casts the collated value (MYSQL-PRECEDENCE-001).
 * The operator is deprecated from MySQL 8.0.27 and still accepted.
 *
 * Rule: MYSQL-BINARY-OPERATOR-001. Facts: a VARBINARY; NULL when the
 * operand is. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#operator_binary.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing a binary cast
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE BINARY a = 'x'");
 *     $query->facts->scalar($query->statement->where->left)->type->descriptor->name() // => 'VARBINARY'
 */
final class BinaryCast implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The cast expression
     */
    public function __construct(public readonly Scalar $operand)
    {
        Check::input((new Precedence())->opening($operand) >= Precedence::PREFIX, 'The operand of BINARY needs a grouping to keep its place.');
    }

    /**
     * Derives the operand; the result is a binary string the server marks as nullable whatever the operand.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        Deprecation::raise(Deprecated::BinaryOperator, $derivation);

        $operand = (new Precision())->domain($fact->type);

        return new ScalarFact(new Known($operand === null ? new Binary(BinaryKind::VarBinary) : (new Texts(Settings::of($derivation->context)))->binary($operand)), Nullability::Nullable);
    }

    /**
     * Writes BINARY and the operand.
     */
    public function render(Output $out): void
    {
        $out->keyword('BINARY')->node($this->operand);
    }
}
