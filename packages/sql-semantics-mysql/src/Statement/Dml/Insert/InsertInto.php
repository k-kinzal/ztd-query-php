<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;

/**
 * The head of INSERT and REPLACE: the verb, its modifiers, the table and the optional column list.
 *
 * Mirrors the server's `PT_insert` with its `is_replace` flag: REPLACE
 * deletes a row that duplicates a unique key before it inserts the new one.
 * REPLACE takes no IGNORE and no HIGH_PRIORITY; the written table has no
 * correlation name. The keyword INTO is optional and always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html, https://dev.mysql.com/doc/refman/8.4/en/replace.html.
 *
 * @visibility SqlSemantics
 */
final class InsertInto implements Node
{
    use Snapshot;

    /**
     * @param bool $replace Whether the verb is REPLACE
     * @param InsertPriority|null $priority The scheduling modifier
     * @param bool $ignore Whether IGNORE turns errors into warnings
     * @param WriteTarget $table The table the rows are written to
     * @param ColumnList|null $columns The columns written; null for every column of the table
     * @throws InvalidConstruction When REPLACE has IGNORE or HIGH_PRIORITY, or the table has a correlation name
     */
    public function __construct(
        public readonly bool $replace,
        public readonly ?InsertPriority $priority,
        public readonly bool $ignore,
        public readonly WriteTarget $table,
        public readonly ?ColumnList $columns = null,
    ) {
        Check::input(!$replace || (!$ignore && $priority !== InsertPriority::High), 'REPLACE takes neither IGNORE nor HIGH_PRIORITY.');
        Check::input($table->alias === null, 'The table of INSERT and REPLACE has no correlation name.');
    }

    /**
     * Writes the verb, the modifiers, INTO, the table and the column list.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->replace ? 'REPLACE' : 'INSERT');
        if ($this->priority !== null) {
            $out->keyword($this->priority->value);
        }
        if ($this->ignore) {
            $out->keyword('IGNORE');
        }
        $out->keyword('INTO')->node($this->table)->node($this->columns);
    }
}
