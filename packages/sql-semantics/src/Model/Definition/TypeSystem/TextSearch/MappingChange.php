<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\TextSearch;

/**
 * Whether a text search configuration adds mappings for token types or replaces their existing mappings.
 * @visibility public
 * @example Reading an added mapping
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH CONFIGURATION app.english ADD MAPPING FOR word WITH english_stem');
 *     $statement->change // => \SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange::Add
 */
enum MappingChange: string
{
    case Add = 'ADD';
    case Alter = 'ALTER';
}
