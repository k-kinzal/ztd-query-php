<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Storage;

use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A writable column location; arbitrary computed expressions are not storage paths.
 *
 * @visibility public
 * @example Inspecting an assignment destination
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('UPDATE t SET id=1');
 *     $statement->writes[0]->target instanceof \SqlSemantics\Model\Write\Storage\Path // => true
 */
interface Path
{
    /**
     * Identifies the stored column at the root of this path.
     */
    public function column(): ColumnReference|UnresolvedColumnReference;

    /**
     * Returns the destination's type without loading its current value.
     */
    public function type(): TypeDescriptor;
}
