<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A persistent truncation target with an explicit descendant policy.
 *
 * @visibility public
 * @example Keeping descendants unchanged
 *     $schema = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app');
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('rows');
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName($schema, $name);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTarget($target, true))->toString() // => 'ONLY "app"."rows"'
 */
final class TruncateTarget
{
    use Snapshot;

    /**
     * Fixes the absolute table identity and the scope of this occurrence.
     */
    public function __construct(public readonly TableName $table, public readonly bool $only = false)
    {
    }

    /**
     * Emits this target in its original semantic position.
     */
    public function toString(): string
    {
        return ($this->only ? 'ONLY ' : '') . $this->table->quoted();
    }
}
