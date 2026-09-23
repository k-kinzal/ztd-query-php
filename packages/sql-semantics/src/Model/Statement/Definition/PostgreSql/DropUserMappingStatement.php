<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes a mapping without option values or modification payloads.
 * @visibility public
 * @example Inspecting the mapping's foreign server
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP USER MAPPING FOR USER SERVER remote');
 *     $statement->target->server // => 'remote'
 */
final class DropUserMappingStatement extends BoundStatement
{
    /**
     * Selects one mapping with an explicit existence policy.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly UserMappingIdentity $target, public readonly bool $ifExists = false)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('User mappings require PostgreSQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the mapping request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->target, $this->ifExists);
    }

    /**
     * Replaces the complete user and foreign-server identity together.
     */
    public function withTarget(UserMappingIdentity $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->ifExists));
    }

    /**
     * Replaces the mapping's existence policy without changing its target.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->target, $ifExists));
    }
}
