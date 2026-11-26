<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryColumnPolicy;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;

/**
 * Resolves the supported native column definition.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateColumnLowering
{
    /**
     * Reads the native storage, nullability and literal default.
     *
     * @throws InvalidConstruction When the definition has unsupported attributes or values
     */
    public function lower(ColumnDefinition $column, ?Name $tableCollation = null, ?PrimaryColumnPolicy $primary = null): TableColumn
    {
        $specification = $column->specification;
        Check::input(
            $column->name->table === null && $specification instanceof OrdinaryColumn && $specification->references === null,
            'CREATE TABLE requires ordinary unqualified column definitions.'
        );
        $kind = (new CreateColumnStorageLowering())->lower($column, $tableCollation);
        $nullable = null;
        $default = null;
        foreach ($specification->attributes as $attribute) {
            if ($attribute instanceof CollateAttribute && $kind instanceof CharacterStorage) {
                continue;
            }
            if ($primary?->inline === true && $attribute instanceof KeywordAttribute && $attribute->keyword === ColumnKeyword::PrimaryKey) {
                continue;
            }
            if ($attribute instanceof DefaultLiteral && $default === null) {
                $default = (new LiteralDefaultLowering())->lower($attribute->value);
            } elseif ($attribute instanceof KeywordAttribute && $nullable === null) {
                $nullable = match ($attribute->keyword) {
                    ColumnKeyword::Null => true,
                    ColumnKeyword::NotNull => false,
                    ColumnKeyword::NotSecondary, ColumnKeyword::AutoIncrement, ColumnKeyword::SerialDefaultValue,
                    ColumnKeyword::PrimaryKey, ColumnKeyword::Unique, ColumnKeyword::Visible,
                    ColumnKeyword::Invisible => throw new InvalidConstruction('The column attribute is outside the native declaration domain.'),
                };
            } else {
                throw new InvalidConstruction('The column attribute is outside the native declaration domain.');
            }
        }
        if ($primary !== null) {
            $default = $primary->effectiveDefault($nullable, $default);
            $nullable = false;
        }

        return new TableColumn(new Identifier($column->name->column->value), $kind, $nullable ?? true, $default);
    }
}
