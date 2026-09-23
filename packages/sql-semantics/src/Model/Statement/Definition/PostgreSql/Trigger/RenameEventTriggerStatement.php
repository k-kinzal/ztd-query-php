<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Trigger\EventTriggerInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames an event trigger with a required replacement identity.
 * @visibility public
 * @example Reading the required alteration operand
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
 *     $statement->newName // => 'audit_ddl'
 */
final class RenameEventTriggerStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly string $newName,
    ) {
        EventTriggerInvariant::target($origin, $name);
        EventTriggerInvariant::target($origin, $newName);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the operation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->newName);
    }

    /**
     * Replaces the target event-trigger identity without applying the alteration.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->newName));
    }

    /**
     * Replaces the required alteration operand in a separately validated statement.
     */
    public function withNewName(string $newName): self
    {
        return $this->changed(new self($this->origin, $this->name, $newName));
    }
}
