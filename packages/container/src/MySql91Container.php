<?php

declare(strict_types=1);

namespace Container;

final class MySql91Container extends MySqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'container-registry.oracle.com/mysql/community-server:9.1.0';

    public static function getGrammarVersion(): string
    {
        return 'mysql-9.1.0';
    }
}
