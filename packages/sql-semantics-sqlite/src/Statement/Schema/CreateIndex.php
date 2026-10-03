<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create an index on a table.
 *
 * Rule: SQLITE-CREATE-INDEX-001. The table is named without a schema and
 * belongs to the schema of the index name; without one it is found on the
 * search path. Its resolution is the relation fact of the statement node: the
 * node is the one occurrence of the indexed table, and the indexed
 * expressions and the WHERE condition of a partial index are derived at a
 * position whose only visible relation is that table: the condition sees
 * its columns and its row identifier, an indexed expression sees its
 * columns only, as SQLite does not resolve `rowid` there. A missing or
 * conflicting table, a missing column, and a bound parameter, a subquery, a
 * qualified column reference or a non-deterministic function in an indexed
 * expression, or a bound parameter, a subquery or a non-deterministic
 * function in the condition, are diagnostics (SQLITE-DEFINITION-LIMITS-001).
 * Remaining assumption: SQLite reads a string literal written as a whole
 * term as a column name; the model derives it as the text it is. Indexes are
 * not part of a declaration context: the statement provides no declaration,
 * and whether the index name is free is not a fact.
 * Source: https://sqlite.org/lang_createindex.html, https://sqlite.org/partialindex.html,
 * https://sqlite.org/expridx.html. Status: Implemented.
 *
 * @visibility public
 * @example Resolving the indexed table and an indexed column
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a, b)');
 *     $index = $semantics->analyze('CREATE UNIQUE INDEX i ON t (b DESC) WHERE a IS NOT NULL', [$table]);
 *     [$index->facts->relation($index->statement)->table->table === $table->declarations()[0], $index->facts->scalar($index->statement->terms[0]->expression)->resolution->slot->column === $table->declarations()[0]->columns[1]] // => [true, true]
 */
final class CreateIndex implements Statement, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<SortTerm> The indexed columns and expressions in order
     */
    public readonly array $terms;

    /**
     * @param QualifiedName $name The index name; its schema is the schema of the table
     * @param Name $table The indexed table
     * @param list<SortTerm> $terms The indexed columns and expressions in order; at least one
     * @param Scalar|null $where The condition of a partial index
     * @param bool $unique Whether UNIQUE is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Name $table,
        array $terms,
        public readonly ?Scalar $where = null,
        public readonly bool $unique = false,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'An index name has at most a schema qualifier.');
        $this->terms = Check::listOf($terms, SortTerm::class, 'An index has at least one term.', 1);
    }

    /**
     * Answers the name of the indexed table with the schema the index name gives it.
     */
    public function indexed(): QualifiedName
    {
        return new QualifiedName($this->table, $this->name->schema);
    }

    /**
     * Resolves the indexed table and derives the indexed expressions and the condition against it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $shapes = new TableShapes();
        $fact = $derivation->relation($this, $derivation->environment());
        $scope = $shapes->scope($derivation, $this, new QualifiedName($this->table), $fact->shape, $shapes->implicitOf($fact));
        $limits = new Limits();
        foreach ($this->terms as $term) {
            $derivation->scalar($term->expression, $scope->columns);
            $limits->report($term->expression, DefinitionPosition::IndexExpression, $derivation);
        }
        if ($this->where !== null) {
            $derivation->scalar($this->where, $scope->row);
            $limits->report($this->where, DefinitionPosition::PartialIndexWhere, $derivation);
        }
    }

    /**
     * Resolves the indexed table and derives its row shape.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new TableShapes())->target($derivation, $this->indexed());
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->unique) {
            $out->keyword('UNIQUE');
        }
        $out->keyword('INDEX');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ObjectNames())->write($out, $this->name);
        $out->keyword('ON')->name($this->table, NameUse::Relation)->symbol('(')->list($this->terms)->symbol(')');
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
