<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use ZtdQuery\Platform\MySql\MySqlSessionFactory;

final class ZtdMysqli
{
    public function factory(): MySqlSessionFactory
    {
        return new MySqlSessionFactory();
    }
}
