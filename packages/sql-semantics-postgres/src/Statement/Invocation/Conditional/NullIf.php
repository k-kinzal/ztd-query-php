<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Categories;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\OperatorTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * `NULLIF (value, other)`: NULL when the two are equal, otherwise the first.
 *
 * Mirrors PostgreSQL's `A_Expr` of kind `AEXPR_NULLIF` over the `=`
 * operator. Rule: PG-NULLIF-001. Facts: the `=` comparison of the operands
 * must resolve (the operator table of `Rules\Expression\Typing\OperatorTyping`); the result has the type of the
 * first operand, or of the second when the first is a string constant or
 * NULL, which the implied `=` promotes; it may be NULL. A comparison that
 * depends on a declaration, or is invalid, gives the call the same fact.
 * The result column is named `nullif`.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-NULLIF. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\NullIf(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()))->outputName()->value // => 'nullif'
 */
final class NullIf implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $value The value returned when the two differ
     * @param Scalar $other The value compared with
     */
    public function __construct(public readonly Scalar $value, public readonly Scalar $other)
    {
    }

    /**
     * Names an unaliased result column.
     */
    public function outputName(): Name
    {
        return new Name('nullif');
    }

    /**
     * Derives the operands, the comparison and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $derivation->scalar($this->value, $environment);
        $other = $derivation->scalar($this->other, $environment);
        $comparison = (new OperatorTyping())->named($derivation->context, '=', $value->type, $other->type);
        if (!$comparison instanceof Known) {
            return new ScalarFact($comparison, Nullability::Dependent);
        }
        $categories = new Categories();
        if ($categories->builtin($value->type) !== Builtin::Unknown) {
            return new ScalarFact($value->type, Nullability::Nullable);
        }

        return new ScalarFact($categories->builtin($other->type) === Builtin::Unknown ? new Known(Builtin::Text) : $other->type, Nullability::Nullable);
    }

    /**
     * Writes NULLIF with the two operands.
     */
    public function render(Output $out): void
    {
        $out->keyword('NULLIF')->glue()->symbol('(')->node($this->value)->symbol(',')->node($this->other)->symbol(')');
    }
}
