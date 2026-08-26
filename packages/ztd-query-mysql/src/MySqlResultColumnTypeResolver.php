<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

final class MySqlResultColumnTypeResolver implements ResultColumnTypeResolver
{
    private MySqlMysqliResultColumnTypeResolver $mysqliResolver;
    private MySqlPdoResultColumnTypeResolver $pdoResolver;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->mysqliResolver = new MySqlMysqliResultColumnTypeResolver();
        $this->pdoResolver = new MySqlPdoResultColumnTypeResolver();
    }

    /**
     * Answers.
     *
     * @return ColumnType
     */
    public function resolve(array $metadata): ColumnType
    {
        if (array_key_exists('type', $metadata)) {
            return $this->mysqliResolver->resolve($metadata);
        }

        return $this->pdoResolver->resolve($metadata);
    }
}
