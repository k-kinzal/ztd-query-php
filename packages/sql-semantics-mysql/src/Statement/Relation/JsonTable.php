<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;

/**
 * The table function `JSON_TABLE(document, path COLUMNS (...)) [AS] alias`.
 *
 * Rule: MYSQL-JSON-TABLE-001. The document expression sees the enclosing
 * queries and the tables to its left in the same FROM clause
 * (MYSQL-FROM-SCOPE-001). The columns are the slots the column definitions
 * contribute, nested paths flattened in order. A table function without
 * alias is reported. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
     * @param Name|null $alias The correlation name
     * @param AliasMark $mark What is written before the alias
     */
    public function __construct(public readonly Scalar $document, public readonly StringLiteral $path, array $columns, public readonly ?Name $alias = null, public readonly AliasMark $mark = AliasMark::As)
    {
        Check::input($alias !== null || $mark === AliasMark::As, 'A relation without alias has no alias mark.');
        $this->columns = Check::listOf($columns, JsonTableColumn::class, 'JSON_TABLE defines at least one column.', 1);
    }

    /**
     * Derives the document, the path and the columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        if ($this->alias === null) {
            $derivation->report(new Misuse(MisuseRule::TableFunctionWithoutAlias));
        }
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
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
    }
}
