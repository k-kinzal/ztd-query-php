<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A password supplied as a text literal; binding never hashes or stores it.
 * @visibility public
 * @example Reading the password spelling
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE ROLE r ENCRYPTED PASSWORD 'secret'");
 *     $statement->options[0]->secret->text // => "'secret'"
 */
final class RolePassword
{
    /**
     * Requires a PostgreSQL text literal rather than an arbitrary expression.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $secret)
    {
        if ($secret->literalKind !== LiteralKind::Text || $secret->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A role password requires a PostgreSQL text literal.');
        }
    }
}
