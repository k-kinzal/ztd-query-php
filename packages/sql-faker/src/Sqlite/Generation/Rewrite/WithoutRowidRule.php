<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Implements the required PRIMARY KEY and forbidden AUTOINCREMENT conditions in build.c/sqlite3EndTable.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/build.c
 */
final class WithoutRowidRule implements RewriteRule
{
    /**
     * Completes the table's first column only when WITHOUT ROWID has no primary key.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('create_table_args') as $table) {
            $options = array_filter($sequence->terminals, static fn ($terminal): bool =>
                $terminal->name === 'ROWID_TABLE_OPTION' && $terminal->ancestor('create_table_args') === $table);
            if ($options === []) {
                continue;
            }
            foreach ($sequence->occurrences('autoinc') as $id) {
                $range = $sequence->range($id);
                if ($range !== null && $sequence->terminals[$range[0]]->ancestor('create_table_args') === $table) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'sqlite.without-rowid-autoincrement');
                }
            }
            $keys = array_filter($sequence->terminals, static fn ($terminal): bool =>
                $terminal->name === 'PRIMARY' && $terminal->ancestor('create_table_args') === $table);
            if ($keys === []) {
                $sequence = $this->primaryKey($sequence, $table);
            }
        }
        return $sequence;
    }

    /**
     * Attaches a column constraint to its original carglist occurrence.
     */
    public function primaryKey(TerminalSequence $sequence, int $table): TerminalSequence
    {
        foreach ($sequence->productions as $production) {
            if ($production->rule !== 'columnname' || $production->parent === null) {
                continue;
            }
            $range = $sequence->range($production->id);
            $constraints = $sequence->child($production->parent, 'carglist');
            if ($range === null || $constraints === null || $sequence->terminals[$range[0]]->ancestor('create_table_args') !== $table) {
                continue;
            }
            return $sequence->replace($range[1], 0, [
                $sequence->insertedFor('PRIMARY', $constraints->id, 'sqlite.without-rowid-primary-key'),
                $sequence->insertedFor('KEY', $constraints->id, 'sqlite.without-rowid-primary-key', 1),
            ], 'sqlite.without-rowid-primary-key');
        }
        return $sequence;
    }
}
