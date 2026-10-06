<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER OPERATOR FAMILY name USING method DROP member, ...`: removes operators and support functions from a family.
 *
 * Mirrors PostgreSQL's `AlterOpFamilyStmt` with `isDrop` true.
 * Source: https://www.postgresql.org/docs/17/sql-alteropfamily.html.
 *
 * @visibility public
 * @example Counting the removed members
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER OPERATOR FAMILY f USING btree DROP FUNCTION 1 (int4, int8)');
 *     count($operation->statement->members) // => 1
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
