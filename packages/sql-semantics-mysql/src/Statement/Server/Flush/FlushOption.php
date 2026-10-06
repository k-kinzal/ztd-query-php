<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Flush;

/**
 * What one item of FLUSH flushes, other than tables.
 *
 * Mirrors the REFRESH_* flags of LEX::type. Each case holds the keywords it
 * is written with. QUERY CACHE and DES_KEY_FILE exist up to MySQL 5.7,
 * OPTIMIZER_COSTS from MySQL 5.7, HOSTS up to MySQL 8.x.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flush.html,
 * https://dev.mysql.com/doc/refman/5.7/en/flush.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption::BinaryLogs->value // => 'BINARY LOGS'
 */
enum FlushOption: string
{
    case ErrorLogs = 'ERROR LOGS';
    case EngineLogs = 'ENGINE LOGS';
    case GeneralLogs = 'GENERAL LOGS';
    case SlowLogs = 'SLOW LOGS';
    case BinaryLogs = 'BINARY LOGS';
    case RelayLogs = 'RELAY LOGS';
    case QueryCache = 'QUERY CACHE';
    case Hosts = 'HOSTS';
    case Privileges = 'PRIVILEGES';
    case Logs = 'LOGS';
    case Status = 'STATUS';
    case DesKeyFile = 'DES_KEY_FILE';
    case Resources = 'USER_RESOURCES';
    case OptimizerCosts = 'OPTIMIZER_COSTS';
}
