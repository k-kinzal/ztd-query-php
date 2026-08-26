<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

/**
 * Converts between the spelling a column uses and the spelling a property uses.
 *
 * SQL columns are conventionally written in snake_case and PHP properties in
 * camelCase, so hydration has to try a name both ways round before deciding a
 * column has nowhere to go. Which spelling is which is a convention rather
 * than a rule, so both conversions are stated in one place where they can be
 * read against each other.
 */
final class PropertyName
{
    /**
     * Answers a name as a column would spell it.
     *
     * @param string $name Name as a property spells it
     *
     * @return string The same name in snake_case
     */
    public static function toSnakeCase(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name);
    }

    /**
     * Answers a name as a property would spell it.
     *
     * @param string $name Name as a column spells it
     *
     * @return string The same name in camelCase
     */
    public static function toCamelCase(string $name): string
    {
        return lcfirst(str_replace('_', '', ucwords($name, '_')));
    }
}
