<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * How a routine runs: its language, the types whose transforms apply, and its body.
 * An inline SQL body without LANGUAGE is language sql.
 * @visibility public
 * @example Reading the language and transforms
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS integer LANGUAGE plpython3u TRANSFORM FOR TYPE hstore AS 'return 1'");
 *     $statement->implementation->language // => 'plpython3u'
 *     $statement->implementation->transforms[0]->name // => 'hstore'
 * @example Rejecting an inline body in another language
 *     $body = new \SqlSemantics\Model\Definition\Routine\Declaration\ReturnBody(\SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::PostgreSql));
 *     new \SqlSemantics\Model\Definition\Routine\Declaration\RoutineImplementation('plpgsql', [], $body); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RoutineImplementation
{
    /**
     * @param list<TypeDescriptor> $transforms Types whose language transforms apply (TRANSFORM FOR TYPE)
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $language, public readonly array $transforms, public readonly RoutineBody $body)
    {
        Collections::objects($transforms, TypeDescriptor::class);
        foreach ($transforms as $type) {
            if ($type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A transform applies to a PostgreSQL type.');
            }
        }
        BodyInvariant::language($language, $body);
    }
}
