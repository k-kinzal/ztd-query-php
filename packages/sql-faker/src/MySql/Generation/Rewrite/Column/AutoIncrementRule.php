<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Column;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * create_field.cc/Create_field::init permits AUTO_INCREMENT only for integer storage types.
 */
final class AutoIncrementRule implements RewriteRule
{
    /**
     * Removes a column attribute when its owning field has an incompatible type.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('column_attribute') as $id) {
            $range = $sequence->range($id);
            $field = $range === null ? null : $sequence->terminals[$range[0]]->ancestor('field_def');
            $type = $field === null ? null : $sequence->child($field, 'type');
            $owner = $type === null ? null : $sequence->range($type->id);
            if ($range === null || $owner === null || !in_array($sequence->nameAt($range[0]), ['AUTO_INC', 'SERIAL_SYM'], true)) {
                continue;
            }
            if (!in_array($sequence->nameAt($owner[0]), ['INT_SYM', 'TINYINT_SYM', 'SMALLINT_SYM', 'MEDIUMINT_SYM', 'BIGINT_SYM', 'BOOL_SYM', 'BOOLEAN_SYM'], true)) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'sql/create_field.cc:allowed_type_modifier');
            }
        }
        return $sequence;
    }
}
