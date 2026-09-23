<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\Routine\OrderedSetAggregate;
use SqlSemantics\Model\Definition\Routine\OrdinaryAggregate;
use SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Deletes aggregate definitions identified by their typed names and argument requests.
 * @visibility public
 * @example Inspecting a deletion request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DROP AGGREGATE f(*)');
 *     $statement instanceof \SqlSemantics\Model\Statement\Definition\PostgreSql\DropAggregatesStatement // => true
 * @example Rejecting a missing target list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\PostgreSql\DropAggregatesStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DropAggregatesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<ZeroArgumentAggregate|OrdinaryAggregate|OrderedSetAggregate> $targets Ordered deletion requests
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $targets,
        public readonly bool $ifExists = false,
        public readonly DropBehavior $behavior = DropBehavior::Default,
    ) {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Overloaded routine deletion requires PostgreSQL.');
        }
        Collections::alternatives(Collections::nonEmpty($targets), [ZeroArgumentAggregate::class, OrdinaryAggregate::class, OrderedSetAggregate::class]);
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
        return new self($origin, $this->targets, $this->ifExists, $this->behavior);
    }

    /**
     * Replaces the targets with a separately validated request.
     * @param non-empty-list<ZeroArgumentAggregate|OrdinaryAggregate|OrderedSetAggregate> $targets Ordered replacements
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $targets, $this->ifExists, $this->behavior));
    }

    /**
     * Replaces the behavior when a target does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->targets, $ifExists, $this->behavior));
    }

    /**
     * Replaces the handling of dependent objects.
     */
    public function withBehavior(DropBehavior $behavior): self
    {
        return $this->changed(new self($this->origin, $this->targets, $this->ifExists, $behavior));
    }
}
