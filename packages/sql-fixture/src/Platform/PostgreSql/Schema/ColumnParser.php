<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition as WrittenColumn;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Reads a column definition into a schema column.
 *
 * The analysis supplies the resolved type, whether the column admits NULL
 * and whether it is generated; the statement supplies its constraints and
 * default.
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
        $name = $declared->name->value;
        $shape = (new TypeDeclaration())->shape($declared->type, $written->type);
        $constraints = (new ColumnConstraints())->read($written->qualifiers);
        $autoIncrement = $shape->autoIncrement || $constraints->identity;

        return new ColumnDefinition(
            name: $name,
            type: $shape->type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $declared->nullability !== Nullability::NotNull && !$autoIncrement && !in_array($name, $primaryKeyColumns, true),
            unsigned: false,
            default: $constraints->default === null ? null : (new DefaultExpression())->evaluate($constraints->default),
            autoIncrement: $autoIncrement,
            generated: $declared->generated,
        );
    }
}
