<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TableDropScope;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Deletes named tables, retaining temporary-table selection and dependency behavior.
 * @visibility public
 * @example Inspecting a temporary-table request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP TEMPORARY TABLE IF EXISTS tmp');
 *     $statement->selection === \SqlSemantics\Model\Definition\TableDropScope::Temporary // => true
 * @example Rejecting an empty deletion target list
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP TABLE IF EXISTS t');
 *     new \SqlSemantics\Model\Statement\Definition\DropTableStatement($statement->origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropTableStatement extends BoundStatement
{
    /**
     * @param non-empty-list<QualifiedName> $names Ordered table names to resolve at execution
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $names,
        public readonly bool $ifExists = false,
        public readonly DropBehavior $behavior = DropBehavior::Default,
        public readonly TableDropScope $selection = TableDropScope::Visible,
    ) {
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
        if ($selection === TableDropScope::Temporary && $origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A temporary-only table deletion requires MySQL.');
        }
        if ($origin->dialect === Dialect::Sqlite && (count($names) !== 1 || $behavior !== DropBehavior::Default)) {
            throw new InvalidStructure('SQLite table deletion requires one table without a dependency policy.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Drop;
    }

    /**
     * Retains the target selection and policies when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->names, $this->ifExists, $this->behavior, $this->selection);
    }

    /**
     * Replaces the affected names while preserving selection and existence policies.
     * @param non-empty-list<QualifiedName> $names
     */
    public function withNames(array $names): self
    {
        return $this->changed(new self($this->origin, $names, $this->ifExists, $this->behavior, $this->selection));
    }

    /**
     * Replaces the behavior when a requested table does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->names, $ifExists, $this->behavior, $this->selection));
    }

    /**
     * Replaces the requested handling of dependent schema objects.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->names, $this->ifExists, $behavior, $this->selection));
    }

    /**
     * Replaces the table namespace selection under the statement's dialect invariants.
     */
    public function withSelection(TableDropScope $selection): self
    {
        return $this->changed(new self($this->origin, $this->names, $this->ifExists, $this->behavior, $selection));
    }
}
