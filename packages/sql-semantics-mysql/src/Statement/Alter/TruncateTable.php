<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\RelationKinds;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `TRUNCATE [TABLE] t`: a request to remove every row of a table.
 *
 * Mirrors PT_truncate_table_stmt. Rule: MYSQL-TRUNCATE-001. The name
 * resolves by MYSQL-CHANGE-TARGET-001 and its resolution is the relation fact
 * of the statement node; a declared view is refused
 * (MYSQL-RELATION-KIND-001). The statement changes no declaration and provides
 * none. The word TABLE is optional and always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/truncate-table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Resolving the emptied table
 *     $truncate = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('TRUNCATE shop.t');
 *     [$truncate->toString(), $truncate->statement->table->schema?->value] // => ['TRUNCATE TABLE shop.t', 'shop']
 */
final class TruncateTable implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table with its optional database
     */
    public function __construct(public readonly QualifiedName $table)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Records the resolution of the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new Targets())->target($derivation, $this->table));
        (new RelationKinds())->refuseView($derivation, $this->table, $fact->table, KindRefusal::NoSuchTable);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('TRUNCATE', 'TABLE');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
    }
}
