<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition as WrittenColumn;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Reads a column definition into a schema column.
 *
 * The analysis supplies the declared type, whether the column admits NULL
 * and whether it is generated; the statement supplies the written type
 * arguments, the primary key and the default.
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
        $domain = $declared->type;
        $shape = $domain instanceof ColumnDomain ? (new TypeDeclaration())->shape($domain, $written->type) : new TypeShape('BLOB');
        $constraints = (new ColumnConstraints())->read($written->constraints);

        return new ColumnDefinition(
            name: $name,
            type: $shape->type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $declared->nullability !== Nullability::NotNull && !in_array($name, $primaryKeyColumns, true),
            unsigned: false,
            default: $constraints->default === null ? null : (new DefaultExpression())->evaluate($constraints->default),
            autoIncrement: $constraints->autoIncrement,
            generated: $declared->generated,
        );
    }
}
