<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Conflict;

use SqlSemantics\Model\Expression;

/**
 * A conflict selected by ordered index expressions and an optional index predicate.
 * @visibility public
  * @example Inspecting IndexConflict
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
 *     $nothing = $binder->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING')->conflicts[0];
 *     $update = $binder->bind('INSERT INTO t VALUES(1) ON CONFLICT(id) DO UPDATE SET id=2 WHERE t.id=1')->conflicts[0];
 *     $update->target instanceof \SqlSemantics\Model\Write\Conflict\IndexConflict // => true
 */
final class IndexConflict implements Target
{
    /**
     * @var non-empty-list<Expression> Validated ordered operands
     */
    public readonly array $keys;

    /**
     * @param list<Expression> $keys
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(array $keys, public readonly ?Expression $predicate = null)
    {
        \SqlSemantics\Model\Validation\Collections::objects($keys, Expression::class);
        if ($keys === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An index selector requires its keys.');
        }
        $this->keys = \SqlSemantics\Model\Validation\Collections::nonEmpty($keys);
    }
}
