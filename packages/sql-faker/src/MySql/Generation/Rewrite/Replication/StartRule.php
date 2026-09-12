<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Replication;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy requires the IO thread when START REPLICA/SLAVE supplies authentication options.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class StartRule implements RewriteRule
{
    /**
     * Adds the IO thread to an explicitly SQL-only start, keeping the authentication values intact.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (['start_replica_stmt' => 'opt_replica_thread_option_list', 'slave' => 'opt_slave_thread_option_list'] as $rule => $options) {
            foreach ($sequence->occurrences($rule) as $id) {
                $range = $sequence->range($id);
                $threads = $sequence->child($id, $options);
                $threadRange = $threads === null ? null : $sequence->range($threads->id);
                if ($range === null || $threads === null || $threadRange === null || $sequence->nameAt($range[0]) !== 'START_SYM') {
                    continue;
                }
                $names = array_slice($sequence->names(), $threadRange[0], $threadRange[1] - $threadRange[0]);
                $statement = array_slice($sequence->names(), $range[0], $range[1] - $range[0]);
                if (!in_array('SQL_THREAD', $names, true) || in_array('RELAY_THREAD', $names, true)
                    || array_intersect($statement, ['USER', 'PASSWORD', 'DEFAULT_AUTH_SYM', 'PLUGIN_DIR_SYM']) === []) {
                    continue;
                }
                $source = 'sql/sql_yacc.yy:START_REPLICA:authentication';
                $sequence = $sequence->replace($threadRange[1], 0, [
                    $sequence->insertedFor(',', $threads->id, $source),
                    $sequence->insertedFor('RELAY_THREAD', $threads->id, $source, 1),
                ], $source);
            }
        }
        return $sequence;
    }
}
