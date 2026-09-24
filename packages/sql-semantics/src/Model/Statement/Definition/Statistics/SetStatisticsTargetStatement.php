<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Statistics;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Sets the sampling target of an extended statistics object; -1, also spelled DEFAULT, restores the system default.
 * @visibility public
 * @example Reading the new target
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS IF EXISTS app.s SET STATISTICS 500');
 *     $statement->name->parts // => ['app', 's']
 *     $statement->target // => 500
 * @example Rejecting a target below the default marker
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER STATISTICS s SET STATISTICS DEFAULT');
 *     $statement->withTarget(-2); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class SetStatisticsTargetStatement extends BoundStatement
{
    /**
     * @param int $target Sampling target between -1 and 10000
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly bool $ifExists, public readonly int $target)
    {
        StatisticsInvariant::dialect($origin);
        StatisticsInvariant::name($name, false);
        if ($target < -1 || $target > 10000) {
            throw new InvalidStructure('A statistics target is between -1 and 10000.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the target change while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifExists, $this->target);
    }

    /**
     * Replaces the altered statistics object.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifExists, $this->target));
    }

    /**
     * Replaces whether a missing statistics object is accepted.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifExists, $this->target));
    }

    /**
     * Replaces the sampling target.
     */
    public function withTarget(int $target): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifExists, $target));
    }
}
