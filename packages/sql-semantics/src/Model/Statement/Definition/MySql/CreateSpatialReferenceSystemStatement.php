<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Spatial\CreationPolicy;
use SqlSemantics\Model\Definition\Spatial\SpatialDefinition;
use SqlSemantics\Model\Definition\Spatial\SpatialOperands;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * Creates a spatial reference system with required metadata and one existence policy.
 * @visibility public
 * @example Inspecting a replacement request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
 *     $statement->policy === \SqlSemantics\Model\Definition\Spatial\CreationPolicy::Replace // => true
 */
final class CreateSpatialReferenceSystemStatement extends BoundStatement
{
    /**
     * Requires name and definition together through the SpatialDefinition value.
     */
    public function __construct(
        Origin $origin,
        public readonly int $srid,
        public readonly SpatialDefinition $definition,
        public readonly CreationPolicy $policy = CreationPolicy::RequireNew,
    ) {
        SpatialOperands::statement($origin, $srid);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains all declaration operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->srid, $this->definition, $this->policy);
    }

    /**
     * Replaces the nonzero unsigned 32-bit identifier.
     */
    public function withSrid(int $srid): self
    {
        return $this->changed(new self($this->origin, $srid, $this->definition, $this->policy));
    }

    /**
     * Replaces the complete metadata value, preserving its mandatory operands.
     */
    public function withDefinition(SpatialDefinition $definition): self
    {
        return $this->changed(new self($this->origin, $this->srid, $definition, $this->policy));
    }

    /**
     * Replaces one mutually exclusive existence policy with another.
     */
    public function withPolicy(CreationPolicy $policy): self
    {
        return $this->changed(new self($this->origin, $this->srid, $this->definition, $policy));
    }
}
