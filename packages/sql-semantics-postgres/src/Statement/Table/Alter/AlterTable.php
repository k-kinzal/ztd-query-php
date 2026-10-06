<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change a table, an index, a sequence, a view, a materialized view or a foreign table.
 *
 * Mirrors PostgreSQL's `AlterTableStmt` (`relation`, `cmds`, `objtype`, `missing_ok`). The relation is
 * resolved (PG-TABLE-TARGET-001) and is the relation fact of the statement; the actions are derived against
 * it (PG-ALTER-TABLE-001). The statement changes no declaration. ONLY is written only for tables and foreign
 * tables; a partition action stands alone.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Resolving the altered table
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE IF EXISTS ONLY t ADD c int CHECK (c > a), ALTER b DROP NOT NULL');
 *     $statement->facts->relation($statement->statement)->table::class // => 'SqlSemantics\\Statement\\Reference\\Table\\UndeclaredTable'
 */
final class AlterTable implements Statement, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<AlterCommand> The actions in order
     */
    public readonly array $commands;

    /**
     * @param AlterTarget $target The kind of relation
     * @param RelationReference $relation The relation
     * @param list<AlterCommand> $commands The actions in order
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(
        public readonly AlterTarget $target,
        public readonly RelationReference $relation,
        array $commands,
        public readonly bool $ifExists = false,
    ) {
        $this->commands = Check::listOf($commands, AlterCommand::class, 'ALTER takes at least one action.', 1);
        Check::input((new Alterations())->admits($this->commands), 'A partition action stands alone.');
        Check::input(!$relation->only || $target === AlterTarget::Table || $target === AlterTarget::ForeignTable, 'ONLY is written for tables and foreign tables.');
    }

    /**
     * Resolves the relation and derives the actions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Alterations())->derive($this, $derivation);
    }

    /**
     * Resolves the relation and answers its facts.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->existing($derivation, $this->relation->name, $this->ifExists);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', ...$this->target->keywords());
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->node($this->relation)->list($this->commands);
    }
}
