<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Routine\Characteristics\AlterationInvariant;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineAlteration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Changes stored FUNCTION characteristics without changing its body or executing it.
 * @visibility public
 * @example Inspecting the routine target
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER FUNCTION app.r NO SQL');
 *     $statement->name->parts // => ['app', 'r']
 */
final class AlterFunctionStatement extends BoundStatement
{
    /**
     * Requires a typed metadata-change request, which may leave every characteristic unchanged.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly RoutineAlteration $changes)
    {
        AlterationInvariant::target($origin, $name);
        AlterationInvariant::changes($origin, $changes);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the routine request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->changes);
    }

    /**
     * Replaces the routine target without changing its requested characteristics.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->changes));
    }

    /**
     * Replaces all requested characteristic changes together.
     */
    public function withChanges(RoutineAlteration $changes): self
    {
        return $this->changed(new self($this->origin, $this->name, $changes));
    }
}
