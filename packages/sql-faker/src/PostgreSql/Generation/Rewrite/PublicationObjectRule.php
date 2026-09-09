<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Resolves the table/schema continuation state checked by gram.y/preprocess_pubobj_list.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class PublicationObjectRule implements RewriteRule
{
    /**
     * Supplies a missing or incompatible object-kind prefix, retaining valid continuation syntax.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $previous = [];
        foreach (array_reverse($sequence->occurrences('PublicationObjSpec')) as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $first = $sequence->terminals[$range[0]];
            $root = array_search('pub_obj_list', $first->rules, true);
            $list = $root === false ? $id : $first->ancestors[$root];
            if (in_array($first->name, ['TABLE', 'TABLES'], true)) {
                $previous[$list] = $first->name;
                continue;
            }
            $kind = $this->kind($sequence, $id, $previous[$list] ?? 'TABLE');
            if (!isset($previous[$list]) || $previous[$list] !== $kind) {
                $prefix = $kind === 'TABLE' ? ['TABLE'] : ['TABLES', 'IN_P', 'SCHEMA'];
                $inserted = [];
                foreach ($prefix as $offset => $name) {
                    $inserted[] = $sequence->insertedFor($name, $id, 'postgresql.publication-object', $offset);
                }
                $sequence = $sequence->replace($range[0], 0, $inserted, 'postgresql.publication-object');
            }
            $previous[$list] = $kind;
        }
        return $sequence;
    }

    /**
     * Distinguishes plain inherited names, table-only specifications and CURRENT_SCHEMA.
     */
    public function kind(TerminalSequence $sequence, int $object, string $inherited): string
    {
        $range = $sequence->range($object);
        if ($range !== null && $sequence->nameAt($range[0]) === 'CURRENT_SCHEMA') {
            return 'TABLES';
        }
        foreach (['indirection', 'extended_relation_expr', 'opt_column_list', 'OptWhereClause'] as $name) {
            $child = $sequence->child($object, $name);
            if ($child !== null && $sequence->range($child->id) !== null) {
                return 'TABLE';
            }
        }
        return $inherited;
    }
}
