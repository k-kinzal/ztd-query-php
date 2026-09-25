<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Trigger\RelationTriggerInvariant;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Definition\Trigger\TriggerEvents;
use SqlSemantics\Model\Definition\Trigger\TriggerInvocation;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;

/**
 * Creates an AFTER row trigger whose firing can be deferred like a constraint check.
 * @visibility public
 * @example Reading a deferrable constraint trigger
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE parent(a INT)')))->bind('CREATE CONSTRAINT TRIGGER audit AFTER DELETE ON t FROM parent DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION check_parent()');
 *     $statement->checking // => \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableDeferred
 *     $statement->referenced?->name->parts // => ['public', 'parent']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'CREATE CONSTRAINT TRIGGER "audit" AFTER DELETE ON "public"."t" FROM "public"."parent" DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION "check_parent"()'
 */
final class CreateConstraintTriggerStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly TableReference $table,
        public readonly TriggerEvents $events,
        public readonly TriggerInvocation $invocation,
        public readonly CheckingTime $checking = CheckingTime::Immediate,
        public readonly ?TableReference $referenced = null,
        public readonly ?Expression $condition = null,
    ) {
        RelationTriggerInvariant::identity($origin, $name, $condition);
        if ($events->has(TriggerEvent::Truncate)) {
            throw new InvalidStructure('A constraint trigger fires for each row, which TRUNCATE does not have.');
        }
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
        return new self($origin, $this->name, $this->table, $this->events, $this->invocation, $this->checking, $this->referenced, $this->condition);
    }

    /**
     * Replaces the trigger name.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->table, $this->events, $this->invocation, $this->checking, $this->referenced, $this->condition));
    }

    /**
     * Replaces the row changes that fire the trigger.
     */
    public function withEvents(TriggerEvents $events): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $events, $this->invocation, $this->checking, $this->referenced, $this->condition));
    }

    /**
     * Replaces the executed function and its arguments.
     */
    public function withInvocation(TriggerInvocation $invocation): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->events, $invocation, $this->checking, $this->referenced, $this->condition));
    }

    /**
     * Replaces whether and how the firing is deferred.
     */
    public function withChecking(CheckingTime $checking): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->events, $this->invocation, $checking, $this->referenced, $this->condition));
    }

    /**
     * Replaces or removes the condition, which is rebound against the permitted row images.
     */
    public function withCondition(?Expression $condition): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->table, $this->events, $this->invocation, $this->checking, $this->referenced, $condition));
    }
}
