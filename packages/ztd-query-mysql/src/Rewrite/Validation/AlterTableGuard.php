<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite\Validation;

use PhpMyAdmin\SqlParser\Statements\AlterStatement;

/**
 * Alter Table Guard.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class AlterTableGuard
{
    /**
     * Check for unsupported ALTER TABLE operations.
     */

    public function hasUnsupportedAlterOperation(AlterStatement $statement, string $sql): bool
    {
        $upperSql = strtoupper($sql);
        if (str_contains($upperSql, 'SET DEFAULT') || str_contains($upperSql, 'DROP DEFAULT')) {
            return true;
        }
        if (str_contains($upperSql, 'ORDER BY')) {
            return true;
        }
        foreach ($statement->altered ?? [] as $operation) {
            if (!$operation->options->isEmpty() && $this->rejectsOperation($operation)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recognize ALTER operations whose effects cannot be simulated safely.
     */
    public function rejectsOperation(\PhpMyAdmin\SqlParser\Components\AlterOperation $operation): bool
    {
        $options = $operation->options;
        $forbidden = [
            'ADD' => ['INDEX', 'KEY', 'FULLTEXT', 'SPATIAL', 'UNIQUE', 'CONSTRAINT'],
            'DROP' => ['INDEX', 'KEY', 'CONSTRAINT'],
            'RENAME' => ['INDEX', 'KEY'],
            'ALTER' => ['SET DEFAULT', 'DROP DEFAULT'],
        ];
        foreach ($forbidden as $verb => $keywords) {
            if ($options->has($verb) !== false && \ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, $keywords)) {
                return true;
            }
        }
        if (\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::hasAny($options, [
            'ORDER', 'ORDER BY', 'CONVERT', 'ENGINE', 'PARTITION', 'ADD PARTITION',
            'DROP PARTITION', 'TRUNCATE PARTITION', 'COALESCE PARTITION',
            'REORGANIZE PARTITION', 'EXCHANGE PARTITION', 'ANALYZE PARTITION',
            'CHECK PARTITION', 'OPTIMIZE PARTITION', 'REBUILD PARTITION',
            'REPAIR PARTITION', 'REMOVE PARTITIONING',
        ])) {
            return true;
        }
        foreach (\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::unknownKeywords($operation) as $keyword) {
            if (in_array($keyword, ['ORDER', 'ORDER BY'], true)) {
                return true;
            }
            if ($options->has('ALTER') !== false && in_array($keyword, ['SET', 'DROP'], true)) {
                return true;
            }
            foreach (['PARTITION', 'ENGINE', 'SPATIAL', 'FULLTEXT'] as $unsupported) {
                if (str_contains($keyword, $unsupported)) {
                    return true;
                }
            }
        }
        return false;
    }
}
