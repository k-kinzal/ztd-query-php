<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Alter;

use PhpMyAdmin\SqlParser\Components\AlterOperation;

/**
 * Distinguishes column changes from keys, table metadata and unsupported ALTER operations.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ColumnAction
{
    /**
     * @return 'add'|'drop'|'modify'|'change'|null
     */
    public function detect(AlterOperation $op): ?string
    {
        $options = $op->options;
        if (($options->has('ADD') !== false) && ($options->has('COLUMN') !== false)) {
            return 'add';
        }
        if (($options->has('ADD') !== false) && !\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['COLUMN', 'PRIMARY KEY', 'FOREIGN', 'INDEX', 'UNIQUE', 'KEY', 'FULLTEXT', 'SPATIAL', 'CONSTRAINT', 'PARTITION']) && !(new UnsupportedKeyword())->hasUnsupportedKeywordInUnknown($op)) {
            return 'add';
        }
        if (($options->has('DROP') !== false) && ($options->has('COLUMN') !== false)) {
            return 'drop';
        }
        if (($options->has('DROP') !== false) && !\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['COLUMN', 'PRIMARY KEY', 'FOREIGN', 'INDEX', 'KEY', 'CONSTRAINT'])) {
            return 'drop';
        }
        if (\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['MODIFY', 'MODIFY COLUMN'])) {
            return 'modify';
        }
        if (\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, ['CHANGE', 'CHANGE COLUMN'])) {
            return 'change';
        }
        return null;
    }
}
