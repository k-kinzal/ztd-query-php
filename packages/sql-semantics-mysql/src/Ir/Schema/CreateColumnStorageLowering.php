<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\From\VarcharLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Binds the native storage type of one ordinary column declaration.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreateColumnStorageLowering
{
    /**
     * Resolves signed or character storage with its exact native type parameters.
     *
     * @throws InvalidConstruction When the native storage has no checked declaration role
     */
    public function lower(ColumnDefinition $column, ?Name $tableCollation): IntegerStorage|CharacterStorage
    {
        $type = $column->specification->dataType();
        if ($type instanceof Character) {
            $declared = (new TableDeclaration())->column($column, tableCollation: $tableCollation);
            $varchar = (new VarcharLowering())->lower($declared);

            return new CharacterStorage($varchar->length, $varchar->collation);
        }
        Check::input($type instanceof Integral && $type->width === null && $type->modifiers === [], 'The column requires a signed integer storage role.');

        return match ($type->kind) {
            IntegralKind::TinyInt => IntegerStorage::Tiny,
            IntegralKind::SmallInt => IntegerStorage::Small,
            IntegralKind::MediumInt => IntegerStorage::Medium,
            IntegralKind::Int => IntegerStorage::Standard,
            IntegralKind::BigInt => IntegerStorage::Wide,
        };
    }
}
