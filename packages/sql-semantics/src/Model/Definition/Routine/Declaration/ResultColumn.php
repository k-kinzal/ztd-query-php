<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Routine\ArgumentTypeInvariant;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * One named output column of a RETURNS TABLE function.
 * @visibility public
 * @example Reading a result column
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS TABLE(id integer) LANGUAGE sql AS 'SELECT 1'");
 *     $statement->columns[0]->name // => 'id'
 *     $statement->columns[0]->type->name // => 'integer'
 */
final class ResultColumn
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly TypeDescriptor|ColumnTypeReference $type)
    {
        CatalogInvariant::identifier($name);
        ArgumentTypeInvariant::validate($type);
    }
}
