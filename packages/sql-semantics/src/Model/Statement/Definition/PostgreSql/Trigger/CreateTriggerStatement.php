<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Trigger\RelationTriggerInvariant;
use SqlSemantics\Model\Definition\Trigger\TransitionTable;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Definition\Trigger\TriggerInvocation;
use SqlSemantics\Model\Definition\Trigger\TriggerLevel;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates or replaces a PostgreSQL trigger that runs a function when a table changes.
 * @visibility public
 * @example Reading a relation trigger
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CREATE OR REPLACE TRIGGER audit BEFORE UPDATE ON t FOR EACH ROW WHEN (OLD.a <> NEW.a) EXECUTE FUNCTION log_change()');
 *     $statement->name // => 'audit'
 *     $statement->table->name->parts // => ['public', 't']
 *     $statement->timing // => \SqlSemantics\Model\Trigger\Timing::Before
 *     $statement->orReplace // => true
 *     $statement->toString() // => 'CREATE OR REPLACE TRIGGER "audit" BEFORE UPDATE ON "public"."t" FOR EACH ROW WHEN (("old"."a" <> "new"."a")) EXECUTE FUNCTION "log_change"()'
 */
final class CreateTriggerStatement extends BoundStatement
{
    /**
     * @param list<TransitionTable> $transitions
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly TableReference $table,
        public readonly Timing $timing,
        public readonly TriggerEvents $events,
        public readonly TriggerLevel $level,
        public readonly TriggerInvocation $invocation,
        public readonly array $transitions = [],
        public readonly ?Expression $condition = null,
        public readonly bool $orReplace = false,
    ) {
        RelationTriggerInvariant::identity($origin, $name, $condition);
        RelationTriggerInvariant::firing($timing, $events, $level, $condition);
        RelationTriggerInvariant::transitions($timing, $events, $transitions);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->table, $this->timing, $this->events, $this->level, $this->invocation, $this->transitions, $this->condition, $this->orReplace);
    }

    /**
     * Replaces the trigger name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->timing, $this->events, $this->level, $this->invocation, $this->transitions, $this->condition, $this->orReplace));
    }

    /**
     * Replaces when the trigger fires relative to the change.
     */
    public function withTiming(Timing $timing): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $timing, $this->events, $this->level, $this->invocation, $this->transitions, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the changes that fire the trigger.
     */
    public function withEvents(TriggerEvents $events): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->timing, $events, $this->level, $this->invocation, $this->transitions, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the per-row or per-statement granularity.
     */
    public function withLevel(TriggerLevel $level): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->timing, $this->events, $level, $this->invocation, $this->transitions, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the executed function and its arguments.
     */
    public function withInvocation(TriggerInvocation $invocation): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->timing, $this->events, $this->level, $invocation, $this->transitions, $this->condition, $this->orReplace));
    }

    /**
     * Replaces the transition tables.
     * @param list<TransitionTable> $transitions
     */
    public function withTransitions(array $transitions): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->timing, $this->events, $this->level, $this->invocation, $transitions, $this->condition, $this->orReplace));
    }

    /**
     * Replaces or removes the firing condition, which is rebound against the permitted row images.
     */
    public function withCondition(?Expression $condition): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->timing, $this->events, $this->level, $this->invocation, $this->transitions, $condition, $this->orReplace));
    }

    /**
     * Chooses whether an existing trigger of the same name is replaced.
     */
    public function withOrReplace(bool $orReplace): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->timing, $this->events, $this->level, $this->invocation, $this->transitions, $this->condition, $orReplace));
    }
}
