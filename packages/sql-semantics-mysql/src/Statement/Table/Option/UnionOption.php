<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * The UNION option of a MERGE table: the tables it merges, possibly none.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility public
 * @example Reading a table option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT) UNION = (t1, t2)');
 *     $create->statement->options[0] instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption // => true
 */
final class UnionOption implements TableOption
{
    use Snapshot;

    /**
     * @var list<QualifiedName> The merged tables in order
     */
    public readonly array $tables;

    /**
     * @param list<QualifiedName> $tables The merged tables in order
     */
    public function __construct(array $tables)
    {
        $this->tables = Check::listOf($tables, QualifiedName::class, 'The merged tables are an ordered list of table names.');
        foreach ($this->tables as $table) {
            Check::input($table->catalog === null, 'A table name has at most a database qualifier.');
        }
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNION')->symbol('(');
        foreach ($this->tables as $position => $table) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($table->schema !== null) {
                $out->name($table->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($table->name, NameUse::Relation);
        }
        $out->symbol(')');
    }
}
