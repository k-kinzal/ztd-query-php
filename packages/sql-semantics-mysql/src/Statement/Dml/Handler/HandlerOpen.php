<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;

/**
 * HANDLER ... OPEN: opens a table for direct reads under its name or an alias.
 *
 * Rule: MYSQL-HANDLER-OPEN-001. The table name resolves like a table
 * reference (MYSQL-TABLE-SHAPES-001) and is recorded as a table use of the
 * statement; a declared view is refused (MYSQL-RELATION-KIND-001). The
 * statement returns no rows. Terminates: no child. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class HandlerOpen implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table with its optional database
     * @param Name|null $alias The name of the handler; the table name when absent
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly QualifiedName $table, public readonly ?Name $alias = null, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($alias !== null || $mark === AliasMark::As, 'A table without alias has no alias mark.');
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Resolves the table and records it as the table use of the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new TableShapes())->named($this->table, $derivation, $derivation->environment()));
        (new RelationKinds())->require($derivation, $this->table, $fact->table, RelationKind::BaseTable);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('HANDLER');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation)->keyword('OPEN');
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
