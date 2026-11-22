<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to check the existing values of a domain against a constraint added NOT VALID.
 *
 * Rule: PG-DOMAIN-006. Mirrors `AlterDomainStmt` subtype 'V'.
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ValidateDomainConstraint implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The domain name
     * @param Name $constraint The constraint name
     */
    public function __construct(public readonly DottedName $name, public readonly Name $constraint)
    {
    }

    /**
     * Derives nothing: domains are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DOMAIN')->node($this->name)->keyword('VALIDATE', 'CONSTRAINT')->name($this->constraint, NameUse::Column);
    }
}
