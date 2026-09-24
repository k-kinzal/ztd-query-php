<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A C routine loaded from an object file by its link symbol (AS 'file', 'symbol').
 * @visibility public
 * @example Reading the object file and symbol
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS integer LANGUAGE c AS 'mylib', 'f_impl'");
 *     $statement->implementation->body->file->text // => "'mylib'"
 *     $statement->implementation->body->symbol->text // => "'f_impl'"
 */
final class LinkedBody implements RoutineBody
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $file, public readonly Literal $symbol)
    {
        BodyInvariant::text($file);
        BodyInvariant::text($symbol);
    }
}
