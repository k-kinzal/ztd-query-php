<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * parse.y:on_using resolves ON in favor of a JOIN until a SELECT clause separates the UPSERT.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/parse.y
 */
final class UpsertSourceRule implements RewriteRule
{
    /**
     * Adds a WHERE expression only when an INSERT source ends with FROM immediately before ON CONFLICT.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('cmd') as $command) {
            $upsert = $sequence->child($command, 'upsert');
            $select = $sequence->child($command, 'select');
            $range = $upsert === null ? null : $sequence->range($upsert->id);
            if ($select === null || $range === null || $sequence->nameAt($range[0]) !== 'ON') {
                continue;
            }
            foreach ($sequence->occurrences('oneselect') as $query) {
                $queryRange = $sequence->range($query);
                if ($queryRange === null || $sequence->terminals[$queryRange[0]]->ancestor('select') !== $select->id) {
                    continue;
                }
                $from = $sequence->child($query, 'from');
                $where = $sequence->child($query, 'where_opt');
                $fromRange = $from === null ? null : $sequence->range($from->id);
                if ($where !== null && $fromRange !== null && $fromRange[1] === $queryRange[1]) {
                    $source = 'src/parse.y:on_using:upsert-ambiguity';
                    $sequence = $sequence->replace($fromRange[1], 0, [
                        $sequence->insertedFor('WHERE', $where->id, $source),
                        $sequence->insertedFor('INTEGER', $where->id, $source, 1),
                    ], $source);
                }
                break;
            }
        }
        return $sequence;
    }
}
