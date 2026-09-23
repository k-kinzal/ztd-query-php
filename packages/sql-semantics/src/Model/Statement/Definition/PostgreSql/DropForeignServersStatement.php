<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Foreign\ServerInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests removal of named foreign servers and records dependent-object behavior.
 * @visibility public
 * @example Inspecting all removal targets
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP SERVER one, two CASCADE');
 *     $statement->names // => ['one', 'two']
 * @example Rejecting an empty selection
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignServersStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropForeignServersStatement extends BoundStatement
{
    /**
     * @param non-empty-list<string> $names Unqualified target identities
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $names,
        public readonly bool $ifExists = false,
        public readonly DropBehavior $behavior = DropBehavior::Default,
    ) {
        Collections::strings(Collections::nonEmpty($names));
        foreach ($names as $name) {
            ServerInvariant::target($origin, $name);
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->names, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the target identities in a separately validated request.
     * @param non-empty-list<string> $names Replacement target identities
     */
    public function withNames(array $names): self
    {
        return $this->changed(new self($this->origin, $names, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the behavior when a target does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->names, $ifExists, $this->behavior));
    }

    /**
     * Replaces the handling of dependent objects.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->names, $this->ifExists, $behavior));
    }
}
