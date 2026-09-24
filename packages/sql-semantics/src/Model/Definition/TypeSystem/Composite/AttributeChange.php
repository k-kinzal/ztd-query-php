<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Composite;

/**
 * One change ALTER TYPE applies to the attributes of a composite type.
 * @visibility public
 * @example Reading the changes of a composite type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair ADD ATTRIBUTE note text, DROP ATTRIBUTE amount');
 *     $statement->changes[0] instanceof \SqlSemantics\Model\Definition\TypeSystem\Composite\AttributeChange // => true
 *     $statement->changes[1] instanceof \SqlSemantics\Model\Definition\TypeSystem\Composite\DropAttribute // => true
 */
interface AttributeChange
{
}
