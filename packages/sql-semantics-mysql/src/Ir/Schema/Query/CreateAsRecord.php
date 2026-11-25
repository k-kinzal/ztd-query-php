<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Query;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\Storage\InnoDbRecord;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Derives the record budget from the effective native definition of a query-backed table.
 *
 * @visibility SqlSemantics
 */
final class CreateAsRecord
{
    /**
     * Counts each explicit and projected field in the effective heading once.
     * @param array<array-key, TableColumn> $explicit The checked definition map keyed by decoded names
     */
    public function check(Select $query, array $explicit): void
    {
        $columns = count($explicit);
        $logical = $physical = $nullable = 0;
        $packed = false;

        foreach ($explicit as $field) {
            $bytes = $field->storageBytes();
            $logical += $bytes;
            $physical += $bytes;
            $nullable += (int) $field->nullable;
        }
        foreach ($query->columns as $result) {
            if (isset($explicit[$result->name->key()])) {
                continue;
            }
            ++$columns;
            [$storage, $local, $admitsNull, $variable] = $this->projected($query, $result);
            $logical += $storage;
            $physical += $local;
            $nullable += (int) $admitsNull;
            $packed = $packed || $variable;
        }

        Check::input($columns <= 1017, 'The effective InnoDB table heading can have at most 1017 columns.');
        $bitmap = intdiv($nullable + ($packed ? 0 : 1) + 7, 8);
        Check::input($logical + $bitmap <= 65535, 'The projected logical table record cannot exceed 65535 bytes.');
        Check::input(
            $query->release === GrammarRelease::MySql5651 || InnoDbRecord::fitsPayload($physical, $nullable, false),
            'The projected clustered InnoDB record must fit the native 8126-byte boundary.'
        );
    }

    /**
     * Determines the physical and logical footprint of a checked projected occurrence.
     * @return array{int, int, bool, bool} Logical bytes, local clustered bytes, nullability and row packing
     */
    public function projected(Select $query, ResultColumn $result): array
    {
        $expression = $result->expression;
        if ($expression instanceof ColumnReference || $expression instanceof TextColumnReference) {
            $source = $query->source;
            Check::invariant($source instanceof TableSource, 'An inherited table column has an owning native source.');
            $type = $source->columnTypes[$expression->slot];
            if ($type instanceof Varchar) {
                $bytes = $type->length * 4;

                return [$bytes + ($bytes > 255 ? 2 : 1), InnoDbRecord::varcharBytes($type->length), $type->nullable, true];
            }
            return [4, 4, $type->nullable(), false];
        }

        $release = $query->release;
        if ($expression instanceof NullLiteral) {
            $variable = match ($release) {
                GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044,
                GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830 => false,
                GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => true,
            };

            return [(int) $variable, 1, true, $variable];
        }

        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $wide = $expression instanceof SignedCast ? !$legacy
            : $expression instanceof IntegerLiteral && $expression->decimal[0] !== '-'
                && strlen($expression->decimal) >= ($legacy ? 10 : 9);
        $bytes = $wide ? 8 : 4;

        return [$bytes, $bytes, $expression instanceof SignedCast && $expression->operand instanceof NullLiteral, false];
    }
}
