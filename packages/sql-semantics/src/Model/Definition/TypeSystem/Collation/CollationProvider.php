<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Collation;

/**
 * The library that implements a collation.
 * @visibility public
 * @example Reading the provider of an ICU collation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE COLLATION german (PROVIDER = ICU, LOCALE = 'de-DE')");
 *     $statement->provider // => \SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider::Icu
 */
enum CollationProvider: string
{
    case Libc = 'libc';
    case Icu = 'icu';
    case Builtin = 'builtin';
}
