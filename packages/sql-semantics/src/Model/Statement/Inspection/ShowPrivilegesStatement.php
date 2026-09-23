<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\PrivilegeField;
use SqlSemantics\Model\Query\Inspection\ServerTextColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests privilege definitions and their applicable object contexts.
 * @visibility public
 * @example Inspecting the result roles
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PRIVILEGES');
 *     $statement instanceof \SqlSemantics\Model\Statement\Inspection\ShowPrivilegesStatement // => true
 */
final class ShowPrivilegesStatement extends BoundStatement implements ResultStatement
{
    /**
     * Records the inspection request without reading live server metadata.
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This metadata inspection requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Show;
    }

    /**
     * Retains the inspection operation while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }

    /**
     * @return list<OutputColumn> Ordered server metadata roles, types and NULL facts
     */
    #[Override]
    public function resultColumns(): array
    {
        $columns = [];
        foreach (PrivilegeField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new ServerTextColumn($this->source, $this->scopeId, $field));
        }
        return $columns;
    }
}
