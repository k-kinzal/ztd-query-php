<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;

/**
 * The my sql result column type resolver, as result column type resolver.
 */
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
     * @return ColumnDeclaration
     */
    public function resolve(array $metadata): ColumnDeclaration
    {
        if (array_key_exists('type', $metadata)) {
            return $this->mysqliResolver->resolve($metadata);
        }

        return $this->pdoResolver->resolve($metadata);
    }
}
