<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Role;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The timestamp text after which a role's password stops working, kept as written.
 * @visibility public
 * @example Reading the expiry spelling
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER ROLE r VALID UNTIL 'infinity'");
 *     $statement->options[0]->until->text // => "'infinity'"
 */
final class RoleValidity
{
    /**
     * Requires a PostgreSQL text literal; the timestamp is not parsed or evaluated.
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $until)
    {
        if ($until->literalKind !== LiteralKind::Text || $until->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A role validity bound requires a PostgreSQL text literal.');
        }
    }
}
