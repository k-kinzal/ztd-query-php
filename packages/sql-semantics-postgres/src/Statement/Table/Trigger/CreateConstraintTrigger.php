<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Triggers;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A request to create a constraint trigger: an AFTER ROW trigger whose firing can be deferred.
 *
 * Mirrors PostgreSQL's `CreateTrigStmt` with `isconstraint` (`constrrel`, `deferrable`, `initdeferred`). The
 * table and the referenced table are resolved; the WHEN condition sees the table's columns as OLD and NEW
 * (PG-TRIGGER-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility public
 * @example Reading a constraint trigger
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t FROM u DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION f()');
 *     $statement->toString() // => 'CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t FROM u DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION f()'
 */
final class CreateConstraintTrigger implements SchemaElement, Relation
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
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param Name $name The trigger name
     * @param list<TriggerEvent> $events The events
     * @param QualifiedName $table The table
     * @param DottedName $function The trigger function
     * @param list<TriggerArgument> $arguments The arguments
     * @param ParentTable|null $referenced The table of FROM, referenced by a foreign key
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     * @param Scalar|null $when The WHEN condition
     * @param bool $replace Whether OR REPLACE is written
     */
    public function __construct(
        public readonly Name $name,
        array $events,
        public readonly QualifiedName $table,
        public readonly DottedName $function,
        array $arguments = [],
        public readonly ?ParentTable $referenced = null,
        array $attributes = [],
        public readonly ?Scalar $when = null,
        public readonly bool $replace = false,
    ) {
        $this->events = Check::listOf($events, TriggerEvent::class, 'A trigger fires on at least one event.', 1);
        $this->arguments = Check::listOf($arguments, TriggerArgument::class, 'Trigger arguments are trigger arguments.');
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Answers the schema written on the table, or null when it is unqualified.
     */
    public function createdSchema(): ?Name
    {
        return $this->table->schema;
    }

    /**
     * Resolves the tables, derives the condition and checks the attributes.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Triggers())->deriveConstraint($this, $derivation);
    }

    /**
     * Derives the statement inside CREATE SCHEMA: an unqualified table is in that schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void
    {
        (new Triggers())->deriveConstraint($this, $derivation, $schema);
    }

    /**
     * Resolves the table and answers its facts.
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
        (new Triggers())->writeConstraint($out, $this);
    }
}
