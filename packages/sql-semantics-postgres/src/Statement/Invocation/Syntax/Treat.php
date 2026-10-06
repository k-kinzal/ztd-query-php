<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `TREAT (value AS type)`: a conversion the server performs by calling the function named after the type.
 *
 * The grammar turns `TREAT(x AS t)` into the call `pg_catalog.t(x)`, where
 * `t` is the last part of the type's catalog name. Rule: PG-TREAT-001.
 * Facts: those of that call (PG-CALL-RESULT-001 with `pg_catalog` alone
 * searched); the type name's modifiers are derived where they stand. The
 * result column is named after the function.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-TYPE-CASTS. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column after the type's function
 *     $treat = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\Treat(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::Integer)),
 *     );
 *     $treat->outputName()->value // => 'int4'
 */
final class Treat implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $value The value converted
     * @param TypeName $type The target type
     */
    public function __construct(public readonly Scalar $value, public readonly TypeName $type)
    {
    }

    /**
     * Names an unaliased result column after the function called.
     */
    public function outputName(): Name
    {
        return new Name($this->type->designation->catalogName()->value);
    }

    /**
     * Derives the value, the type's modifiers and the result of the call.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $value = $derivation->scalar($this->value, $environment);
        $this->type->deriveClause($derivation, $environment);

        return (new CallTyping())->catalog($derivation->context, $this->type->designation->catalogName()->value, [$value]);
    }

    /**
     * Writes TREAT with the value and the type.
     */
    public function render(Output $out): void
    {
        $out->keyword('TREAT')->glue()->symbol('(')->node($this->value)->keyword('AS')->node($this->type)->symbol(')');
    }
}
