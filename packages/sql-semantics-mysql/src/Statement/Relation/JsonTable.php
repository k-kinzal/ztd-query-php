<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;

/**
 * The table function `JSON_TABLE(document, path COLUMNS (...)) [AS] alias`.
 *
 * Rule: MYSQL-JSON-TABLE-001. The document expression sees the enclosing
 * queries and the tables to its left in the same FROM clause
 * (MYSQL-FROM-SCOPE-001). The columns are the slots the column definitions
 * contribute, nested paths flattened in order. A table function requires
 * a nonempty correlation name. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the alias of a JSON table
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM JSON_TABLE('[1]', '$[*]' COLUMNS (a INT PATH '$')) AS j");
 *     $query->statement->query->from->alias->value // => 'j'
 */
final class JsonTable implements Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<JsonTableColumn> The column definitions in written order
     */
    public readonly array $columns;

    /**
     * @param Scalar $document The JSON document
     * @param StringLiteral $path The path of the rows
     * @param list<JsonTableColumn> $columns The column definitions; at least one
     * @param Name $alias The nonempty correlation name
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly Scalar $document, public readonly StringLiteral $path, array $columns, public readonly Name $alias, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($mark !== AliasMark::Equals, 'A JSON table alias is written with or without AS.');
        Check::input($alias->value !== '', 'A JSON table requires a nonempty alias.');
        $this->columns = Check::listOf($columns, JsonTableColumn::class, 'JSON_TABLE defines at least one column.', 1);
    }

    /**
     * Derives the document, the path and the columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $derivation->scalar($this->document, $environment);
        $derivation->scalar($this->path, $environment);
        $slots = [];
        foreach ($this->columns as $column) {
            array_push($slots, ...$column->deriveColumns($derivation, $environment));
        }

        return new RelationFact(new RowShape($slots));
    }

    /**
     * Writes the table function.
     */
    public function render(Output $out): void
    {
        $out->keyword('JSON_TABLE')->glue()->symbol('(')->node($this->document)->symbol(',')->node($this->path)
            ->keyword('COLUMNS')->symbol('(')->list($this->columns)->symbol(')')->symbol(')');
        $this->mark->write($out);
        $out->name($this->alias, NameUse::Alias);
    }
}
