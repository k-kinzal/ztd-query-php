<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * parse_tree_column_attrs.h rejects SERIAL and storage/default attributes on generated fields.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/parse_tree_column_attrs.h
 */
final class GeneratedColumnRule implements RewriteRule
{
    /**
     * Retains the generation expression, replacing SERIAL's implicit auto-increment type and forbidden attributes.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('field_def') as $id) {
            if ($sequence->child($id, 'expr') === null) {
                continue;
            }
            $type = $sequence->child($id, 'type');
            $range = $type === null ? null : $sequence->range($type->id);
            if ($range !== null && $sequence->nameAt($range[0]) === 'SERIAL_SYM') {
                $source = 'sql/parse_tree_column_attrs.h:PT_generated_field_def:serial';
                $anchor = $sequence->terminals[$range[0]];
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                    $anchor->replaced('BIGINT_SYM', $source), $sequence->inserted('UNSIGNED_SYM', $anchor, $source),
                ], $source);
            }
            foreach ($sequence->occurrences('column_attribute') as $attribute) {
                $range = $sequence->range($attribute);
                if ($range !== null && $sequence->terminals[$range[0]]->ancestor('field_def') === $id
                    && in_array($sequence->nameAt($range[0]), ['DEFAULT_SYM', 'ON_SYM', 'AUTO_INC', 'SERIAL_SYM', 'COLUMN_FORMAT_SYM', 'STORAGE_SYM'], true)) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'sql/parse_tree_column_attrs.h:generated-attributes');
                }
            }
        }
        return $sequence;
    }
}
