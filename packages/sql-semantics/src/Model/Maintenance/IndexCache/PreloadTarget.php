<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\IndexCache;

/**
 * An index preload request with its own policy for leaf pages.
 * @visibility public
 * @example Inspecting the requested page selection
 *     $request = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('LOAD INDEX INTO CACHE t IGNORE LEAVES');
 *     $request->targets[0]->ignoreLeaves // => true
 */
final class PreloadTarget
{
    /**
     * Combines a physical index request with its policy for preloading leaf pages.
     */
    public function __construct(public readonly TableIndexes $indexes, public readonly bool $ignoreLeaves = false)
    {
    }
}
