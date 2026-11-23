<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Values;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Resolves one complete vertical column in a checked VALUES matrix.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ColumnUnification
{
    /**
     * Derives the native common kind at a position present in every row.
     * @param non-empty-list<ValuesRow> $rows The validated equal-width rows
     */
    public function of(array $rows, int $column): ColumnKind
    {
        $candidate = null;
        foreach ($rows as $row) {
            $kind = $row->cells[$column]->kind();
            if ($candidate === null) {
                $candidate = $kind;
            } elseif ($kind === ColumnKind::Int8 && $candidate === ColumnKind::Int4) {
                $candidate = ColumnKind::Int8;
            } else {
                Check::input(
                    $kind === null || $kind === $candidate || ($kind === ColumnKind::Int4 && $candidate === ColumnKind::Int8),
                    'VALUES column types are incompatible.'
                );
            }
        }

        return $candidate ?? ColumnKind::Text;
    }
}
