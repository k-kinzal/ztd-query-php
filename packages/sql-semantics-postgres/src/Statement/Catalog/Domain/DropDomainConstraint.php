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
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * A request to remove a constraint of a domain.
 *
 * Rule: PG-DOMAIN-005. Mirrors `AlterDomainStmt` subtype 'X' with
 * `missing_ok` and the drop behavior.
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropDomainConstraint implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The domain name
     * @param Name $constraint The constraint name
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior The drop behavior, when written
     */
    public function __construct(public readonly DottedName $name, public readonly Name $constraint, public readonly bool $ifExists = false, public readonly ?DropBehavior $behavior = null)
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
        $out->keyword('ALTER', 'DOMAIN')->node($this->name)->keyword('DROP', 'CONSTRAINT');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->constraint, NameUse::Column);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
