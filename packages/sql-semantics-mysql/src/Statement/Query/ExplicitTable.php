<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * The TABLE statement: every row and column of one table (MySQL 8.0.19 and later).
 *
 * It is both the query and the occurrence of the table it reads.
 *
 * Rule: MYSQL-EXPLICIT-TABLE-001. The name resolves like a table reference
 * (MYSQL-TABLE-SHAPES-001); the output fields are the columns of the table
 * in order. Source: https://dev.mysql.com/doc/refman/8.4/en/table.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the table of a TABLE statement
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('TABLE shop.t');
 *     [$query->statement->query->name()->schema?->value, $query->statement->query->name()->name->value] // => ['shop', 't']
 */
final class ExplicitTable implements Query, NamedRelation
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table name with its optional database
     */
    public function __construct(public readonly QualifiedName $table)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers the table name.
     */
    public function name(): QualifiedName
    {
        return $this->table;
    }

    /**
     * Answers no correlation name: the TABLE statement takes none.
     */
    public function alias(): ?Name
    {
        return null;
    }

    /**
     * Resolves the table and answers its columns as the output.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new TableShapes())->explicit($this, $derivation->relation($this, $outer), $derivation);
    }

    /**
     * Resolves the name and derives the row shape of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableShapes())->named($this->table, $derivation, $environment);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('TABLE');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
    }
}
