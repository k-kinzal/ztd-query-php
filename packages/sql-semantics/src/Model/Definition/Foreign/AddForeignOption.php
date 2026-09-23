<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

/**
 * Adds a named text option that must not exist at execution.
 * @visibility public
 * @example Inspecting a required option value
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ALTER FOREIGN DATA WRAPPER fdw OPTIONS (ADD format 'csv')");
 *     $statement->options[0]->option->name // => 'format'
 */
final class AddForeignOption
{
    /**
     * Requires both the option name and its text-literal operand.
     */
    public function __construct(public readonly ForeignOption $option)
    {
    }
}
