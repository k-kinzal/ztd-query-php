<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * HANDLER ... OPEN: opens a table for direct reads under its name or an alias.
 *
 * Rule: MYSQL-HANDLER-OPEN-001. The table name resolves like a table
 * reference (MYSQL-TABLE-SHAPES-001) and is recorded as a table use of the
 * statement. The statement returns no rows. Terminates: no child. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a HANDLER ... OPEN
 *     $open = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('HANDLER shop.t OPEN AS h');
 *     [$open->statement->table->name->value, $open->statement->alias?->value, $open->toString()] // => ['t', 'h', 'HANDLER shop.t OPEN AS h']
 */
final class HandlerOpen implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table with its optional database
     * @param Name|null $alias The name of the handler; the table name when absent
     */
    public function __construct(public readonly QualifiedName $table, public readonly ?Name $alias = null)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Resolves the table and records it as the table use of the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->target($this, (new TableShapes())->named($this->table, $derivation, $derivation->environment()));
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
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
    }
}
