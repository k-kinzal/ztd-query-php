<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use Closure;
use Fuzz\Shared\Oracle\BehaviorTarget;
use PDOException;
use PhpFuzzer\Config;
use ZtdQuery\Platform\SessionFactory;

/**
 * Installs one common contract campaign with only the native driver and factory substituted.
 */
final class Campaign
{
    /**
     * Configure the common target and release its private databases at shutdown.
     * @param 'mysql'|'pgsql'|'sqlite' $driver
     */
    public static function configure(Config $config, string $driver, SessionFactory $factory): void
    {
        try {
            $native = new Sandbox($driver);
            $physical = new Sandbox($driver);
        } catch (PDOException $failure) {
            fwrite(STDERR, 'Cannot start the fuzz database: ' . $failure->getMessage() . PHP_EOL);
            exit(2);
        }
        register_shutdown_function(static function () use ($native, $physical): void {
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }
            $cleanupFailed = false;
            foreach ([$native, $physical] as $sandbox) {
                try {
                    $sandbox->close();
                } catch (PDOException $failure) {
                    fwrite(STDERR, 'Cannot release the fuzz database: ' . $failure->getMessage() . PHP_EOL);
                    $cleanupFailed = true;
                }
            }
            if ($cleanupFailed) {
                exit(2);
            }
        });
        $config->setAllowedExceptions([]);
        $config->setMaxLen(516);
        $config->setTarget(Closure::fromCallable(new BehaviorTarget($factory, $native, $physical)));
    }
}
