<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Implements the contextual identifier checks in sql_yacc.yy/alter_instance_action and opt_source_count.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class InstanceActionRule implements RewriteRule
{
    /**
     * Replaces only the checked identifier productions with their explicitly declared lexical domains.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('alter_instance_action') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $first = $sequence->nameAt($range[0]);
            $domains = match ($first) {
                'ROTATE_SYM' => ['ROTATE_KEY_ENGINE'],
                'ENABLE_SYM', 'DISABLE_SYM' => ['REDO_ENGINE', 'REDO_LOG_NAME'],
                default => [],
            };
            foreach ($sequence->productions as $child) {
                if ($child->parent !== $id || !in_array($child->rule, ['ident', 'ident_or_text'], true) || $domains === []) {
                    continue;
                }
                $childRange = $sequence->range($child->id);
                $domain = array_shift($domains);
                if ($childRange !== null) {
                    $sequence = $sequence->replace($childRange[0], $childRange[1] - $childRange[0], [
                        $sequence->insertedFor($domain, $child->id, 'mysql.instance-action-name'),
                    ], 'mysql.instance-action-name');
                }
            }
        }
        return $sequence;
    }
}
