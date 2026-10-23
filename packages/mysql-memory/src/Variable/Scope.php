<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

/**
 * Where a system variable lives: only globally, only in the session, or both.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/system-variables.html.
 *
 * @visibility MySqlMemory
 */
enum Scope: string
{
    case Global = 'GLOBAL';
    case Session = 'SESSION';
    case Both = 'BOTH';
}
