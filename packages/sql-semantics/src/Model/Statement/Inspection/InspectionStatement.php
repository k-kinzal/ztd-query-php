<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\MetadataColumn;
use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A MySQL SHOW request whose result fields come from a closed metadata domain.
 * @visibility public
 * @example Inspecting the shared operation of a metadata request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW COLLATION');
 *     [$statement instanceof \SqlSemantics\Model\Statement\Inspection\InspectionStatement, $statement->kind->value] // => [true, 'SHOW']
 */
abstract class InspectionStatement extends BoundStatement implements ResultStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('SHOW requests require MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Show;
    }

    /**
     * Whether the request binds against a 5.6 or 5.7 grammar, whose result layouts predate later fields.
     */
    public function legacyRelease(): bool
    {
        return in_array($this->origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true);
    }

    /**
     * @param list<MetadataField> $fields Result fields in server order
     * @param array<string, string> $labels Result labels replacing a field's default label
     * @return list<OutputColumn>
     */
    protected function columns(array $fields, array $labels = []): array
    {
        $columns = [];
        foreach ($fields as $ordinal => $field) {
            $label = $labels[$field->label()] ?? null;
            $columns[] = new OutputColumn($ordinal, $label ?? $field->label(), new MetadataColumn($this->source, $this->scopeId, $field, $label));
        }
        return $columns;
    }
}
