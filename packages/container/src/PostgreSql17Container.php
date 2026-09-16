<?php

declare(strict_types=1);

namespace Container;

final class PostgreSql17Container extends PostgreSqlContainer
{
    /** @var null|string */
    protected static $IMAGE = 'postgres:17.2';
}
