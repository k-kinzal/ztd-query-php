<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the identity and literal domains of PostgreSQL foreign servers.
 * @visibility SqlSemantics
 */
final class ServerInvariant
{
    /**
     * Requires a local server name rather than a connection or qualified relation.
     * @throws InvalidStructure
     */
    public static function target(Origin $origin, string $name): void
    {
        if ($origin->dialect !== Dialect::PostgreSql || $name === '') {
            throw new InvalidStructure('A foreign server requires PostgreSQL and a nonempty name.');
        }
    }

    /**
     * Permits text metadata, absence, or a classified version-change policy.
     * @throws InvalidStructure
     */
    public static function text(Literal|ServerVersionChange|null $value): void
    {
        if ($value instanceof Literal && ($value->type->dialect !== Dialect::PostgreSql || $value->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('Foreign server metadata requires a PostgreSQL text literal.');
        }
    }
}
