<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A request to create a foreign table: a table whose rows a foreign server provides.
 *
 * Mirrors PostgreSQL's `CreateForeignTableStmt` (a `CreateStmt` with
 * `servername` and `options`). Like CREATE TABLE it provides a declaration
 * (PG-TABLE-DECLARATION-001); its relation fact resolves to it.
 * Source: https://www.postgresql.org/docs/17/sql-createforeigntable.html.
 *
 * @visibility public
 * @example Declaring a foreign table
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE FOREIGN TABLE f (a int OPTIONS (column_name 'x') NOT NULL) SERVER s OPTIONS (table_name 'y')");
 *     [$create->declarations()[0]->columns[0]->nullability, $create->toString()] // => [\SqlSemantics\Statement\Type\Nullability::NotNull, "CREATE FOREIGN TABLE f (a INT OPTIONS (column_name 'x') NOT NULL) SERVER s OPTIONS (table_name 'y')"]
 */
final class CreateForeignTable implements Relation, \SqlSemantics\Statement\Statement
{
    use Snapshot;

    /**
     * @var list<GenericOption> The options of the foreign table
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The table name
     * @param ListedColumns|PartitionOf $definition The columns: a column list or a parent partitioned table
     * @param Name $server The foreign server
     * @param list<GenericOption> $options The options of the foreign table
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly ListedColumns|PartitionOf $definition,
        public readonly Name $server,
        array $options = [],
        public readonly bool $ifNotExists = false,
    ) {
        $this->options = Check::listOf($options, GenericOption::class, 'Foreign table options are generic options.');
    }

    /**
     * Provides the declaration and derives the definition against it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Definitions())->derive($this, $derivation, null);
    }

    /**
     * Answers the facts of the new table: its row shape and the declaration, without providing it.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Definitions())->fact($this, $derivation, null);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'FOREIGN', 'TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new Spelling())->qualified($out, $this->name);
        $out->node($this->definition)->keyword('SERVER')->name($this->server);
        if ($this->options !== []) {
            $out->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
        }
    }
}
