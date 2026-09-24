<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Cast;

/**
 * How a cast without a cast function converts: by reinterpreting the binary value or through the text I/O functions.
 * @visibility public
 * @example Reading a binary-coercible cast
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE CAST (app.tag AS text) WITHOUT FUNCTION');
 *     $statement->mechanism // => \SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism::BinaryCoercible
 */
enum CastMechanism: string
{
    case BinaryCoercible = 'WITHOUT FUNCTION';
    case InOut = 'WITH INOUT';
}
