<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Sequences;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change the options of a sequence.
 *
 * Mirrors PostgreSQL's `AlterSeqStmt` (`sequence`, `options`, `missing_ok`). The sequence is resolved (PG-
 * TABLE-TARGET-001) and is the relation fact of the statement; the statement changes no declaration.
 * Source: https://www.postgresql.org/docs/17/sql-altersequence.html.
 *
 * @visibility public
 * @example Changing a sequence
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SEQUENCE IF EXISTS s RESTART WITH 1 NO MAXVALUE');
 *     $statement->toString() // => 'ALTER SEQUENCE IF EXISTS s RESTART 1 NO MAXVALUE'
 */
final class AlterSequence implements Statement, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<SequenceOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The sequence
     * @param list<SequenceOption> $options The options in the order written
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(public readonly QualifiedName $name, array $options, public readonly bool $ifExists = false)
    {
        $this->options = Check::listOf($options, SequenceOption::class, 'ALTER SEQUENCE takes at least one option.', 1);
    }

    /**
     * Resolves the sequence and derives the options.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->relation($this, $derivation->environment());
        (new Sequences())->options($this->options, $derivation);
    }

    /**
     * Resolves the sequence and answers its facts.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->existing($derivation, $this->name, $this->ifExists);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'SEQUENCE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        (new Spelling())->qualified($out, $this->name);
        (new Writing())->sequence($out, $this->options);
    }
}
