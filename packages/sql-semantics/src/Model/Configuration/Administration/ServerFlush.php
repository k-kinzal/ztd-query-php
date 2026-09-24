<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

use Override;

/**
 * The FLUSH options that take no operand, spelled as their keywords.
 * @visibility public
 * @example Checking a release-specific option
 *     \SqlSemantics\Model\Configuration\Administration\ServerFlush::QueryCache->availableIn(80044) // => false
 */
enum ServerFlush: string implements FlushTarget
{
    case ErrorLogs = 'ERROR LOGS';
    case EngineLogs = 'ENGINE LOGS';
    case GeneralLogs = 'GENERAL LOGS';
    case SlowLogs = 'SLOW LOGS';
    case BinaryLogs = 'BINARY LOGS';
    case Logs = 'LOGS';
    case QueryCache = 'QUERY CACHE';
    case Hosts = 'HOSTS';
    case Privileges = 'PRIVILEGES';
    case Status = 'STATUS';
    case DesKeyFile = 'DES_KEY_FILE';
    case UserResources = 'USER_RESOURCES';
    case OptimizerCosts = 'OPTIMIZER_COSTS';

    /**
     * The query cache and DES key file exist before MySQL 8.0, HOSTS before 8.4 and OPTIMIZER_COSTS from 5.7.
     */
    #[Override]
    public function availableIn(int $release): bool
    {
        return match ($this) {
            self::QueryCache, self::DesKeyFile => $release < 80000,
            self::Hosts => $release < 80400,
            self::OptimizerCosts => $release >= 50700,
            self::ErrorLogs, self::EngineLogs, self::GeneralLogs, self::SlowLogs, self::BinaryLogs, self::Logs, self::Privileges, self::Status, self::UserResources => true,
        };
    }
}
