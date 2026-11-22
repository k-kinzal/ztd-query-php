<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to set or remove the default of a domain: `ALTER DOMAIN name { SET DEFAULT expression | DROP DEFAULT }`.
 *
 * Rule: PG-DOMAIN-003. Mirrors `AlterDomainStmt` subtype 'T'. The default
 * expression is evaluated where no column is visible.
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
