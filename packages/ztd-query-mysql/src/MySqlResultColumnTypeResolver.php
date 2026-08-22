<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;

final class MySqlResultColumnTypeResolver implements ResultColumnTypeResolver
{
    private MySqlMysqliResultColumnTypeResolver $mysqliResolver;
    private MySqlPdoResultColumnTypeResolver $pdoResolver;

    public function __construct()
    {
        $this->mysqliResolver = new MySqlMysqliResultColumnTypeResolver();
        $this->pdoResolver = new MySqlPdoResultColumnTypeResolver();
    }

    public function resolve(array $metadata): ColumnType
    {
        if (array_key_exists('type', $metadata)) {
            return $this->mysqliResolver->resolve($metadata);
        }

        return $this->pdoResolver->resolve($metadata);
    }
}
