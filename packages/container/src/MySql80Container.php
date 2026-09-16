<?php

declare(strict_types=1);

namespace Container;

final class MySql80Container extends MySqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'mysql:8.0.44';

    public static function getGrammarVersion(): string
    {
        return 'mysql-8.0.44';
    }
}
