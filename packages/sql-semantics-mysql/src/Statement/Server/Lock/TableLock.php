<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Lock;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;

/**
 * One table LOCK TABLES locks: `t [[AS] alias] lock_type`.
 *
 * The statement that holds it records the resolution of the table name as
 * the relation fact of this node. A table locked under an alias is reached
 * by later statements of the session only through that alias.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html.
 *
 * @visibility SqlSemantics
 * @example Holding a table, its alias and the lock
 *     $lock = new \SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('a'), \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::Write);
 *     [$lock->table->name->value, $lock->alias?->value, $lock->mode] // => ['t', 'a', \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode::Write]
 */
final class TableLock implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table name with its optional database
     * @param Name|null $alias The alias, when written
     * @param LockMode $mode The lock
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly QualifiedName $table, public readonly ?Name $alias, public readonly LockMode $mode, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($alias !== null || $mark === AliasMark::As, 'A table without alias has no alias mark.');
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Writes the table, the alias and the lock.
     */
    public function render(Output $out): void
    {
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
        $out->keyword(...explode(' ', $this->mode->value));
    }
}
