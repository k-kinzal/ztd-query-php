<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP INDEX name ON t [ALGORITHM = …] [LOCK = …]`: a request to remove an index of a table.
 *
 * Mirrors PT_drop_index_stmt. Rule: MYSQL-DROP-INDEX-001. The table resolves
 * by MYSQL-CHANGE-TARGET-001 and its resolution is the relation fact of the
 * statement node; `PRIMARY` names the primary key. A declaration context
 * holds no indexes, so the index name is kept unresolved. The ALGORITHM and
 * LOCK options are kept in the order written. The statement changes no
 * declaration and provides none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-index.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing an index while allowing reads
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DROP INDEX i ON t LOCK SHARED');
 *     [$drop->statement->index->value, $drop->toString()] // => ['i', 'DROP INDEX i ON t LOCK = SHARED']
 */
final class DropIndex implements Statement
{
    use Snapshot;

    /**
     * @var list<AlterOption> The ALGORITHM and LOCK options in order
     */
    public readonly array $options;

    /**
     * @param Name $index The index name
     * @param QualifiedName $table The table with its optional database
     * @param list<AlterOption> $options The ALGORITHM and LOCK options in order
     */
    public function __construct(public readonly Name $index, public readonly QualifiedName $table, array $options = [])
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
        $this->options = Check::listOf($options, AlterOption::class, 'The options of DROP INDEX are a list of ALGORITHM and LOCK options.');
    }

    /**
     * Records the resolution of the table and derives the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->target($this, (new Targets())->target($derivation, $this->table));
        foreach ($this->options as $option) {
            $option->deriveOption($derivation);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'INDEX')->name($this->index, NameUse::Label)->keyword('ON');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
