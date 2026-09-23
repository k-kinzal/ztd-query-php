<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\View;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Storage\Parameter;

/**
 * PostgreSQL view declaration properties: self-reference policy and view options.
 * @visibility public
 * @example Inspecting a view option
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE VIEW v WITH (security_barrier = true) AS SELECT 1');
 *     $statement->properties->parameters[0]->name->parts // => ['security_barrier']
 */
final class PostgreSqlViewProperties
{
    /**
     * @param list<Parameter> $parameters Declared view options such as security_barrier or check_option
     * @throws InvalidStructure
     */
    public function __construct(public readonly bool $recursive = false, public readonly array $parameters = [])
    {
        Collections::objects($parameters, Parameter::class);
    }
}
