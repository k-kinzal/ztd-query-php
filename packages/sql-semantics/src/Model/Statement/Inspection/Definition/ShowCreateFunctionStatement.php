<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateFunctionField;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE statement that would recreate a function.
 * @visibility public
 * @example Inspecting the named function
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW CREATE FUNCTION app.item');
 *     [$statement->function->parts, $statement->resultColumns()[0]->name] // => [['app', 'item'], 'Function']
 */
final class ShowCreateFunctionStatement extends InspectionStatement
{
    /**
     * @param QualifiedName $function Optionally database-qualified function name
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $function)
    {
        if (count($function->parts) > 2) {
            throw new InvalidStructure('A function name takes at most a database qualifier.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->function);
    }

    /**
     * Names another function.
     */
    public function withFunction(QualifiedName $function): self
    {
        return $this->changed(new self($this->origin, $function));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateFunctionField::cases());
    }
}
