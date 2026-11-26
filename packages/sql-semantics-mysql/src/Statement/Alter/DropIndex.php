<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;

/**
 * `DROP INDEX name ON t [ALGORITHM = …] [LOCK = …]`: a request to remove an index of a table.
 *
 * Mirrors PT_drop_index_stmt. Rule: MYSQL-DROP-INDEX-001. The table resolves
 * by MYSQL-CHANGE-TARGET-001 and its resolution is the relation fact of the
 * statement node. The completed MySQL IR resolves the physical index name
 * against the resolved owner's declaration. The ALGORITHM and
 * LOCK options are kept in the order written. The statement changes no
 * declaration and provides none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-index.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
