<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

/**
 * What ZTD decided to do with a statement.
 *
 * A statement is either read as it stands, simulated because carrying it out
 * would change the database, or left alone because ZTD was configured to let
 * it through unrun.
 */
enum QueryKind: string
{
    case READ = 'read';
    case WRITE_SIMULATED = 'write_simulated';
    case DDL_SIMULATED = 'ddl_simulated';
    case SKIPPED = 'skipped';
}
