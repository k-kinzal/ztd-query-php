<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use InvalidArgumentException;

/**
 * The schemas a session reads an unqualified table name in, in order, such as its current database or its search path setting.
 *
 * A server resolves a table name without a schema in the schemas of its
 * session, so `users` and `app.users` are the same table when `app` is the
 * current database. An unqualified name refers to the table of the first
 * schema that has one, and an unqualified declaration creates its table in
 * the first schema. Names are given as the server stores them, unquoted,
 * and each database package states which paths its server can search.
 *
 * @visibility public
 * @example Reading the schema an unqualified declaration creates its table in
 *     (new \SqlSemantics\Core\SearchPath('app', 'public'))->schemas[0] // => 'app'
 */
final class SearchPath
{
    /**
     * The schemas, in the order they are searched.
     *
     * @var non-empty-list<non-empty-string>
     */
    public readonly array $schemas;

    /**
     * Lists the schemas in the order they are searched.
     *
     * @throws InvalidArgumentException When there is no schema or a schema name is empty
     */
    public function __construct(string ...$schemas)
    {
        $names = [];
        foreach ($schemas as $schema) {
            if ($schema === '') {
                throw new InvalidArgumentException('A schema name must not be empty.');
            }
            $names[] = $schema;
        }
        if ($names === []) {
            throw new InvalidArgumentException('A search path needs at least one schema.');
        }
        $this->schemas = $names;
    }
}
