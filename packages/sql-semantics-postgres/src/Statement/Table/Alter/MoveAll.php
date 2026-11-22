<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;

/**
 * ALTER ... ALL IN TABLESPACE ... SET TABLESPACE: moves every table, index or materialized view of a tablespace.
 *
 * Mirrors PostgreSQL's `AlterTableMoveAllStmt` (`orig_tablespacename`, `objtype`, `roles`,
 * `new_tablespacename`, `nowait`).
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class MoveAll implements Statement
{
    use Snapshot;

    /**
     * @var list<RoleSpec> The owners whose relations move; none means all
     */
    public readonly array $owners;

    /**
     * @param AlterTarget $target The kind of relation: tables, indexes or materialized views
     * @param Name $from The tablespace the relations are in
     * @param Name $to The tablespace they move to
     * @param list<RoleSpec> $owners The owners whose relations move; none means all
     * @param bool $nowait Whether NOWAIT is written
     */
    public function __construct(
        public readonly AlterTarget $target,
        public readonly Name $from,
        public readonly Name $to,
        array $owners = [],
        public readonly bool $nowait = false,
    ) {
        $this->owners = Check::listOf($owners, RoleSpec::class, 'Owners are roles.');
        Check::input(in_array($target, [AlterTarget::Table, AlterTarget::Index, AlterTarget::MaterializedView], true), 'ALL IN TABLESPACE moves tables, indexes or materialized views.');
    }

    /**
     * Derives nothing: the statement names catalog objects only.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', ...$this->target->keywords())->keyword('ALL', 'IN', 'TABLESPACE')->name($this->from);
        if ($this->owners !== []) {
            $out->keyword('OWNED', 'BY')->list($this->owners);
        }
        $out->keyword('SET', 'TABLESPACE')->name($this->to);
        if ($this->nowait) {
            $out->keyword('NOWAIT');
        }
    }
}
