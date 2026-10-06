<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Locking;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One locking clause of a query: `FOR UPDATE|SHARE [OF tables] [NOWAIT|SKIP LOCKED]` or `LOCK IN SHARE MODE`.
 *
 * The tables are names of tables of the query, by correlation name when
 * they have one; the query that holds the clause reports a name that names
 * none. A table may be written `t.*`, which names the same table; the form
 * is kept, since a locking clause of a subquery is part of the text MySQL
 * names an unaliased select list expression after. Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html,
 * https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading the tables of a locking clause
 *     $clause = new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update, [new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))], \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction::Nowait);
 *     [$clause->tables[0]->name->value, $clause->action] // => ['t', \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction::Nowait]
 * @example Refusing a table list on LOCK IN SHARE MODE
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::ShareMode, [new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class LockingClause implements Node
{
    use Snapshot;

    /**
     * @var list<QualifiedName> The locked tables in written order; empty for every table of the query
     */
    public readonly array $tables;

    /**
     * @var list<OptionalWords> For each locked table, whether it is written with `.*`
     */
    public readonly array $wildcards;

    /**
     * @param LockStrength $strength The lock taken
     * @param list<QualifiedName> $tables The locked tables; empty for every table of the query
     * @param LockedRowAction|null $action What to do with a row locked elsewhere
     * @param list<OptionalWords> $wildcards For each locked table, whether it is written with `.*`; empty when none is
     */
    public function __construct(public readonly LockStrength $strength, array $tables = [], public readonly ?LockedRowAction $action = null, array $wildcards = [])
    {
        $this->tables = Check::listOf($tables, QualifiedName::class, 'A locking clause names tables.');
        $listed = Check::listOf($wildcards, OptionalWords::class, 'The wildcard forms of a locking clause are a list.');
        Check::input($listed === [] || count($listed) === count($this->tables), 'A locking clause has one wildcard form per table.');
        $this->wildcards = $listed === [] ? array_fill(0, count($this->tables), OptionalWords::Omitted) : $listed;
        Check::input($strength !== LockStrength::ShareMode || ($tables === [] && $action === null), 'LOCK IN SHARE MODE takes no table list and no action.');
        foreach ($this->tables as $table) {
            Check::input($table->catalog === null, 'A table is qualified by at most a database.');
        }
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        if ($this->strength === LockStrength::ShareMode) {
            $out->keyword('LOCK', 'IN', 'SHARE', 'MODE');

            return;
        }
        $out->keyword('FOR', $this->strength === LockStrength::Update ? 'UPDATE' : 'SHARE');
        foreach ($this->tables as $position => $table) {
            if ($position === 0) {
                $out->keyword('OF');
            } else {
                $out->symbol(',');
            }
            if ($table->schema !== null) {
                $out->name($table->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($table->name, NameUse::Relation);
            if ($this->wildcards[$position] === OptionalWords::Written) {
                $out->symbol('.')->symbol('*');
            }
        }
        if ($this->action === LockedRowAction::Nowait) {
            $out->keyword('NOWAIT');
        } elseif ($this->action === LockedRowAction::SkipLocked) {
            $out->keyword('SKIP', 'LOCKED');
        }
    }
}
