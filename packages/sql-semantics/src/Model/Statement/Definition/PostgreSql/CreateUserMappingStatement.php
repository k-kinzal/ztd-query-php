<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Definition\Foreign\WrapperInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Declares a mapping with initial text options and a creation policy.
 * @visibility public
 * @example Inspecting the mapping's foreign server
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE USER MAPPING FOR USER SERVER remote');
 *     $statement->target->server // => 'remote'
 */
final class CreateUserMappingStatement extends BoundStatement
{
    /**
     * @param list<ForeignOption> $options Ordered initial options
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly UserMappingIdentity $target, public readonly array $options = [], public readonly bool $ifNotExists = false)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('User mappings require PostgreSQL.');
        }
        WrapperInvariant::options($options);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the mapping request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->target, $this->options, $this->ifNotExists);
    }

    /**
     * Replaces the complete user and foreign-server identity together.
     */
    public function withTarget(UserMappingIdentity $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->options, $this->ifNotExists));
    }

    /**
     * @param list<ForeignOption> $options Replacement initial options
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->target, $options, $this->ifNotExists));
    }

    /**
     * Replaces the mapping's existence policy without changing its target.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->options, $ifNotExists));
    }
}
