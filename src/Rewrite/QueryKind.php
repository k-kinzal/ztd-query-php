<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

enum QueryKind: string
{
    case READ = 'read';
    case WRITE_SIMULATED = 'write_simulated';
    case DDL_SIMULATED = 'ddl_simulated';
    case FORBIDDEN = 'forbidden';
    case UNKNOWN_SCHEMA = 'unknown_schema';
}
