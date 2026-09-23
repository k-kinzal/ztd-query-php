<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\View;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests recomputation of a materialized view's stored rows from its query.
 *
 * @visibility public
 * @example Inspecting a concurrent refresh
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('REFRESH MATERIALIZED VIEW CONCURRENTLY app.mv');
 *     [$statement->concurrently, $statement->name->parts] // => [true, ['app', 'mv']]
 */
final class RefreshMaterializedViewStatement extends BoundStatement
{
    /**
     * A concurrent refresh keeps the stored rows readable and therefore cannot empty them.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly bool $concurrently = false, public readonly bool $withData = true)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A materialized view requires PostgreSQL.');
        }
        if ($concurrently && !$withData) {
            throw new InvalidStructure('A concurrent refresh cannot leave the view without data.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Refresh;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->concurrently, $this->withData);
    }

    /**
     * Targets another materialized view with the same refresh policy.
     * @throws InvalidStructure
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->concurrently, $this->withData));
    }
}
