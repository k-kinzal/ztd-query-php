<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\ExpressionLowering;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Resolves the supported native signed column definition.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateColumnLowering
{
    /**
     * Reads only the integer storage, nullability and literal default.
     *
     * @throws InvalidConstruction When the definition has unsupported attributes or values
     */
    public function lower(BoundStatement $operation, ColumnDefinition $column): TableColumn
    {
        $specification = $column->specification;
        Check::input(
            $column->name->table === null && $specification instanceof OrdinaryColumn && $specification->references === null,
            'CREATE TABLE requires ordinary unqualified column definitions.'
        );
        $type = $specification->type;
        Check::input($type instanceof Integral && $type->width === null && $type->modifiers === [], 'The column requires a signed integer storage role.');
        $kind = match ($type->kind) {
            IntegralKind::SmallInt => IntegerStorage::Small,
            IntegralKind::Int => IntegerStorage::Standard,
            IntegralKind::BigInt => IntegerStorage::Wide,
            IntegralKind::TinyInt, IntegralKind::MediumInt => throw new InvalidConstruction('The column requires a supported signed integer storage role.'),
        };
        $nullable = null;
        $default = null;
        foreach ($specification->attributes as $attribute) {
            if ($attribute instanceof DefaultLiteral && $default === null) {
                $default = (new ExpressionLowering())->lower($attribute->value, new SourceBinding($operation, null));
                Check::input($default instanceof IntegerLiteral || $default instanceof NullLiteral, 'The column requires an integer or NULL default.');
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

        return new TableColumn(new Identifier($column->name->column->value), $kind, $nullable ?? true, $default);
    }
}
