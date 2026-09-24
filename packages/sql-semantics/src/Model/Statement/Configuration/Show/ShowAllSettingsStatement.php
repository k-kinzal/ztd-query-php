<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration\Show;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Setting\SettingColumn;
use SqlSemantics\Model\Query\Inspection\Setting\SettingField;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Displays every PostgreSQL run-time parameter with its value and description.
 * @visibility public
 * @example Reading the result columns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SHOW ALL');
 *     array_map(static fn ($column) => $column->name, $statement->resultColumns()) // => ['name', 'setting', 'description']
 */
final class ShowAllSettingsStatement extends BoundStatement implements ResultStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('SHOW ALL requires PostgreSQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Show;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin);
    }

    /**
     * @return list<OutputColumn> The name, displayed value and description of each parameter
     */
    #[Override]
    public function resultColumns(): array
    {
        $columns = [];
        foreach (SettingField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new SettingColumn($this->source, $this->scopeId, $field, $field->value));
        }
        return $columns;
    }
}
