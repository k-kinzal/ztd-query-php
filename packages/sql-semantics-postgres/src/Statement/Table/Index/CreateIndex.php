<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Indexes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;

/**
 * A request to create an index.
 *
 * Mirrors PostgreSQL's `IndexStmt` (`unique`, `concurrent`, `if_not_exists`,
 * `idxname`, `relation`, `accessMethod`, `indexParams`,
 * `indexIncludingParams`, `nulls_not_distinct`, `options`, `tableSpace`,
 * `whereClause`). Rule PG-INDEX-001: the indexed table is resolved
 * (PG-TABLE-TARGET-001) and is the relation fact of the statement; the keys,
 * the included columns and the predicate are derived where that table is the
 * only visible relation, the predicate as a condition (PG-TABLE-CONDITION-001).
 * An index declares nothing a query can read.
 * Source: https://www.postgresql.org/docs/17/sql-createindex.html.
 *
 * @visibility SqlSemantics
 */
final class CreateIndex implements SchemaElement, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<IndexElement> The keys
     */
    public readonly array $elements;

    /**
     * @var list<IndexElement> The included columns
     */
    public readonly array $included;

    /**
     * @var list<Definition> The storage parameters
     */
    public readonly array $options;

    /**
     * @param Name|null $name The index name; required with IF NOT EXISTS
     * @param RelationReference $table The indexed table
     * @param list<IndexElement> $elements The keys; at least one
     * @param bool $unique Whether UNIQUE is written
     * @param bool $concurrently Whether CONCURRENTLY is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param Name|null $method The access method
     * @param list<IndexElement> $included The included columns
     * @param bool|null $nullsDistinct True for NULLS DISTINCT, false for NULLS NOT DISTINCT, null when not written
     * @param list<Definition> $options The storage parameters
     * @param Name|null $tablespace The tablespace
     * @param Scalar|null $where The predicate of a partial index
     */
    public function __construct(
        public readonly ?Name $name,
        public readonly RelationReference $table,
        array $elements,
        public readonly bool $unique = false,
        public readonly bool $concurrently = false,
        public readonly bool $ifNotExists = false,
        public readonly ?Name $method = null,
        array $included = [],
        public readonly ?bool $nullsDistinct = null,
        array $options = [],
        public readonly ?Name $tablespace = null,
        public readonly ?Scalar $where = null,
    ) {
        $this->elements = Check::listOf($elements, IndexElement::class, 'An index has at least one key.', 1);
        $this->included = Check::listOf($included, IndexElement::class, 'Included columns are index elements.');
        $this->options = Check::listOf($options, Definition::class, 'Index storage parameters are definitions.');
        Check::input(!$ifNotExists || $name !== null, 'IF NOT EXISTS needs an index name.');
    }

    /**
     * Answers the schema written on the indexed table.
     */
    public function createdSchema(): ?Name
    {
        return $this->table->name->schema;
    }

    /**
     * Resolves the table and derives the keys and the predicate against it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Indexes())->derive($this, $derivation, null);
    }

    /**
     * Derives the statement inside CREATE SCHEMA: an unqualified table is in that schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void
    {
        (new Indexes())->derive($this, $derivation, $schema);
    }

    /**
     * Resolves the indexed table as written and answers its facts.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->resolve($derivation, $this->table->name);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        (new Indexes())->write($out, $this);
    }
}
