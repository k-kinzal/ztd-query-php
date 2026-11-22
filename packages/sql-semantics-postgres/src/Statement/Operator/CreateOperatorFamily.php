<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * `CREATE OPERATOR FAMILY name USING method`: defines an empty operator family.
 *
 * Mirrors PostgreSQL's `CreateOpFamilyStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-createopfamily.html.
 *
 * @visibility SqlSemantics
 */
final class CreateOperatorFamily implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The family name
     * @param Name $method The index access method
     */
    public function __construct(public readonly DottedName $name, public readonly Name $method)
    {
    }

    /**
     * Derives nothing: names hold no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'OPERATOR', 'FAMILY')->node($this->name)->keyword('USING')->name($this->method, NameUse::Column);
    }
}
