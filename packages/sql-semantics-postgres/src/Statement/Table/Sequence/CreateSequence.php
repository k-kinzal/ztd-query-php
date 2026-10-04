<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Sequences;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A request to create a sequence.
 *
 * Mirrors PostgreSQL's `CreateSeqStmt` (`sequence` with its persistence, `options`, `if_not_exists`). A
 * sequence is a relation: the statement provides its declaration (PG-SEQUENCE-001) with the columns
 * `last_value` and `log_cnt` (bigint) and `is_called` (boolean), never NULL, and the system columns.
 * Source: https://www.postgresql.org/docs/17/sql-createsequence.html.
 *
 * @visibility public
 * @example Declaring a sequence
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TEMP SEQUENCE IF NOT EXISTS s AS bigint INCREMENT BY 2 START WITH 10 OWNED BY t.id');
 *     [$statement->declarations()[0]->name->schema->value, $statement->declarations()[0]->columns[0]->name->value, $statement->toString()] // => ['pg_temp', 'last_value', 'CREATE TEMP SEQUENCE IF NOT EXISTS s AS BIGINT INCREMENT 2 START 10 OWNED BY t.id']
 */
final class CreateSequence implements SchemaElement
{
    use Snapshot;

    /**
     * @var list<SequenceOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The sequence name
     * @param list<SequenceOption> $options The options in the order written
     * @param Persistence $persistence The persistence
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly QualifiedName $name,
        array $options = [],
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly bool $ifNotExists = false,
    ) {
        $this->options = Check::listOf($options, SequenceOption::class, 'Sequence options are sequence options.');
    }

    /**
     * Answers the schema written on the sequence name.
     */
    public function createdSchema(): ?Name
    {
        return $this->name->schema;
    }

    /**
     * Derives the options and provides the declaration.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Sequences())->create($this, $derivation, null);
    }

    /**
     * Derives the statement inside CREATE SCHEMA: an unqualified sequence belongs to that schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void
    {
        (new Sequences())->create($this, $derivation, $schema);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE')->node($this->persistence)->keyword('SEQUENCE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new Spelling())->qualified($out, $this->name);
        (new Writing())->sequence($out, $this->options);
    }
}
