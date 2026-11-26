<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Option;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * The UNION option of a MERGE table: the tables it merges, possibly none.
 *
 * The equals sign between the option and its value is optional and is not kept.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-options.
 *
 * @visibility SqlSemantics
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
