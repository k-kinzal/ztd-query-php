<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Definition\Routine\OrderedSetAggregate;
use SqlSemantics\Model\Definition\Routine\OrdinaryAggregate;
use SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate;

/**
 * An aggregate selected by one of the three PostgreSQL aggregate signature forms.
 * @visibility public
 * @example Addressing a zero-argument aggregate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER AGGREGATE app.count_all(*) OWNER TO alice');
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\AggregateIdentity // => true
 *     $statement->object->target instanceof \SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate // => true
 */
final class AggregateIdentity implements ObjectAddress
{
    /**
     * The signature form is part of the identity.
     */
    public function __construct(public readonly ZeroArgumentAggregate|OrdinaryAggregate|OrderedSetAggregate $target)
    {
    }
}
