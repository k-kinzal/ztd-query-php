<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

/**
 * Implements the My Sql Result Column Type Resolver contract for MySQL.
 */
final class MySqlResultColumnTypeResolver implements ResultColumnTypeResolver
{
    private MySqlMysqliResultColumnTypeResolver $mysqliResolver;
    private MySqlPdoResultColumnTypeResolver $pdoResolver;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct()
    {
        $this->mysqliResolver = new MySqlMysqliResultColumnTypeResolver();
        $this->pdoResolver = new MySqlPdoResultColumnTypeResolver();
    }

    /**
     * Resolve for the supplied MySQL input.
     */
    public function resolve(array $metadata): ColumnType
    {
        if (array_key_exists('type', $metadata)) {
            return $this->mysqliResolver->resolve($metadata);
        }

        return $this->pdoResolver->resolve($metadata);
    }
}
