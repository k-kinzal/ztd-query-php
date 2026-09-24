<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateProcedureField;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE statement that would recreate a procedure.
 * @visibility public
 * @example Inspecting the named procedure
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW CREATE PROCEDURE app.item');
 *     [$statement->procedure->parts, $statement->resultColumns()[0]->name] // => [['app', 'item'], 'Procedure']
 */
final class ShowCreateProcedureStatement extends InspectionStatement
{
    /**
     * @param QualifiedName $procedure Optionally database-qualified procedure name
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $procedure)
    {
        if (count($procedure->parts) > 2) {
            throw new InvalidStructure('A procedure name takes at most a database qualifier.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->procedure);
    }

    /**
     * Names another procedure.
     */
    public function withProcedure(QualifiedName $procedure): self
    {
        return $this->changed(new self($this->origin, $procedure));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateProcedureField::cases());
    }
}
