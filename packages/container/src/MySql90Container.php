<?php

declare(strict_types=1);

namespace Container;

final class MySql90Container extends MySqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'container-registry.oracle.com/mysql/community-server:9.0.1';

    public static function getGrammarVersion(): string
    {
        return 'mysql-9.0.1';
    }
}
