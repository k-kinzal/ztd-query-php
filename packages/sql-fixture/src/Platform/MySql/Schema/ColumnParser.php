<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as WrittenColumn;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Reads a column definition into a schema column.
 *
 * The statement supplies the written type, attributes and default; the
 * analysis supplies whether the column admits NULL and whether it is generated.
 *
 * @visibility root
 */
final class ColumnParser
{
    /**
     * Returns the schema column for a written column and its declaration.
     *
     * @param list<string> $primaryKeyColumns
     */
    public function parse(WrittenColumn $written, Column $declared, array $primaryKeyColumns): ColumnDefinition
    {
        $name = $written->name->column->value;
        $type = $written->specification->dataType();
        $shape = (new TypeParameters())->shape($type);
        $attributes = (new ColumnAttributes())->read($written->specification->columnAttributes());
        $autoIncrement = $attributes->autoIncrement || $shape->autoIncrement;

        return new ColumnDefinition(
            name: $name,
            type: $shape->type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $declared->nullability !== Nullability::NotNull && !$autoIncrement && !in_array($name, $primaryKeyColumns, true),
            unsigned: (new TypeParameters())->unsigned($type),
            default: $attributes->default === null ? null : (new DefaultExpression())->evaluate($attributes->default, (new TypeParameters())->numeric($type)),
            autoIncrement: $autoIncrement,
            generated: $declared->generated,
            enumValues: (new TypeParameters())->members($type),
        );
    }
}
