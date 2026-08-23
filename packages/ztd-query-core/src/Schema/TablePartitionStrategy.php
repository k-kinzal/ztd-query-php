<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

enum TablePartitionStrategy: string
{
    case Range = 'range';
    case List = 'list';
    case Hash = 'hash';
}
