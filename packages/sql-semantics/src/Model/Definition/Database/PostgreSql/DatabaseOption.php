<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Database\PostgreSql;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One requested PostgreSQL database property; a null value requests the server default with DEFAULT.
 * @visibility public
 * @example Reading a requested property
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE DATABASE app WITH CONNECTION LIMIT 5');
 *     $statement->options[0]->parameter // => \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::ConnectionLimit
 *     $statement->options[0]->value // => 5
 * @example Rejecting a value outside the parameter domain
 *     new \SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption(\SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter::IsTemplate, 'yes'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DatabaseOption
{
    /**
     * Text values are decoded strings, flags are booleans, and counts are integers.
     * @throws InvalidStructure
     */
    public function __construct(public readonly DatabaseParameter $parameter, public readonly string|int|bool|null $value)
    {
        if (!$parameter->accepts($value)) {
            throw new InvalidStructure('The database option value is outside the domain of ' . $parameter->value . '.');
        }
    }
}
