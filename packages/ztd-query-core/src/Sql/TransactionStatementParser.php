<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

interface TransactionStatementParser
{
    public function parse(string $sql): ?TransactionStatement;
}
