<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Schema\IndexDefinition;

/**
 * An index definition with typed key expressions and its bound row predicate.
 *
 * @example Reading a bound index key
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $index = (new \SqlSemantics\Binder($schema))->bind('CREATE INDEX ix ON t((id + 1))')->indexes[0];
 *     $index->keys[0]->type->name // => 'integer'
 *
 * @visibility public
 */
final class IndexDeclaration
{
    /**
     * @param IndexDefinition $definition Index destination and declared options
     * @param list<Expression> $keys Typed index keys in definition order
     * @param Expression|null $predicate Typed condition for a partial index
     */
    public function __construct(public readonly IndexDefinition $definition, public readonly array $keys, public readonly ?Expression $predicate)
    {
        Collections::objects($keys, Expression::class);
    }
}
