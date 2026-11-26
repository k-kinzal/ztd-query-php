<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Separates the owner's field order from the order of the index operands.
 *
 * @visibility SqlSemantics
 */
final class IndexPartsLowering
{
    /**
     * Resolves the written parts and rebinds them into a detached heading in declaration order.
     * @param non-empty-list<KeyPart> $parts The written parts
     * @return array{list<Identifier>, list<ColumnKind>, list<IndexColumn>}
     */
    public function resolve(Table $table, array $parts, bool $legacy): array
    {
        $fields = $directions = [];
        foreach ($parts as $part) {
            Check::input($part instanceof ColumnPart && $part->length === null, 'CREATE INDEX requires whole declared column parts.');
            $matches = $table->matchingIndexColumns($part->column->value);
            Check::input(count($matches) === 1, 'Each index field must resolve to one declared column.');
            $field = $matches[0];
            Check::input(
                $field->type instanceof Integral && $field->type->kind === IntegralKind::Int
                    && !$field->type->unsigned() && !$field->generated && $field->nullability !== Nullability::Dependent,
                'CREATE INDEX requires an ordinary signed INT field with known nullability.'
            );
            $ordinal = array_search($field, $table->columns, true);
            Check::input(is_int($ordinal), 'An index part requires a physically declared field.');
            Check::input(!isset($fields[$ordinal]), 'CREATE INDEX cannot repeat a key column.');
            $fields[$ordinal] = [new Identifier($field->name->value),
                $field->nullability === Nullability::NotNull ? ColumnKind::RequiredSignedInt : ColumnKind::SignedInt];
            $directions[$ordinal] = !$legacy && $part->direction === Direction::Descending;
        }
        ksort($fields, SORT_NUMERIC);
        $slots = array_flip(array_keys($fields));
        $columns = [];
        foreach ($directions as $ordinal => $descending) {
            $columns[] = new IndexColumn($slots[$ordinal], $descending);
        }

        return [array_column($fields, 0), array_column($fields, 1), $columns];
    }
}
