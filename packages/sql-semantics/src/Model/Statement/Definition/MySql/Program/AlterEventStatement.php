<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Program;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Stored\EventAlteration;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * ALTER EVENT: changes the schedule, completion policy, name, status, comment, body or definer of an existing event.
 * @visibility public
 * @example Inspecting an event change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER DEFINER = 'ops'@'%' EVENT e ON COMPLETION PRESERVE");
 *     [$statement->definer->username, $statement->changes->completion->value] // => ['ops', 'ON COMPLETION PRESERVE']
 */
final class AlterEventStatement extends BoundStatement
{
    /**
     * Requires the event name and at least one requested change.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly EventAlteration $changes, public readonly AccountName|CurrentAccount|null $definer = null)
    {
        ProgramInvariant::definition($origin, $name, false, ProgramKind::Event);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the requested changes while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->changes, $this->definer);
    }

    /**
     * Replaces the altered event's name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->changes, $this->definer));
    }

    /**
     * Replaces all requested changes together.
     */
    public function withChanges(EventAlteration $changes): self
    {
        return $this->changed(new self($this->origin, $this->name, $changes, $this->definer));
    }

    /**
     * Replaces or removes the new definer account.
     */
    public function withDefiner(AccountName|CurrentAccount|null $definer): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->changes, $definer));
    }
}
