<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowTargets;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DESCRIBE (DESC, EXPLAIN) with a table: the columns of a table or view, as SHOW COLUMNS reports them.
 *
 * Rule: MYSQL-DESCRIBE-001. The table resolves by MYSQL-SHOW-TARGET-001.
 * A column written after the table is a LIKE pattern the column names
 * must match, written as a name or as a string. The columns are those of
 * SHOW COLUMNS in the layout of MYSQL-SHOW-ROWS-001. DESCRIBE, DESC and
 * EXPLAIN are the same keyword (UtilityNoise); the writer emits DESCRIBE.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a column pattern
 *     $describe = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("DESC t 'a%'");
 *     [$describe->statement->column?->value, $describe->field(0)->name?->value, $describe->toString()] // => ['a%', 'Field', "DESCRIBE t 'a%'"]
 */
final class DescribeTable implements Statement
{
    use Snapshot;

    /**
     * @param InspectedTable $table The table
     * @param Name|Text|null $column The pattern of the column names, written as a name or as a string
     */
    public function __construct(public readonly InspectedTable $table, public readonly Name|Text|null $column = null)
    {
    }

    /**
     * Resolves the table and records the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowTargets())->derive($derivation, $this->table);
        (new ShowFacts())->rows($derivation, Report::Columns);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DESCRIBE')->node($this->table);
        if ($this->column instanceof Name) {
            $out->name($this->column, NameUse::Column);
        } else {
            $out->node($this->column);
        }
    }
}
