<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * `COLLATION FOR (value)`: the name of the collation of a value.
 *
 * The server calls `pg_catalog.pg_collation_for(value)`. Rule:
 * PG-COLLATION-FOR-001. Facts: `text`, NULL when no collation is derived for
 * the value. The result column is named `pg_collation_for`.
 * Source: https://www.postgresql.org/docs/17/functions-info.html#FUNCTIONS-INFO-CATALOG. Status: Implemented.
 *
 * @visibility public
 * @example Naming the result column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\CollationFor(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()))->outputName()->value // => 'pg_collation_for'
 */
final class CollationFor implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $value The value whose collation is asked
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('pg_collation_for');
    }

    /**
     * Derives the value and the result of `pg_catalog.pg_collation_for`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new CallTyping())->catalog($derivation->context, 'pg_collation_for', [$derivation->scalar($this->value, $environment)]);
    }

    /**
     * Writes COLLATION FOR and the value in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('COLLATION', 'FOR')->symbol('(')->node($this->value)->symbol(')');
    }
}
