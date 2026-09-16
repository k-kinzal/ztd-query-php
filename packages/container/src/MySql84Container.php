<?php

declare(strict_types=1);

namespace Container;

final class MySql84Container extends MySqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'container-registry.oracle.com/mysql/community-server:8.4.7';

    public static function getGrammarVersion(): string
    {
        return 'mysql-8.4.7';
    }
}
