<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y restricts partition strategies, JSON encodings, and literal role names beyond their grammar productions.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L4497-L4506
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L16905-L16927
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L17298-L17341
 */
final class ParserNameRule implements RewriteRule
{
    /**
     * Replaces only the direct name child; descendant column names and general RoleSpec references remain intact.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (['PartitionSpec' => ['ColId', 'PARTITION_STRATEGY'], 'json_format_clause' => ['name', 'JSON_ENCODING']] as $owner => [$childName, $name]) {
            foreach ($sequence->occurrences($owner) as $id) {
                $child = $sequence->child($id, $childName);
                $range = $child === null ? null : $sequence->range($child->id);
                if ($range !== null && $sequence->terminals[$range[0]]->name !== $name) {
                    $source = 'gram.y:' . $owner . ':name-domain';
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [$sequence->terminals[$range[0]]->replaced($name, $source)], $source);
                }
            }
        }
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->within('RoleId') && in_array($terminal->name, ['CURRENT_ROLE', 'CURRENT_USER', 'SESSION_USER'], true)) {
                $source = 'gram.y:RoleId:literal-name';
                $sequence = $sequence->replace($index, 1, [$terminal->replaced('IDENT', $source)], $source);
            }
        }
        return $sequence;
    }
}
