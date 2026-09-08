<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Implements sqlite3EndTable's STRICT column-type restriction using global.c/sqlite3StdType.
 */
final class StrictTableRule implements RewriteRule
{
    /**
     * Constrains only columns of the same STRICT table, including originally empty type productions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('create_table_args') as $table) {
            $strict = array_filter($sequence->terminals, static fn ($terminal): bool =>
                $terminal->name === 'STRICT_TABLE_OPTION' && $terminal->ancestor('create_table_args') === $table);
            if ($strict === []) {
                continue;
            }
            foreach ($sequence->occurrences('columnname') as $column) {
                $range = $sequence->range($column);
                if ($range === null || $sequence->terminals[$range[0]]->ancestor('create_table_args') !== $table) {
                    continue;
                }
                $type = $sequence->child($column, 'typetoken');
                if ($type === null) {
                    continue;
                }
                $typeRange = $sequence->range($type->id) ?? [$range[1], $range[1]];
                $sequence = $sequence->replace($typeRange[0], $typeRange[1] - $typeRange[0], [
                    $sequence->insertedFor('STRICT_COLUMN_TYPE', $type->id, 'sqlite.strict-column-type'),
                ], 'sqlite.strict-column-type');
            }
        }
        return $sequence;
    }
}
