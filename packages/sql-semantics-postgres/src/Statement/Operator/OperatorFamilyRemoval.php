<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * `ALTER OPERATOR FAMILY name USING method DROP member, ...`: removes operators and support functions from a family.
 *
 * Mirrors PostgreSQL's `AlterOpFamilyStmt` with `isDrop` true.
 * Source: https://www.postgresql.org/docs/17/sql-alteropfamily.html.
 *
 * @visibility SqlSemantics
 */
final class OperatorFamilyRemoval implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<MemberRemoval> The removed members in written order
     */
    public readonly array $members;

    /**
     * @param DottedName $name The family name
     * @param Name $method The index access method
     * @param list<MemberRemoval> $members The removed members; at least one
     */
    public function __construct(public readonly DottedName $name, public readonly Name $method, array $members)
    {
        $this->members = Check::listOf($members, MemberRemoval::class, 'DROP names at least one member.', 1);
    }

    /**
     * Derives the operand types.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        foreach ($this->members as $member) {
            $member->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'OPERATOR', 'FAMILY')->node($this->name)->keyword('USING')->name($this->method, NameUse::Column)->keyword('DROP')->list($this->members);
    }
}
