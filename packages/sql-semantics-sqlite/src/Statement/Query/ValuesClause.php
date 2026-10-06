<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Expression\ProgramOnly;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A VALUES clause: a query whose rows are written out.
 *
 * Rule: SQLITE-VALUES-001. The values see the enclosing queries and no
 * relation of their own. The result has the width of the first row; a row of
 * another width is reported. SQLite names the result columns `column1`,
 * `column2`, and so on. The type of a column is the choice over its values
 * and it can be NULL when one of its values can.
 * Source: https://sqlite.org/lang_select.html#the_values_clause. Status: Implemented.
 *
 * @visibility public
 * @example Reading the output of a VALUES clause
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("VALUES (1, 'a'), (2, NULL)");
 *     [count($query->statement->rows), $query->field(1)->name->value, $query->field(1)->nullability] // => [2, 'column2', \SqlSemantics\Statement\Type\Nullability::Nullable]
 */
final class ValuesClause implements Statement, Query
{
    use Snapshot;

    /**
     * @var non-empty-list<ValueRow> The rows in written order
     */
    public readonly array $rows;

    /**
     * @param list<ValueRow> $rows The rows in written order; at least one
     */
    public function __construct(array $rows)
    {
        $this->rows = Check::listOf($rows, ValueRow::class, 'A VALUES clause has at least one row.', 1);
    }

    /**
     * Derives the clause as a statement root and records its rows as the output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ProgramOnly())->outsideProgram($this, $derivation);
        $derivation->output($derivation->query($this, $derivation->environment()));
    }

    /**
     * Derives every value and the output fields.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        $environment = new Environment($derivation->context, $outer);
        $width = count($this->rows[0]->values);
        $types = array_fill(0, $width, []);
        $nullability = array_fill(0, $width, Nullability::NotNull);
        $reported = false;
        foreach ($this->rows as $row) {
            foreach ($row->values as $position => $value) {
                $fact = $derivation->scalar($value, $environment);
                if ($position < $width) {
                    $types[$position][] = $fact->type;
                    $nullability[$position] = $nullability[$position]->propagate($fact->nullability);
                }
            }
            if (count($row->values) !== $width && !$reported) {
                $derivation->report(new ArityMismatch(ArityRule::ValueRows, $width, count($row->values)));
                $reported = true;
            }
        }
        $fields = [];
        foreach ($this->rows[0]->values as $position => $value) {
            $fields[] = new Field($position, new OutputSlot(new Name('column' . ($position + 1)), (new Storages())->either($types[$position]), $nullability[$position]), count($this->rows) === 1 ? $value : null);
        }

        return new QueryFact($fields, $derivation->context->columnNames);
    }

    /**
     * Writes the rows.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES')->list($this->rows);
    }
}
