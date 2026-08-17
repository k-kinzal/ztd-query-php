<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

enum SqlTokenDialect: string
{
    case Standard = 'standard';
    case MySql = 'mysql';
}
