<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Domain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to forbid or allow NULL values of a domain: `ALTER DOMAIN name { SET | DROP } NOT NULL`.
 *
 * Rule: PG-DOMAIN-004. Mirrors `AlterDomainStmt` subtype 'O' (SET) or 'N' (DROP).
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility public
 * @example Forbidding NULL values
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d SET NOT NULL');
 *     $operation->statement->notNull // => true
 */
final class AlterDomainNotNull implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The domain name
     * @param bool $notNull Whether NULL is forbidden (SET) instead of allowed (DROP)
     */
    public function __construct(public readonly DottedName $name, public readonly bool $notNull)
    {
    }

    /**
     * Derives nothing: the request holds no expression and domains are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DOMAIN')->node($this->name)->keyword($this->notNull ? 'SET' : 'DROP', 'NOT', 'NULL');
    }
}
