<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Relation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Triggers;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;

/**
 * A request to create a trigger.
 *
 * Mirrors PostgreSQL's `CreateTrigStmt` without `isconstraint` (`replace`, `trigname`, `relation`,
 * `funcname`, `args`, `row`, `timing`, `events`, `whenClause`, `transitionRels`). Rule PG-TRIGGER-001: the
 * table is resolved and is the relation fact of the statement; the WHEN condition of a row trigger sees the
 * table's columns as OLD and NEW, and that of a statement trigger sees no column. FOR [ EACH ] — EACH is
 * optional — and PROCEDURE for FUNCTION are not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility SqlSemantics
 */
final class CreateTrigger implements SchemaElement, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<TriggerEvent> The events
     */
    public readonly array $events;

    /**
     * @var list<TriggerArgument> The arguments
     */
    public readonly array $arguments;

    /**
     * @var list<TriggerTransition> The REFERENCING items
     */
    public readonly array $transitions;

    /**
     * @param Name $name The trigger name
     * @param TriggerTiming $timing When the trigger fires
     * @param list<TriggerEvent> $events The events
     * @param QualifiedName $table The table, view or foreign table
     * @param DottedName $function The trigger function
     * @param list<TriggerArgument> $arguments The arguments
     * @param bool|null $row True for FOR EACH ROW, false for FOR EACH STATEMENT, null when not written (statement level)
     * @param list<TriggerTransition> $transitions The REFERENCING items
     * @param Scalar|null $when The WHEN condition
     * @param bool $replace Whether OR REPLACE is written
     */
    public function __construct(
        public readonly Name $name,
        public readonly TriggerTiming $timing,
        array $events,
        public readonly QualifiedName $table,
        public readonly DottedName $function,
        array $arguments = [],
        public readonly ?bool $row = null,
        array $transitions = [],
        public readonly ?Scalar $when = null,
        public readonly bool $replace = false,
    ) {
        $this->events = Check::listOf($events, TriggerEvent::class, 'A trigger fires on at least one event.', 1);
        $this->arguments = Check::listOf($arguments, TriggerArgument::class, 'Trigger arguments are trigger arguments.');
        $this->transitions = Check::listOf($transitions, TriggerTransition::class, 'REFERENCING items are transitions.');
    }

    /**
     * Answers the schema written on the table.
     */
    public function createdSchema(): ?Name
    {
        return $this->table->schema;
    }

    /**
     * Resolves the table and derives the condition.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Triggers())->derive($this, $derivation, null);
    }

    /**
     * Derives the statement inside CREATE SCHEMA: an unqualified table is in that schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void
    {
        (new Triggers())->derive($this, $derivation, $schema);
    }

    /**
     * Resolves the table as written and answers its facts.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new Targets())->resolve($derivation, $this->table);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        (new Triggers())->write($out, $this);
    }
}
