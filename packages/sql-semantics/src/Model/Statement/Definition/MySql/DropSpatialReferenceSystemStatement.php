<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Spatial\SpatialOperands;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * Removes one spatial reference system identified by its nonzero unsigned 32-bit SRID.
 * @visibility public
 * @example Inspecting the requested SRID
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4120');
 *     $statement->srid // => 4120
 */
final class DropSpatialReferenceSystemStatement extends BoundStatement
{
    /**
     * Retains the identifier and missing-definition policy without checking live dependencies.
     */
    public function __construct(Origin $origin, public readonly int $srid, public readonly bool $ifExists = false)
    {
        SpatialOperands::statement($origin, $srid);
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
        return new self($origin, $this->srid, $this->ifExists);
    }

    /**
     * Replaces the identifier under the same domain invariant.
     */
    public function withSrid(int $srid): self
    {
        return $this->changed(new self($this->origin, $srid, $this->ifExists));
    }

    /**
     * Replaces the behavior when the target does not exist.
     */
    public function withIfExists(bool $ifExists): self
    {
        return $this->changed(new self($this->origin, $this->srid, $ifExists));
    }
}
