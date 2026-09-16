<?php

declare(strict_types=1);

namespace Container;

final class MySql56Container extends MySqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'mysql:5.6.51';

    public static function getGrammarVersion(): string
    {
        return 'mysql-5.6.51';
    }
}
