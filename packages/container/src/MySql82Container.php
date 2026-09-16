<?php

declare(strict_types=1);

namespace Container;

final class MySql82Container extends MySqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'mysql:8.2.0';

    public static function getGrammarVersion(): string
    {
        return 'mysql-8.2.0';
    }
}
