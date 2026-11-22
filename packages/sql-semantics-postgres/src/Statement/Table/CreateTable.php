<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Definitions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TableForm;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionSpec;

/**
 * A request to create a table.
 *
 * Mirrors PostgreSQL's `CreateStmt` (`relation` with its persistence,
 * `tableElts`/`inhRelations`, `ofTypename` or `partbound`, `partspec`,
 * `accessMethod`, `options`, `oncommit`, `tablespacename`,
 * `if_not_exists`). The statement provides one declaration
 * (PG-TABLE-DECLARATION-001) and is the one relation occurrence of the new
 * table: its relation fact resolves to that declaration. The expressions of
 * the definition are derived where that table is the only visible relation
 * (PG-TABLE-DEFINITION-001). The optional WITHOUT OIDS clause is a no-op and
 * is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class CreateTable implements SchemaElement, Relation
{
    use Snapshot;

    /**
     * @var list<Definition> The storage parameters
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The table name
     * @param TableForm $definition The columns: a column list, a composite type or a parent partitioned table
     * @param Persistence $persistence The persistence
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param PartitionSpec|null $partitioning The PARTITION BY clause
     * @param Name|null $method The table access method
     * @param list<Definition> $options The storage parameters
     * @param OnCommit|null $onCommit The ON COMMIT action
     * @param Name|null $tablespace The tablespace
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly TableForm $definition,
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly bool $ifNotExists = false,
        public readonly ?PartitionSpec $partitioning = null,
        public readonly ?Name $method = null,
        array $options = [],
        public readonly ?OnCommit $onCommit = null,
        public readonly ?Name $tablespace = null,
    ) {
        $this->options = Check::listOf($options, Definition::class, 'Storage parameters are definitions.');
    }

    /**
     * Answers the schema written on the table name.
     */
    public function createdSchema(): ?Name
    {
        return $this->name->schema;
    }

    /**
     * Provides the declaration and derives the definition against it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Definitions())->derive($this, $derivation, null);
    }

    /**
     * Derives the statement inside CREATE SCHEMA: an unqualified table belongs to that schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void
    {
        (new Definitions())->derive($this, $derivation, $schema);
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
        $out->keyword('CREATE')->node($this->persistence)->keyword('TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new Spelling())->qualified($out, $this->name);
        $out->node($this->definition)->node($this->partitioning);
        if ($this->method !== null) {
            $out->keyword('USING')->name($this->method);
        }
        if ($this->options !== []) {
            $out->keyword('WITH');
            (new Writing())->definitions($out, $this->options);
        }
        if ($this->onCommit !== null) {
            $out->keyword('ON', 'COMMIT', ...$this->onCommit->keywords());
        }
        if ($this->tablespace !== null) {
            $out->keyword('TABLESPACE')->name($this->tablespace);
        }
    }
}
