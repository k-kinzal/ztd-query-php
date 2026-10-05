<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to check the existing values of a domain against a constraint added NOT VALID.
 *
 * Rule: PG-DOMAIN-006. Mirrors `AlterDomainStmt` subtype 'V'.
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the validated constraint
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d VALIDATE CONSTRAINT c');
 *     $operation->statement->constraint->value // => 'c'
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
