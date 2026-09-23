<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Definition\Foreign\UserMappingIdentity;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Modifies one mapping through a nonempty ordered list of explicit option changes.
 * @visibility public
 * @example Inspecting the mapping's foreign server
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
 *     $statement->target->server // => 'remote'
 * @example Rejecting an empty modification
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
 *     $statement->withOptions([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 * @example Rejecting an option value without an operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER USER MAPPING FOR USER SERVER remote OPTIONS (DROP old)');
 *     $statement->withOptions(['DROP old']); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AlterUserMappingStatement extends BoundStatement
{
    /**
     * @var non-empty-list<AddForeignOption|SetForeignOption|DropForeignOption>
     */
    public readonly array $options;

    /**
     * @param list<AddForeignOption|SetForeignOption|DropForeignOption> $options Ordered option changes
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly UserMappingIdentity $target, array $options)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('User mappings require PostgreSQL.');
        }
        Collections::alternatives($options, [AddForeignOption::class, SetForeignOption::class, DropForeignOption::class]);
        $this->options = Collections::nonEmpty($options);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the mapping request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->target, $this->options);
    }

    /**
     * Replaces the complete user and foreign-server identity together.
     */
    public function withTarget(UserMappingIdentity $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->options));
    }

    /**
     * @param list<AddForeignOption|SetForeignOption|DropForeignOption> $options Replacement nonempty ordered changes
     */
    public function withOptions(array $options): self
    {
        return $this->changed(new self($this->origin, $this->target, $options));
    }
}
