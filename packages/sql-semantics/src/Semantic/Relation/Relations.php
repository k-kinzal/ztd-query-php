<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Relation;

use SqlSemantics\Core\Model\JoinKind;

/**
 * Computes occurrence sets from semantic relation operations.
 * @visibility SqlSemantics
 */
final class Relations
{
    /**
     * @return non-empty-list<TableReference>
     */
    public static function tables(TableReference|Join $source): array
    {
        return $source instanceof TableReference ? [$source] : [...self::tables($source->left), ...self::tables($source->right)];
    }

    /**
     * @return list<TableReference>
     */
    public static function nullable(TableReference|Join $source): array
    {
        if ($source instanceof TableReference) {
            return [];
        }
        return [
            ...in_array($source->kind, [JoinKind::Right, JoinKind::Full], true) ? self::tables($source->left) : self::nullable($source->left),
            ...in_array($source->kind, [JoinKind::Left, JoinKind::Full], true) ? self::tables($source->right) : self::nullable($source->right),
        ];
    }
}
