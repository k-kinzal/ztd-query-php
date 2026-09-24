<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateViewField;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE VIEW statement that would recreate a view; the view is resolved like a table.
 * @visibility public
 * @example Inspecting the described view
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE users(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SHOW CREATE VIEW users');
 *     [$statement->view->declaration->name, $statement->resultColumns()[1]->name] // => ['users', 'Create View']
 */
final class ShowCreateViewStatement extends InspectionStatement
{
    /**
     * @param TableReference $view Described view, resolved or diagnosed against the schema
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TableReference $view)
    {
        InspectedTable::validate($origin, $view);
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->view);
    }

    /**
     * Describes another view and resolves it against this request's schema.
     */
    public function withView(TableReference $view): self
    {
        return $this->changed(new self($this->origin, $view));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateViewField::cases());
    }
}
