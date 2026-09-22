<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlSemantics\Model\BoundStatement;

/**
 * Writes SQL on one line between literals, quoted identifiers, and meaningful comments.
 *
 * @example Working with SQL structure
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind('SELECT  1');
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'SELECT 1'
 *
 * @visibility public
 */
final class SimpleSerializer implements Serializer
{
    /**
     * Serializes the owned structure without consulting the original source text.
     */
    public function serialize(BoundStatement $statement): string
    {
        return Serialization\Statements::write($statement)->toString();
    }
}
