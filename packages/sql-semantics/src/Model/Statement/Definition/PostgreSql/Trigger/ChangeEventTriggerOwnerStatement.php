<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Trigger\EventTriggerInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Transfers a named event trigger to a required role identity.
 * @visibility public
 * @example Reading the required alteration operand
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER');
 *     $statement->newOwner->value // => 'CURRENT_USER'
 */
final class ChangeEventTriggerOwnerStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly string $name,
        public readonly NamedRole|SessionRole $newOwner,
    ) {
        EventTriggerInvariant::target($origin, $name);
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
        return new self($origin, $this->name, $this->newOwner);
    }

    /**
     * Replaces the target event-trigger identity without applying the alteration.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->newOwner));
    }

    /**
     * Replaces the required alteration operand in a separately validated statement.
     */
    public function withNewOwner(NamedRole|SessionRole $newOwner): self
    {
        return $this->changed(new self($this->origin, $this->name, $newOwner));
    }
}
