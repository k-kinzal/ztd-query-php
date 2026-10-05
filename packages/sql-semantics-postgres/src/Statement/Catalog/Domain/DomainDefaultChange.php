<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to set or remove the default of a domain: `ALTER DOMAIN name { SET DEFAULT expression | DROP DEFAULT }`.
 *
 * Rule: PG-DOMAIN-003. Mirrors `AlterDomainStmt` subtype 'T'. The default
 * expression is evaluated where no column is visible.
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility public
 * @example Removing the default
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d DROP DEFAULT');
 *     $operation->statement->default // => null
 */
final class DomainDefaultChange implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The domain name
     * @param Scalar|null $default The new default; null for DROP DEFAULT
     */
    public function __construct(public readonly DottedName $name, public readonly ?Scalar $default)
    {
    }

    /**
     * Derives the default expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->default !== null) {
            $derivation->scalar($this->default, new Environment($derivation->context));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DOMAIN')->node($this->name);
        if ($this->default === null) {
            $out->keyword('DROP', 'DEFAULT');

            return;
        }
        $out->keyword('SET', 'DEFAULT')->node($this->default);
    }
}
