<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Program;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrder;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Trigger\WriteEvent;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL CREATE TRIGGER: a row-level program run BEFORE or AFTER each INSERT, UPDATE or DELETE of one table.
 * @visibility public
 * @example Inspecting a MySQL trigger
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW DELETE FROM t WHERE n = OLD.n');
 *     [$statement->timing->value, $statement->event->value, $statement->table->declaration->name] // => ['AFTER', 'DELETE', 't']
 */
final class CreateTriggerStatement extends BoundStatement
{
    /**
     * Requires BEFORE or AFTER timing, FOLLOWS or PRECEDES only from MySQL 5.7, and a body without RETURN.
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly Timing $timing,
        public readonly WriteEvent $event,
        public readonly TableReference $table,
        public readonly ProgramStatement $body,
        public readonly ?TriggerOrder $order = null,
        public readonly AccountName|CurrentAccount|null $definer = null,
        public readonly bool $ifNotExists = false,
    ) {
        ProgramInvariant::definition($origin, $name, $ifNotExists, ProgramKind::Trigger);
        if ($timing === Timing::InsteadOf || ($order !== null && ProgramInvariant::before($origin, 'mysql-5.7.44'))) {
            throw new InvalidStructure('A MySQL trigger fires BEFORE or AFTER its event, and FOLLOWS or PRECEDES requires MySQL 5.7.');
        }
        ProgramInvariant::body($origin, $body, ProgramKind::Trigger);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the trigger definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->timing, $this->event, $this->table, $this->body, $this->order, $this->definer, $this->ifNotExists);
    }

    /**
     * Replaces the trigger name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->timing, $this->event, $this->table, $this->body, $this->order, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the firing time; the body is revalidated for the row images it may change.
     */
    public function withTiming(Timing $timing): self
    {
        return $this->changed(new self($this->origin, $this->name, $timing, $this->event, $this->table, $this->body, $this->order, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the triggering write; the body is revalidated for the row images the event supplies.
     */
    public function withEvent(WriteEvent $event): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->timing, $event, $this->table, $this->body, $this->order, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces the body.
     */
    public function withBody(ProgramStatement $body): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->timing, $this->event, $this->table, $body, $this->order, $this->definer, $this->ifNotExists));
    }

    /**
     * Replaces or removes the position relative to another trigger.
     */
    public function withOrder(?TriggerOrder $order): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->timing, $this->event, $this->table, $this->body, $order, $this->definer, $this->ifNotExists));
    }
}
