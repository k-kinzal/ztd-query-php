<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Describes removal of one named MySQL database without executing it.
 * @visibility public
 * @example Inspecting the deletion target
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP DATABASE target');
 *     $statement->name // => 'target'
 */
final class DropDatabaseStatement extends BoundStatement
{
    /**
     * Keeps the identifier separate from the operation's removal policy.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly bool $ifExists = false)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This named removal form requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the target and policy while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists);
    }

    /**
     * Replaces the target in a separately validated statement.
     */
    public function withName(string $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists));
    }

    /**
     * Selects the behavior when the named definition does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists));
    }
}
