<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator\Reflection;

/**
 * Maps database names to constructor or property names.
 *
 * @visibility root
 */
final class PropertyNames
{
    /**
     * Maps a camel-case parameter to its database column spelling.
     */
    public function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input) ?? $input);
    }

    /**
     * Maps a database column spelling to a PHP property name.
     */
    public function toCamelCase(string $input): string
    {
        $result = str_replace('_', '', ucwords($input, '_'));
        return lcfirst($result);
    }
}
